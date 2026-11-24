<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AllowanceType;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\LeaveAdjustment;
use App\Models\LeaveRequest;
use App\Models\OtEntry;
use App\Models\PayrollRun;
use App\Models\Setting;
use App\Services\EmployeeMatcher;
use App\Services\ImportService;
use App\Services\LeaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bulk data entry from the office's Excel files: OT sheets, leave registers and the employee list.
 * Flow: upload → validation preview (errors block the row, warnings are shown) → "I have verified" → double confirmation → save.
 */
class ImportController extends Controller
{
    /** Upper limit for any hours cell in a month (31 days × 24 h). */
    public const MAX_HOURS = 744;

    /** Above this many OT hours in a month a warning is shown. */
    public const HIGH_OT = 200;

    public function __construct(private ImportService $import)
    {
    }

    public function index()
    {
        return view('imports.index', ['period' => year_period()]);
    }

    // ---------------------------------------------------------------- uploads kept briefly for sheet switching
    private function dir(): string
    {
        $dir = storage_path('app/private/imports');
        File::ensureDirectoryExists($dir);
        foreach (File::files($dir) as $f) {
            if ($f->getMTime() < time() - 86400) {
                File::delete($f->getPathname());
            }
        }

        return $dir;
    }

    private function storeUpload(Request $request): string
    {
        $request->validate(
            ['file' => ['required', 'file', 'max:10240', 'mimes:xls,xlsx,csv,txt']],
            ['file.mimes' => 'Please upload an Excel file (.xlsx or .xls) or a .csv file.', 'file.max' => 'The file is larger than 10 MB.']
        );
        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        $token = Str::random(32).'.'.(in_array($ext, ['xls', 'xlsx', 'csv'], true) ? $ext : 'csv');
        $request->file('file')->move($this->dir(), $token);
        try {
            $this->openStored($token);
        } catch (\Throwable $e) {
            File::delete($this->dir().DIRECTORY_SEPARATOR.$token);
            throw ValidationException::withMessages(['file' => 'The file could not be read as a spreadsheet. Save it as .xlsx and try again.']);
        }

        return $token;
    }

    private function openStored(string $token)
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{32}\.(xls|xlsx|csv)$/', $token), 404);
        $path = $this->dir().DIRECTORY_SEPARATOR.$token;
        abort_unless(is_file($path), 422, 'The uploaded file has expired. Please upload it again.');

