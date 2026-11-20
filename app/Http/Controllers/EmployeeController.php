<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AllowanceType;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\LeaveRequest;
use App\Models\OtEntry;
use App\Models\Setting;
use App\Services\LeaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Employee (user) registration and master data. */
class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $employees = $this->filtered($request)->paginate(25)->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'groups' => Setting::otGroups(),
            'year' => $this->year($request),
            'defaultStatus' => $this->defaultStatus($request),
        ]);
    }

    private function year(Request $request): int
    {
        return (int) ($request->input('year') ?: active_year());
    }

    /** Current year: active staff. Past years: everybody who was on the rolls that year (some have exited since). */
    private function defaultStatus(Request $request): string
    {
        return $this->year($request) >= (int) now()->year ? 'active' : 'all';
    }

    private function filtered(Request $request)
    {
        $year = $this->year($request);

        return Employee::query()
            // only staff on the rolls during the selected year
            ->where(fn ($w) => $w->whereNull('date_of_joining')->orWhereYear('date_of_joining', '<=', $year))
            ->where(fn ($w) => $w->whereNull('date_of_exit')->orWhereYear('date_of_exit', '>=', $year))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")
                ->orWhere('emp_code', 'like', "%$s%")->orWhere('designation', 'like', "%$s%")))
            ->when($request->group, fn ($q, $g) => $q->where('ot_group', $g))
            ->when($request->email === 'missing', fn ($q) => $q->where(fn ($w) => $w->whereNull('email')->orWhere('email', '')))
            ->when($request->email === 'has', fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''))
            ->when($request->input('status', $this->defaultStatus($request)), fn ($q, $s) => $s === 'all' ? $q : $q->where('status', $s))
            ->orderBy('emp_code');
    }

    public function export(Request $request)
    {
        $types = AllowanceType::ordered()->get();
        $rows = $this->filtered($request)->with('allowances')->get()->map(function (Employee $e) use ($types) {
            $row = [$e->emp_code, $e->name, $e->designation, $e->grade, $e->ot_group, $e->date_of_joining?->format('d-m-Y'),
                $e->uan_no, $e->esic_no, $e->email, $e->phone, $e->basic_salary, $e->manager_special, $e->factory_special, $e->monthly_tax,
                $e->pf_applicable ? 'Yes' : 'No', $e->esi_applicable ? 'Yes' : 'No', $e->status];
            foreach ($types as $t) {
                $row[] = (float) optional($e->allowances->firstWhere('allowance_type_id', $t->id))->amount;
            }

            return $row;
        });
        $header = array_merge(['Emp ID', 'Name', 'Designation', 'Grade', 'Plant / Group', 'Joining Date', 'EPF / UAN', 'ESIC No',
            'Email', 'Phone', 'Basic', 'Manager Special', 'Factory Special', 'Monthly Tax', 'PF', 'ESI', 'Status'], $types->pluck('name')->all());

        $basicCol = array_search('Basic', $header, true);

        return xlsx_download('employees-'.now()->format('Ymd').'.xlsx', $header, $rows, [
            'title' => 'Employees',
            'spare_rows' => 200,
            'validations' => [
                4 => ['list' => Setting::otGroups()],
                $basicCol => ['decimal' => [0, 9999999]], $basicCol + 1 => ['decimal' => [0, 9999999]],
                $basicCol + 2 => ['decimal' => [0, 9999999]], $basicCol + 3 => ['decimal' => [0, 9999999]],
                $basicCol + 4 => ['list' => ['Yes', 'No']], $basicCol + 5 => ['list' => ['Yes', 'No']],
                $basicCol + 6 => ['list' => ['active', 'inactive', 'exited']],
            ] + collect($types)->keys()->mapWithKeys(fn ($i) => [$basicCol + 7 + $i => ['decimal' => [0, 9999999]]])->all(),
            'instructions_title' => 'Employee list (also the import template)',
            'instructions' => [
                'One row per employee. "Emp ID" identifies the employee: an existing ID updates that employee, a new ID creates a new employee.',
                'Required for new employees: Emp ID, Name, Joining Date (dd-mm-yyyy) and Basic.',
                'Plant / Group, PF, ESI and Status have drop-down lists. Amount columns accept numbers only.',
                'Allowance columns (Food, Transport, Increment …) are monthly amounts. Leave 0 or empty to remove an allowance.',
                'To record an exit, fill the employee\'s exit date in the app (or set Status to exited).',
                'Email is used to send payslips. Keep the header row unchanged. Upload via Import Data → Employees; you will see a preview before anything is saved.',
            ],
        ]);
    }

    public function create()
    {
        $next = 'TSA'.str_pad((string) ((int) preg_replace('/\D/', '', (string) Employee::withTrashed()->where('emp_code', 'like', 'TSA%')->max('emp_code')) + 1), 3, '0', STR_PAD_LEFT);

        return view('employees.form', [
            'employee' => new Employee([
                'emp_code' => $next, 'status' => 'active', 'ot_multiplier' => Setting::num('ot_multiplier'),
                'pf_applicable' => true, 'esi_applicable' => true, 'ot_eligible' => true, 'attendance_bonus_applicable' => true,
                'grade' => 'Emp',
            ]),
            'allowanceTypes' => AllowanceType::ordered()->where('is_active', true)->get(),
            'amounts' => [],
            'groups' => Setting::otGroups(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $employee = DB::transaction(function () use ($data, $request) {
            $employee = Employee::create($data + ['created_by' => auth()->id()]);
            $this->saveAllowances($employee, $request->input('allowances', []));

            return $employee;
        });
        ActivityLog::record('employee', "Registered employee {$employee->label()}");

        return redirect()->route('employees.show', $employee)->with('success', 'Employee registered.');
    }

    public function show(Request $request, Employee $employee, LeaveService $leave)
    {
        $year = (int) ($request->input('year') ?: active_year());
        $employee->load('allowances.type');

        return view('employees.show', [
            'employee' => $employee,
            'year' => $year,
            'balances' => $leave->balances($employee, $year),
            'usage' => $leave->monthlyUsage($employee, $year),
            'elHistory' => $leave->elHistory($employee),
            'leaves' => LeaveRequest::with('leaveType')->where('employee_id', $employee->id)
                ->whereDate('from_date', '<=', "$year-12-31")->whereDate('to_date', '>=', "$year-01-01")->latest('from_date')->get(),
            'otEntries' => OtEntry::where('employee_id', $employee->id)->where('year', $year)->orderByDesc('month')->get(),
            'payslips' => $employee->payslips()->with('run')->whereHas('run', fn ($q) => $q->where('year', $year))->latest('id')->get(),
            'settlements' => $employee->settlements()->latest()->get(),
        ]);
    }

    /** Employee photo (kept outside the public folder; only signed-in admins can see it). */
    public function photo(Employee $employee)
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($employee->photo_path && $disk->exists($employee->photo_path), 404);

        return response()->file($disk->path($employee->photo_path), ['Cache-Control' => 'private, max-age=604800']);
    }

    public function edit(Employee $employee)
    {
        return view('employees.form', [
            'employee' => $employee,
            'allowanceTypes' => AllowanceType::ordered()->get(),
            'amounts' => $employee->allowances()->pluck('amount', 'allowance_type_id')->all(),
            'groups' => Setting::otGroups(),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $this->validated($request, $employee);
        DB::transaction(function () use ($employee, $data, $request) {
            $employee->update($data);
            $this->saveAllowances($employee, $request->input('allowances', []));
        });
        ActivityLog::record('employee', "Updated employee {$employee->label()}");

        return redirect()->route('employees.show', $employee)->with('success', 'Employee updated.');
    }

    public function destroy(Employee $employee)
    {
        if ($employee->payslips()->exists()) {
            $employee->update(['status' => 'inactive']);
            ActivityLog::record('employee', "Deactivated employee {$employee->label()} (has payslips)");

            return redirect()->route('employees.index')->with('success', 'Employee has payslip history, so it was marked inactive instead of deleted.');
        }
        $employee->delete();
        ActivityLog::record('employee', "Deleted employee {$employee->label()}");

        return redirect()->route('employees.index')->with('success', 'Employee deleted.');
    }

    private function saveAllowances(Employee $employee, array $amounts): void
    {
        foreach ($amounts as $typeId => $amount) {
            $amount = (float) $amount;
            if ($amount > 0) {
                EmployeeAllowance::updateOrCreate(['employee_id' => $employee->id, 'allowance_type_id' => (int) $typeId], ['amount' => $amount]);
            } else {
                EmployeeAllowance::where('employee_id', $employee->id)->where('allowance_type_id', (int) $typeId)->delete();
            }
        }
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:99999999'];
        $data = $request->validate([
            'emp_code' => ['required', 'string', 'max:30', Rule::unique('employees')->ignore($employee?->id)],
            'name' => ['required', 'string', 'max:150'],
            'name_aliases' => ['nullable', 'string', 'max:500'],
            'designation' => ['nullable', 'string', 'max:150'],
            'grade' => ['nullable', 'string', 'max:30'],
            'ot_group' => ['nullable', 'string', 'max:50'],
            'date_of_joining' => ['required', 'date'],
            'date_of_exit' => ['nullable', 'date', 'after_or_equal:date_of_joining'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Other'])],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'uan_no' => ['nullable', 'string', 'max:30'],
            'esic_no' => ['nullable', 'string', 'max:30'],
            'pan_no' => ['nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_no' => ['nullable', 'string', 'max:40'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'basic_salary' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'manager_special' => $money,
            'factory_special' => $money,
            'monthly_tax' => $money,
            'ot_multiplier' => ['required', 'numeric', 'min:0', 'max:5'],
            'status' => ['required', Rule::in(['active', 'inactive', 'exited'])],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'allowances.*' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_photo' => ['nullable', 'boolean'],
        ]);
        unset($data['allowances'], $data['photo'], $data['remove_photo']);
        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('employee-photos', 'local');
        } elseif ($request->boolean('remove_photo')) {
            $data['photo_path'] = null;
        }
        if (array_key_exists('photo_path', $data) && $employee?->photo_path && $employee->photo_path !== $data['photo_path']) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($employee->photo_path);
        }
        foreach (['manager_special', 'factory_special', 'monthly_tax'] as $f) {
            $data[$f] = (float) ($data[$f] ?? 0);
        }
        foreach (['pf_applicable', 'esi_applicable', 'ot_eligible', 'attendance_bonus_applicable'] as $f) {
            $data[$f] = $request->boolean($f);
        }

        return $data;
    }
}