        return $this->import->load(new \Illuminate\Http\UploadedFile($path, $token, null, null, true));
    }

    private static function summary(array $rows): array
    {
        $c = collect($rows);

        return [
            'total' => $c->count(),
            'ok' => $c->filter(fn ($r) => ! $r['errors'] && ! $r['warnings'] && $r['employee_id'])->count(),
            'warnings' => $c->filter(fn ($r) => ! $r['errors'] && $r['warnings'])->count(),
            'errors' => $c->filter(fn ($r) => (bool) $r['errors'])->count(),
            'importable' => $c->filter(fn ($r) => ! $r['errors'] && $r['employee_id'])->count(),
        ];
    }

    private static function onRolls(Employee $e, Carbon $start, Carbon $end): bool
    {
        return (! $e->date_of_joining || $e->date_of_joining->lte($end)) && (! $e->date_of_exit || $e->date_of_exit->gte($start));
    }

    // ================================================================ OT
    public function otUpload(Request $request)
    {
        $request->validate(['period' => ['required', 'date_format:Y-m']]);
        $token = $this->storeUpload($request);

        return redirect()->route('imports.ot.preview', ['token' => $token, 'period' => $request->period]);
    }

    public function otPreview(Request $request)
    {
        $request->validate(['period' => ['required', 'date_format:Y-m'], 'token' => ['required', 'string']]);
        [$year, $month] = array_map('intval', explode('-', $request->period));
        $book = $this->openStored($request->token);
        $sheet = $request->sheet && in_array($request->sheet, $book->getSheetNames(), true) ? $request->sheet : $this->import->guessSheet($book, $year, $month);
        $fatal = null;
        try {
            $rows = $this->import->parseOt($book, $sheet);
        } catch (\RuntimeException $e) {
            $rows = [];
            $fatal = $e->getMessage();
        }
        if (! $fatal && ! $rows) {
            $fatal = "No employee rows were found in sheet \"$sheet\".";
        }
        $matcher = new EmployeeMatcher();
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();
        $existing = OtEntry::where('year', $year)->where('month', $month)->get()->keyBy('employee_id');
        $seen = collect($rows)->map(fn ($r) => $matcher->match($r['name'], $r['code'])[0]?->id)->filter()->countBy();

        foreach ($rows as &$r) {
            [$emp, $how] = $matcher->match($r['name'], $r['code']);
            $r['employee_id'] = $emp?->id;
            $r['how'] = $how;
            $r['errors'] = [];
            $r['warnings'] = [];
            foreach ($r['invalid'] as $bad) {
                $r['errors'][] = "Not a number: $bad";
            }
            foreach (['second' => '2nd Shift', 'third' => '3rd Shift', 'normal' => 'Normal OT', 'spl' => 'Spl OT'] as $k => $label) {
                if ($r[$k] < 0) {
                    $r['errors'][] = "$label is negative";
                } elseif ($r[$k] > self::MAX_HOURS) {
                    $r['errors'][] = "$label ({$r[$k]}) is more than ".self::MAX_HOURS.' hours';
                }
            }
            if (! $emp) {
                $r['warnings'][] = 'Employee not found — choose one or the row is skipped';
            } else {
                if (($seen[$emp->id] ?? 0) > 1) {
                    $r['errors'][] = "{$emp->name} appears more than once in the file";
                }
                if (! $emp->ot_eligible) {
                    $r['warnings'][] = 'Employee is marked "not eligible for OT"';
                }
                if (! self::onRolls($emp, $start, $end)) {
                    $r['warnings'][] = 'Employee was not on the rolls in '.$start->format('M Y');
                }
                if ($how === 'close') {
                    $r['warnings'][] = "Matched by similar name to {$emp->name} — please check";
                }
                if ($r['normal'] + $r['spl'] > self::HIGH_OT) {
                    $r['warnings'][] = 'Unusually high OT ('.($r['normal'] + $r['spl']).' h)';
                }
                if ($old = $existing[$emp->id] ?? null) {
                    $r['old'] = [num_clean($old->second_shift_hours), num_clean($old->third_shift_hours), num_clean($old->normal_ot_hours), num_clean($old->spl_ot_hours), $old->status];
                }
            }
            if ($r['second'] + $r['third'] + $r['normal'] + $r['spl'] == 0 && ! $r['errors']) {
                $r['warnings'][] = 'All values are zero';
            }
        }
        unset($r);

        return view('imports.ot-preview', [
            'rows' => $rows, 'sheet' => $sheet, 'sheets' => $book->getSheetNames(), 'token' => $request->token, 'fatal' => $fatal,
            'year' => $year, 'month' => $month, 'employees' => $matcher->employees(), 'summary' => self::summary($rows),
            'locked' => PayrollRun::isLocked($year, $month), 'maxHours' => self::MAX_HOURS,
        ]);
    }

    public function otStore(Request $request)
    {
        $max = self::MAX_HOURS;
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'status' => ['required', 'in:pending,approved'],
            'verified' => ['accepted'],
            'rows' => ['required', 'array'],
            'rows.*.include' => ['nullable', 'boolean'],
            'rows.*.employee_id' => ['nullable', 'exists:employees,id'],
            'rows.*.sheet_name' => ['nullable', 'string', 'max:150'],
            'rows.*.how' => ['nullable', 'string', 'max:10'],
        ], ['verified.accepted' => 'Please tick "I have verified the preview" before importing.']);
        [$year, $month] = array_map('intval', explode('-', $data['period']));
        abort_if(PayrollRun::isLocked($year, $month), 422, 'Payroll for this month is finalized; OT cannot be imported.');

        // only the rows ticked in the preview are validated and saved
        $rows = collect($request->input('rows', []))->filter(fn ($r) => ! empty($r['include']) && ! empty($r['employee_id']));
        $rules = $names = [];
        foreach ($rows as $i => $r) {
            foreach (['second' => '2nd Shift', 'third' => '3rd Shift', 'normal' => 'Normal OT', 'spl' => 'Spl OT'] as $k => $label) {
                $rules["$i.$k"] = ['nullable', 'numeric', 'min:0', "max:$max"];
                $names["$i.$k"] = 'Row '.($i + 1)." – $label";
            }
            $rules["$i.remarks"] = ['nullable', 'string', 'max:200'];
            $names["$i.remarks"] = 'Row '.($i + 1).' – Remarks';
        }
        Validator::make($rows->all(), $rules, [], $names)->validate();
        $dupes = $rows->countBy('employee_id')->filter(fn ($n) => $n > 1)->keys();
        if ($dupes->isNotEmpty()) {
            throw ValidationException::withMessages(['rows' => 'The same employee is selected on more than one row: '.Employee::whereIn('id', $dupes)->pluck('name')->implode(', ')]);
        }
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['rows' => 'No rows were selected for import.']);
        }

        $saved = 0;
        DB::transaction(function () use ($rows, $data, $year, $month, &$saved) {
            foreach ($rows as $r) {
                $emp = Employee::find($r['employee_id']);
                if (in_array($r['how'] ?? '', ['none', 'close'], true)) {
                    EmployeeMatcher::remember($emp, $r['sheet_name'] ?? '');
                }
                OtEntry::updateOrCreate(['employee_id' => $emp->id, 'year' => $year, 'month' => $month], [
                    'second_shift_hours' => (float) ($r['second'] ?? 0),
                    'third_shift_hours' => (float) ($r['third'] ?? 0),
                    'normal_ot_hours' => (float) ($r['normal'] ?? 0),
                    'spl_ot_hours' => (float) ($r['spl'] ?? 0),
                    'remarks' => $r['remarks'] ?? null,
                    'status' => $data['status'],
                    'created_by' => auth()->id(),
                    'approved_by' => $data['status'] === 'approved' ? auth()->id() : null,
                    'approved_at' => $data['status'] === 'approved' ? now() : null,
                ]);
                $saved++;
            }
        });
        $label = Carbon::create($year, $month)->format('M Y');
        ActivityLog::record('import', "Imported OT for $label ($saved employees, {$data['status']})");

        return redirect()->route('ot.index', ['period' => $data['period']])->with('success', "$saved OT rows imported for $label ({$data['status']}).");
    }

    // ================================================================ Leave register
    public function leaveUpload(Request $request)
    {
        $request->validate(['year' => ['required', 'integer', 'min:2000', 'max:2100']]);
        $token = $this->storeUpload($request);

        return redirect()->route('imports.leave.preview', ['token' => $token, 'year' => $request->year]);
    }

    public function leavePreview(Request $request, LeaveService $leave)
    {
        $request->validate(['year' => ['required', 'integer'], 'token' => ['required', 'string']]);
        $year = (int) $request->year;
        $book = $this->openStored($request->token);
        $sheet = $request->sheet && in_array($request->sheet, $book->getSheetNames(), true) ? $request->sheet : $this->import->guessSheet($book, $year);
        $fatal = null;
        try {
            $rows = $this->import->parseLeaveRegister($book, $sheet);
        } catch (\RuntimeException $e) {
            $rows = [];
            $fatal = $e->getMessage();
        }
        if (! $fatal && ! $rows) {
            $fatal = "No employee rows were found in sheet \"$sheet\".";
        }
        if (! $fatal && ! preg_match('/\b'.$year.'\b/', $sheet)) {
            session()->now('warning', "The sheet \"$sheet\" does not mention $year. Make sure you picked the right sheet for $year.");
        }
        $matcher = new EmployeeMatcher();
        $locked = PayrollRun::where('year', $year)->where('status', 'finalized')->pluck('month')->all();
        $seen = collect($rows)->map(fn ($r) => $matcher->match($r['name'], $r['code'])[0]?->id)->filter()->countBy();

        foreach ($rows as &$r) {
            [$emp, $how] = $matcher->match($r['name'], $r['code']);
            $r['employee_id'] = $emp?->id;
            $r['how'] = $how;
            $r['errors'] = [];
            $r['warnings'] = [];
            $r['cell'] = [];
            foreach ($r['invalid'] as $m => $bad) {
                $r['errors'][] = month_name($m, true).': "'.mb_substr($bad, 0, 15).'" is not a number';
                $r['cell'][$m] = 'error';
            }
            foreach ($r['months'] as $m => $v) {
                if ($v === null) {
                    continue;
                }
                $dim = Carbon::create($year, $m, 1)->daysInMonth;
                if ($v < 0 || $v > $dim) {
                    $r['errors'][] = month_name($m, true).": $v days is not possible (0 – $dim)";
                    $r['cell'][$m] = 'error';
                }
                if ($emp && $v > 0 && ! self::onRolls($emp, Carbon::create($year, $m, 1), Carbon::create($year, $m, 1)->endOfMonth())) {
                    $r['warnings'][] = month_name($m, true).': employee was not on the rolls';
                    $r['cell'][$m] ??= 'warning';
                }
            }
            if (! $emp) {
                $r['warnings'][] = 'Employee not found — choose one or the row is skipped';
            } else {
                if (($seen[$emp->id] ?? 0) > 1) {
                    $r['errors'][] = "{$emp->name} appears more than once in the file";
                }
                if ($how === 'close') {
                    $r['warnings'][] = "Matched by similar name to {$emp->name} — please check";
                }
                $total = array_sum(array_filter($r['months']));
                $pool = $leave->pool($emp, $year);
                if ($total > $pool) {
                    $r['warnings'][] = 'Total '.num_clean($total)." days exceeds the yearly entitlement of $pool — the extra is Loss of Pay";
                }
            }
            foreach ($locked as $m) {
                if (($r['months'][$m] ?? null) !== null) {
                    $r['cell'][$m] = 'locked';
                }
            }
        }
        unset($r);

        return view('imports.leave-preview', [
            'rows' => $rows, 'sheet' => $sheet, 'sheets' => $book->getSheetNames(), 'token' => $request->token, 'fatal' => $fatal,
            'year' => $year, 'employees' => $matcher->employees(), 'lockedMonths' => $locked, 'summary' => self::summary($rows),
        ]);
    }

    public function leaveStore(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'verified' => ['accepted'],
            'skip_with_requests' => ['nullable', 'boolean'],
            'rows' => ['required', 'array'],
            'rows.*.include' => ['nullable', 'boolean'],
            'rows.*.employee_id' => ['nullable', 'exists:employees,id'],
            'rows.*.sheet_name' => ['nullable', 'string', 'max:150'],
            'rows.*.how' => ['nullable', 'string', 'max:10'],
            'rows.*.m' => ['nullable', 'array'],
        ], ['verified.accepted' => 'Please tick "I have verified the preview" before importing.']);
        $year = (int) $data['year'];
        $rows = collect($request->input('rows', []))->filter(fn ($r) => ! empty($r['include']) && ! empty($r['employee_id']));
        $rules = $names = [];
        foreach ($rows as $i => $r) {
            foreach (array_keys($r['m'] ?? []) as $m) {
                $rules["$i.m.$m"] = ['nullable', 'numeric', 'min:0', 'max:31'];
                $names["$i.m.$m"] = 'Row '.($i + 1).' – '.month_name((int) $m, true);
            }
        }
        Validator::make($rows->all(), $rules, [], $names)->validate();
        $dupes = $rows->countBy('employee_id')->filter(fn ($n) => $n > 1)->keys();
        if ($dupes->isNotEmpty()) {
            throw ValidationException::withMessages(['rows' => 'The same employee is selected on more than one row: '.Employee::whereIn('id', $dupes)->pluck('name')->implode(', ')]);
        }
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['rows' => 'No rows were selected for import.']);
        }
        $locked = PayrollRun::where('year', $year)->where('status', 'finalized')->pluck('month')->flip();
        $skipWithRequests = $request->boolean('skip_with_requests');
        $note = 'Imported from Excel register';
        $stats = ['set' => 0, 'locked' => 0, 'skipped' => 0];
        DB::transaction(function () use ($rows, $year, $locked, $skipWithRequests, $note, &$stats) {
            foreach ($rows as $r) {
                $emp = Employee::find($r['employee_id']);
                if (in_array($r['how'] ?? '', ['none', 'close'], true)) {
                    EmployeeMatcher::remember($emp, $r['sheet_name'] ?? '');
                }
                foreach (($r['m'] ?? []) as $m => $days) {
                    $m = (int) $m;
                    if ($days === null || $days === '' || $m < 1 || $m > 12) {
                        continue;
                    }
                    if ((float) $days > Carbon::create($year, $m, 1)->daysInMonth) {
                        continue;
                    }
                    if (isset($locked[$m])) {
                        $stats['locked']++;

                        continue;
                    }
                    if ($skipWithRequests && LeaveRequest::where('employee_id', $emp->id)->where('status', 'approved')
                        ->whereYear('from_date', $year)->whereMonth('from_date', $m)->exists()) {
                        $stats['skipped']++;

                        continue;
                    }
                    LeaveAdjustment::where(['employee_id' => $emp->id, 'year' => $year, 'month' => $m, 'note' => $note])->delete();
                    if ((float) $days > 0) {
                        LeaveAdjustment::create(['employee_id' => $emp->id, 'year' => $year, 'month' => $m, 'days' => $days, 'note' => $note, 'created_by' => auth()->id()]);
                    }
                    $stats['set']++;
                }
            }
        });
        ActivityLog::record('import', "Imported leave register $year ({$stats['set']} employee-months)");
        $msg = "Leave register $year imported: {$stats['set']} employee-months updated.";
        if ($stats['locked']) {
            $msg .= " {$stats['locked']} values skipped (finalized months).";
        }
        if ($stats['skipped']) {
            $msg .= " {$stats['skipped']} values skipped (months already having approved leave requests).";
        }

        return redirect()->route('leave.register', ['year' => $year])->with('success', $msg);
    }

    // ================================================================ Employees
    // ---------------------------------------------------------------- monthly salary sheet
    public function salaryUpload(Request $request)
    {
        $request->validate(['period' => ['required', 'date_format:Y-m']]);
        $token = $this->storeUpload($request);

        return redirect()->route('imports.salary.preview', ['token' => $token, 'period' => $request->period]);
    }

    public function salaryPreview(Request $request, \App\Services\SalarySheetImporter $importer)
    {
        $request->validate(['period' => ['required', 'date_format:Y-m'], 'token' => ['required', 'string']]);
        [$year, $month] = array_map('intval', explode('-', $request->period));
        $book = $this->openStored($request->token);
        $sheet = $request->sheet && in_array($request->sheet, $book->getSheetNames(), true) ? $request->sheet : $this->import->guessSheet($book, $year, $month);
        $fatal = null;
        $result = ['rows' => [], 'missing' => collect(), 'run' => null];
        $parsed = ['columns' => [], 'allowances' => []];
        try {
            $parsed = $this->import->parseSalarySheet($book, $sheet);
            $result = $importer->analyse($parsed, $year, $month);
            if (! $result['rows']) {
                $fatal = 'No employee rows were found under the header row.';
            }
        } catch (\RuntimeException $e) {
            $fatal = $e->getMessage();
        }
        $label = Carbon::create($year, $month)->format('F Y');
        if (! $fatal && ! preg_match('/'.preg_quote(Carbon::create($year, $month)->format('M'), '/').'[a-z]*\W*'.$year.'/i', $sheet)) {
            session()->now('warning', "The sheet “{$sheet}” does not look like $label. Check that you picked the right month and sheet.");
        }

        return view('imports.salary-preview', [
            'token' => $request->token, 'year' => $year, 'month' => $month, 'label' => $label,
            'sheets' => $book->getSheetNames(), 'sheet' => $sheet, 'fatal' => $fatal,
            'rows' => $result['rows'], 'missing' => $result['missing'], 'run' => $result['run'],
            'columns' => $parsed['columns'], 'allowanceCols' => $parsed['allowances'],
            'summary' => [ // new employees are importable here (they are registered from the sheet)
                'total' => count($result['rows']),
                'ok' => collect($result['rows'])->filter(fn ($r) => ! $r['errors'] && ! $r['warnings'])->count(),
                'warnings' => collect($result['rows'])->filter(fn ($r) => ! $r['errors'] && $r['warnings'])->count(),
                'errors' => collect($result['rows'])->filter(fn ($r) => (bool) $r['errors'])->count(),
                'importable' => collect($result['rows'])->filter(fn ($r) => ! $r['errors'])->count(),
            ],
            'payload' => $fatal ? '' : Crypt::encryptString(json_encode($result['rows'])),
        ]);
    }

    public function salaryStore(Request $request, \App\Services\SalarySheetImporter $importer)
    {
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'], 'payload' => ['required', 'string'], 'verified' => ['accepted'],
            'update_master' => ['nullable', 'boolean'], 'create_new' => ['nullable', 'boolean'], 'save_payroll' => ['nullable', 'boolean'],
        ], ['verified.accepted' => 'Please tick "I have verified the preview" before importing.']);
        [$year, $month] = array_map('intval', explode('-', $data['period']));
        $opts = ['update_master' => $request->boolean('update_master'), 'create_new' => $request->boolean('create_new'), 'save_payroll' => $request->boolean('save_payroll')];
        if (! array_filter($opts)) {
            return back()->with('warning', 'Choose at least one thing to import.');
        }
        $rows = json_decode(Crypt::decryptString($data['payload']), true) ?: [];
        try {
            $s = $importer->apply($rows, $year, $month, $opts);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $label = Carbon::create($year, $month)->format('F Y');
        $parts = array_filter([
            $s['updated'] ? "{$s['updated']} employees updated" : null,
            $s['created'] ? "{$s['created']} new employees" : null,
            $s['types'] ? "{$s['types']} new allowance column(s)" : null,
            $s['payslips'] ? "{$s['payslips']} payslips saved for $label (draft)" : null,
        ]);
        ActivityLog::record('import', "Imported salary sheet $label: ".($parts ? implode(', ', $parts) : 'nothing changed'));
        $msg = "Salary sheet $label imported: ".($parts ? implode(', ', $parts) : 'nothing changed').'.';

        return $s['payslips'] && $s['run']
            ? redirect()->route('payroll.show', $s['run'])->with('success', $msg.' Review the payslips, then finalize.')
            : redirect()->route('employees.index')->with('success', $msg);
    }

    public function employeesUpload(Request $request)
    {
        $token = $this->storeUpload($request);
        $book = $this->openStored($token);
        $types = AllowanceType::ordered()->pluck('name')->all();
        try {
            $rows = $this->import->parseEmployees($book, $types);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        if (! $rows) {
            return back()->with('error', 'No employee rows were found in the file.');
        }
        $existing = Employee::withTrashed()->with('allowances.type')->get()->keyBy(fn ($e) => strtolower($e->emp_code));
        $codes = collect($rows)->countBy(fn ($r) => strtolower($r['emp_code']));
        $groups = Setting::otGroups();
        $labels = ['name' => 'Name', 'designation' => 'Designation', 'grade' => 'Grade', 'ot_group' => 'Plant', 'uan_no' => 'EPF/UAN',
            'esic_no' => 'ESIC', 'email' => 'Email', 'phone' => 'Phone', 'basic_salary' => 'Basic', 'manager_special' => 'Manager spl',
            'factory_special' => 'Factory spl', 'monthly_tax' => 'Tax', 'status' => 'Status', 'date_of_joining' => 'Joined', 'date_of_exit' => 'Exit'];

        foreach ($rows as &$r) {
            $old = $existing[strtolower($r['emp_code'])] ?? null;
            $r['action'] = $old ? 'update' : 'create';
            $v = Validator::make($r, [
                'emp_code' => ['required', 'max:30'],
                'name' => ['required', 'max:150'],
                'date_of_joining' => [$old ? 'nullable' : 'required', 'date'],
                'date_of_exit' => ['nullable', 'date'],
                'basic_salary' => [$old ? 'nullable' : 'required', 'numeric', 'min:0', 'max:99999999'],
                'manager_special' => ['nullable', 'numeric', 'min:0'],
                'factory_special' => ['nullable', 'numeric', 'min:0'],
                'monthly_tax' => ['nullable', 'numeric', 'min:0'],
                'email' => ['nullable', 'email'],
                'status' => ['nullable', 'in:active,inactive,exited,Active,Inactive,Exited'],
            ], [
                'date_of_joining.required' => 'Joining date is missing or not a valid date (use dd-mm-yyyy)',
                'basic_salary.required' => 'Basic salary is required for a new employee',
                'email.email' => 'Email address is not valid',
                'status.in' => 'Status must be active, inactive or exited',
            ]);
            $r['errors'] = $v->errors()->all();
            $r['warnings'] = [];
            if ($codes[strtolower($r['emp_code'])] > 1) {
                $r['errors'][] = "Emp ID {$r['emp_code']} appears more than once in the file";
            }
            $join = $r['date_of_joining'] ?? $old?->date_of_joining?->toDateString();
            if (! empty($r['date_of_exit']) && $join && $r['date_of_exit'] < $join) {
                $r['errors'][] = 'Exit date is before the joining date';
            }
            if (! empty($r['ot_group']) && ! in_array($r['ot_group'], $groups, true)) {
                $r['warnings'][] = "Plant \"{$r['ot_group']}\" is not in the plant list (Settings)";
            }
            if (array_key_exists('email', $r) && ! $r['email']) {
                $r['warnings'][] = 'No email — payslip cannot be emailed';
            }
            // what will change
            $r['changes'] = [];
            if ($old) {
                foreach ($labels as $f => $lbl) {
                    if (! array_key_exists($f, $r) || $r[$f] === null || $r[$f] === '') {
                        continue;
                    }
                    $was = $old->$f instanceof \DateTimeInterface ? $old->$f->toDateString() : $old->$f;
                    $new = $r[$f];
                    if (is_numeric($new) && is_numeric($was) ? (float) $new != (float) $was : strcasecmp(trim((string) $new), trim((string) $was)) !== 0) {
                        $r['changes'][] = "$lbl: ".($was === null || $was === '' ? '—' : (is_numeric($was) ? num_clean($was) : $was)).' → '.(is_numeric($new) ? num_clean($new) : $new);
                    }
                }
                foreach ($r['allowances'] ?? [] as $name => $amount) {
                    $was = (float) optional($old->allowances->first(fn ($a) => $a->type?->name === $name))->amount;
                    if ((float) $amount != $was) {
                        $r['changes'][] = "$name: ".num_clean($was).' → '.num_clean($amount);
                    }
                }
                if (isset($r['basic_salary'], $old->basic_salary) && $old->basic_salary > 0 && abs($r['basic_salary'] - $old->basic_salary) / $old->basic_salary > 0.3) {
                    $r['warnings'][] = 'Basic salary changes by more than 30%';
                }
            }
            $r['employee_id'] = $old?->id ?? 'new';
        }
        unset($r);

        return view('imports.employees-preview', [
            'rows' => $rows,
            'summary' => self::summary($rows),
            'payload' => Crypt::encryptString(json_encode($rows)),
        ]);
    }

    public function employeesStore(Request $request)
    {
        $request->validate(['payload' => ['required', 'string'], 'verified' => ['accepted']],
            ['verified.accepted' => 'Please tick "I have verified the preview" before importing.']);
        $rows = json_decode(Crypt::decryptString($request->payload), true) ?: [];
        $types = AllowanceType::pluck('id', 'name');
        $created = $updated = 0;
        DB::transaction(function () use ($rows, $types, &$created, &$updated) {
            foreach ($rows as $r) {
                if ($r['errors']) {
                    continue;
                }
                $fields = collect($r)->only(['name', 'designation', 'grade', 'ot_group', 'uan_no', 'esic_no', 'status', 'email', 'phone',
                    'bank_account_no', 'bank_ifsc', 'pan_no', 'basic_salary', 'manager_special', 'factory_special', 'other_allowance', 'general_allowance', 'monthly_tax',
                    'pf_applicable', 'esi_applicable', 'date_of_joining', 'date_of_exit'])
                    ->reject(fn ($v) => $v === null || $v === '')
                    ->all();
                if (isset($fields['status'])) {
                    $fields['status'] = strtolower($fields['status']);
                }
                $emp = Employee::withTrashed()->where('emp_code', $r['emp_code'])->first();
                if ($emp) {
                    if ($emp->trashed()) {
                        $emp->restore();
                    }
                    $emp->update($fields);
                    $updated++;
                } else {
                    $emp = Employee::create($fields + ['emp_code' => $r['emp_code'], 'status' => 'active', 'created_by' => auth()->id(),
                        'ot_multiplier' => Setting::num('ot_multiplier')]);
                    $created++;
                }
                foreach ($r['allowances'] ?? [] as $name => $amount) {
                    if (! isset($types[$name])) {
                        continue;
                    }
                    if ($amount > 0) {
                        EmployeeAllowance::updateOrCreate(['employee_id' => $emp->id, 'allowance_type_id' => $types[$name]], ['amount' => $amount]);
                    } else {
                        EmployeeAllowance::where(['employee_id' => $emp->id, 'allowance_type_id' => $types[$name]])->delete();
                    }
                }
            }
        });
        ActivityLog::record('import', "Imported employees ($created created, $updated updated)");

        return redirect()->route('employees.index', ['status' => 'all'])->with('success', "Employees imported: $created created, $updated updated.");
    }

    // ================================================================ templates (.xlsx)
    public function template(Request $request, string $type, LeaveService $leave)
    {
        if ($type === 'ot') {
            $d = Carbon::createFromFormat('Y-m-d', ($request->period ?: working_period()->format('Y-m')).'-01');
            $existing = OtEntry::where('year', $d->year)->where('month', $d->month)->get()->keyBy('employee_id');
            $emps = Employee::where('ot_eligible', true)->where('status', 'active')->orderBy('ot_group')->orderBy('emp_code')->get();
            $rows = $emps->map(function ($e) use ($existing) {
                $x = $existing[$e->id] ?? null;

                return [$e->emp_code, $e->name, $e->ot_group, $x?->second_shift_hours, $x?->third_shift_hours, $x?->normal_ot_hours, $x?->spl_ot_hours, $x?->remarks];
            });
            $max = self::MAX_HOURS;

            return xlsx_download('ot-template-'.$d->format('Y-m').'.xlsx', ['Emp ID', 'Name', 'Plant', '2nd Shift', '3rd Shift', 'Normal OT', 'Spl OT', 'Remarks'], $rows, [
                'title' => $d->format('M Y'),
                'spare_rows' => 50,
                'validations' => [2 => ['list' => Setting::otGroups()], 3 => ['decimal' => [0, $max]], 4 => ['decimal' => [0, $max]], 5 => ['decimal' => [0, $max]], 6 => ['decimal' => [0, $max]]],
                'instructions_title' => 'OT & shift hours for '.$d->format('F Y'),
                'instructions' => [
                    'One row per employee. Keep the Emp ID column: it is used to match the employee (the Name is used if the ID is empty).',
                    '2nd Shift and 3rd Shift = shift hours in the month. Normal OT and Spl OT = overtime hours. Enter numbers only (0 – '.$max.').',
                    'Leave a row empty (or delete it) if the employee has no OT. Do not rename the header row.',
                    'Upload via Import Data → Monthly OT sheet and choose '.$d->format('F Y').'. You will see a validation preview before anything is saved.',
                    'Your existing OT workbook (one sheet per month with Name / 2nd Shift / 3rd Shift / Normal OT columns) can also be uploaded directly.',
                ],
            ]);
        }
        if ($type === 'leave') {
            $year = (int) ($request->year ?: now()->year);
            $emps = Employee::whereIn('status', ['active', 'exited'])->orderBy('emp_code')->get();
            $rows = $emps->map(fn ($e) => array_merge([$e->emp_code, $e->name, $e->date_of_joining?->format('d-m-Y')],
                array_map(fn ($u) => $u['total'] ?: null, array_values($leave->monthlyUsage($e, $year)))));

            return xlsx_download("leave-register-template-$year.xlsx", array_merge(['Emp ID', 'Name', 'Date of Joining'], array_map(fn ($m) => month_name($m, true), range(1, 12))), $rows, [
                'title' => (string) $year,
                'spare_rows' => 50,
                'validations' => collect(range(3, 14))->mapWithKeys(fn ($c) => [$c => ['decimal' => [0, 31]]])->all(),
                'instructions_title' => "Leave register $year",
                'instructions' => [
                    'One row per employee, one column per month: the number of leave days taken in that month (half days as 0.5).',
                    'Keep the Emp ID column — it is used to match the employee. Numbers only, 0 – 31.',
                    'An empty cell leaves that month unchanged in the app; 0 clears a previously imported value.',
                    'Months whose payroll is already finalized are skipped automatically.',
                    "Upload via Import Data → Yearly leave register and choose $year. You will see a validation preview before anything is saved.",
                ],
            ]);
        }
        if ($type === 'employees') {
            return redirect()->route('employees.export', ['status' => 'all']);
        }
        abort(404);
    }
}
