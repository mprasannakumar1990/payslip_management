<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ElLedger;
use App\Models\Employee;
use App\Models\LeaveAdjustment;
use App\Models\LeaveAllocation;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\Setting;
use App\Services\LeaveService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Leave balances, yearly register, EL accrual summary and manual adjustments. */
class LeaveController extends Controller
{
    public function __construct(private LeaveService $leave)
    {
    }

    private function employeesFor(int $year)
    {
        return Employee::onRollsIn($year)->orderBy('emp_code')->get();
    }

    /** Yearly leave allocation: CL / SL / EL granted to each employee for the year. */
    public function allocations(Request $request)
    {
        $year = (int) ($request->input('year') ?: active_year());
        $types = LeaveType::ordered()->where('is_paid', true)->get();
        $allocs = LeaveAllocation::where('year', $year)->get()->groupBy('employee_id');
        $rows = $this->employeesFor($year)
            ->when($request->q, fn ($c, $s) => $c->filter(fn ($e) => stripos($e->name.' '.$e->emp_code, $s) !== false))
            ->map(function ($e) use ($allocs, $types, $year) {
                $mine = ($allocs[$e->id] ?? collect())->keyBy('leave_type_id');
                $b = $this->leave->balances($e, $year);

                return [
                    'employee' => $e,
                    'alloc' => $types->mapWithKeys(fn ($t) => [$t->id => $mine[$t->id] ?? null]),
                    'default' => $types->mapWithKeys(fn ($t) => [$t->id => $this->leave->defaultQuota($e, $t, $year)]),
                    'total' => $mine->sum('days'),
                    'used' => $b['used'],
                    'balance' => $b['balance'],
                    'manual' => $mine->contains('is_manual', true),
                    'generated' => $mine->isNotEmpty(),
                ];
            })->values();

        if ($request->export) {
            $header = array_merge(['Emp ID', 'Name', 'Date of Joining', 'Date of Exit'], $types->pluck('code')->all(), ['Total', 'Used', 'Balance', 'Edited']);
            $data = $rows->map(fn ($r) => array_merge(
                [$r['employee']->emp_code, $r['employee']->name, $r['employee']->date_of_joining?->format('d-m-Y'), $r['employee']->date_of_exit?->format('d-m-Y')],
                $types->map(fn ($t) => $r['alloc'][$t->id]?->days ?? '')->all(),
                [$r['total'], $r['used'], $r['balance'], $r['manual'] ? 'Yes' : '']
            ));

            return xlsx_download("leave-allocation-$year.xlsx", $header, $data, ['title' => "Allocation $year"]);
        }

        return view('leave.allocations', [
            'rows' => $rows, 'types' => $types, 'year' => $year,
            'missing' => $rows->where('generated', false)->count(),
        ]);
    }

    public function generateAllocations(Request $request)
    {
        $data = $request->validate(['year' => ['required', 'integer', 'min:2000', 'max:'.(now()->year + 1)]]);
        $year = (int) $data['year'];
        $s = $this->leave->generateYear($year);
        ActivityLog::record('leave', "Generated yearly leave for $year: {$s['created']} new, {$s['updated']} updated ({$s['employees']} employees)");
        $msg = $s['created'] + $s['updated'] === 0
            ? "Leave for $year is already up to date for every employee."
            : "Leave for $year generated: {$s['employees']} employee(s), {$s['created']} new and {$s['updated']} updated entries.";

        return redirect()->route('leave.allocations', ['year' => $year])->with('success', $msg);
    }

    public function updateAllocation(Request $request, Employee $employee)
    {
        $types = LeaveType::where('is_paid', true)->get();
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
            'days' => ['array'],
            'days.*' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'reset' => ['nullable', 'boolean'],
        ]);
        $year = (int) $data['year'];
        if ($request->boolean('reset')) {
            LeaveAllocation::where('employee_id', $employee->id)->where('year', $year)->delete();
            $this->leave->generateYear($year, $employee);
            ActivityLog::record('leave', "Reset yearly leave $year for {$employee->label()} to the standard entitlement");

            return back()->with('success', "Leave for {$employee->name} ($year) reset to the standard entitlement.");
        }
        $changes = [];
        foreach ($types as $t) {
            $v = $data['days'][$t->id] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            $row = LeaveAllocation::firstOrNew(['employee_id' => $employee->id, 'year' => $year, 'leave_type_id' => $t->id]);
            if ($row->exists && abs($row->days - (float) $v) < 0.001) {
                continue;
            }
            $changes[] = "$t->code ".num_clean($row->days ?? 0).' → '.num_clean($v);
            $row->fill(['days' => (float) $v, 'is_manual' => true, 'created_by' => $row->created_by ?? auth()->id()])->save();
        }
        if ($changes) {
            ActivityLog::record('leave', "Edited yearly leave $year for {$employee->label()}: ".implode(', ', $changes));
        }

        return back()->with('success', $changes ? "Leave for {$employee->name} ($year) updated." : 'No changes.');
    }

    public function balances(Request $request)
    {
        $wp = working_period();
        $year = (int) ($request->input('year') ?: active_year());
        $month = (int) ($request->input('month') ?: ($year == $wp->year ? $wp->month : 12));
        $rows = $this->employeesFor($year)->map(fn ($e) => ['employee' => $e, 'b' => $this->leave->balances($e, $year, $month)]);
        $types = LeaveType::ordered()->where('is_paid', true)->get();

        if ($request->export) {
            $header = ['Emp ID', 'Name'];
            foreach ($types as $t) {
                array_push($header, "$t->code Quota", "$t->code Used", "$t->code Balance");
            }
            array_push($header, 'Total Entitled', 'Total Used', 'Balance', 'Excess (LOP)', 'Explicit LOP');
            $data = $rows->map(function ($r) use ($types) {
                $row = [$r['employee']->emp_code, $r['employee']->name];
                foreach ($types as $t) {
                    $x = collect($r['b']['types'])->firstWhere('code', $t->code);
                    array_push($row, $x['quota'] ?? 0, $x['used'] ?? 0, $x['balance'] ?? 0);
                }
                array_push($row, $r['b']['pool'], $r['b']['used'], $r['b']['balance'], $r['b']['excess'], $r['b']['lop']);

                return $row;
            });

            return xlsx_download("leave-balances-$year-$month.xlsx", $header, $data, ['title' => "Balances $year"]);
        }

        return view('leave.balances', compact('rows', 'types', 'year', 'month'));
    }

    /** Yearly register in the same shape as the "Staff's Earn Leave Balance" sheet. */
    public function register(Request $request)
    {
        $year = (int) ($request->input('year') ?: active_year());
        $pool = Setting::num('annual_leave_pool');
        $rows = $this->employeesFor($year)->map(function ($e) use ($year) {
            $usage = $this->leave->monthlyUsage($e, $year);
            $total = array_sum(array_column($usage, 'paid'));
            $entitled = $this->leave->pool($e, $year);

            return [
                'employee' => $e,
                'months' => array_map(fn ($u) => $u['total'], $usage),
                'lop' => array_sum(array_column($usage, 'lop')),
                'total' => $total,
                'entitled' => $entitled,
                'balance' => round($entitled - $total, 2),
                'el_credit' => $this->leave->elCreditComputed($e, $year),
            ];
        });

        if ($request->export) {
            $header = array_merge(['Emp ID', 'Name', 'Date of Joining'], array_map(fn ($m) => month_name($m, true), range(1, 12)),
                ['Total Leave', 'CL/SL/EL', 'Total Less CL/SL/EL', 'LOP Leave', 'EL Credit']);
            $data = $rows->map(fn ($r) => array_merge(
                [$r['employee']->emp_code, $r['employee']->name, $r['employee']->date_of_joining?->format('d-m-Y')],
                array_values($r['months']), [$r['total'], $r['entitled'], $r['balance'], $r['lop'], $r['el_credit']]
            ));

            return xlsx_download("leave-register-$year.xlsx", $header, $data, ['title' => (string) $year]);
        }

        return view('leave.register', compact('rows', 'year', 'pool'));
    }

    /** Year-wise EL credited and encashment value, like the "2009 to 2020" sheet. */
    public function elSummary(Request $request)
    {
        $toYear = (int) $request->input('to', active_year());
        $fromYear = (int) $request->input('from', max(2009, $toYear - 9));
        $krw = Setting::num('krw_rate');
        $rows = Employee::whereIn('status', ['active', 'exited'])->orderBy('emp_code')->get()->map(function ($e) use ($fromYear, $toYear) {
            $history = collect($this->leave->elHistory($e, $toYear))->keyBy('year');
            $total = $history->sum('days');
            $perDay = round($e->perDay());

            return [
                'employee' => $e,
                'years' => collect(range($fromYear, $toYear))->mapWithKeys(fn ($y) => [$y => $history[$y] ?? null]),
                'earlier' => $history->filter(fn ($h, $y) => $y < $fromYear)->sum('days'),
                'total' => round($total, 2),
                'per_day' => $perDay,
                'amount' => round($total * $perDay, 2),
            ];
        });

        if ($request->export) {
            $header = array_merge(['Emp ID', 'Name', 'Date of Joining', 'Before '.$fromYear], range($fromYear, $toYear), ['Total EL', 'Per Day', 'EL Amount', 'KRW']);
            $data = $rows->map(fn ($r) => array_merge(
                [$r['employee']->emp_code, $r['employee']->name, $r['employee']->date_of_joining?->format('d-m-Y'), $r['earlier']],
                $r['years']->map(fn ($h) => $h['days'] ?? '')->values()->all(),
                [$r['total'], $r['per_day'], $r['amount'], round($r['amount'] * $krw, 2)]
            ));

            return xlsx_download("el-summary-$fromYear-$toYear.xlsx", $header, $data, ['title' => "EL $fromYear-$toYear"]);
        }

        return view('leave.el-summary', compact('rows', 'fromYear', 'toYear', 'krw'));
    }

    public function saveElLedger(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:1990', 'max:2100'],
            'days' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        if ($data['days'] === null) {
            ElLedger::where('employee_id', $employee->id)->where('year', $data['year'])->delete();
            $msg = "EL for {$data['year']} reset to computed value.";
        } else {
            ElLedger::updateOrCreate(['employee_id' => $employee->id, 'year' => $data['year']], ['days' => $data['days'], 'note' => $data['note']]);
            $msg = "EL for {$data['year']} set to {$data['days']} days.";
        }
        ActivityLog::record('leave', "EL ledger {$employee->label()}: $msg");

        return back()->with('success', $msg);
    }

    /** Year end: freeze each employee's computed EL credit for the year in the ledger (manual entries are kept). */
    public function closeYear(Request $request)
    {
        $data = $request->validate(['year' => ['required', 'integer', 'min:2000', 'max:'.now()->year]]);
        $year = (int) $data['year'];
        $n = 0;
        foreach ($this->employeesFor($year) as $e) {
            if (ElLedger::where('employee_id', $e->id)->where('year', $year)->exists()) {
                continue;
            }
            ElLedger::create(['employee_id' => $e->id, 'year' => $year, 'days' => $this->leave->elCreditComputed($e, $year),
                'note' => 'Year closed '.now()->format('d-m-Y')]);
            $n++;
        }
        ActivityLog::record('leave', "Closed leave year $year ($n EL entries saved)");

        return back()->with('success', "Leave year $year closed: EL credit saved for $n employees.");
    }

    public function adjustments(Request $request)
    {
        $year = (int) ($request->input('year') ?: active_year());
        $items = LeaveAdjustment::with(['employee', 'leaveType'])->where('year', $year)
            ->when($request->employee_id, fn ($q, $e) => $q->where('employee_id', $e))
            ->when($request->month, fn ($q, $m) => $q->where('month', $m))
            ->orderBy('month')->orderBy('employee_id')->paginate(40)->withQueryString();

        return view('leave.adjustments', [
            'items' => $items,
            'year' => $year,
            'employees' => Employee::orderBy('name')->get(['id', 'emp_code', 'name']),
            'types' => LeaveType::ordered()->get(),
        ]);
    }

    public function storeAdjustment(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_type_id' => ['nullable', 'exists:leave_types,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'days' => ['required', 'numeric', 'min:0.5', 'max:31'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        if (PayrollRun::isLocked($data['year'], $data['month'])) {
            throw ValidationException::withMessages(['month' => 'Payroll for this month is finalized.']);
        }
        $adj = LeaveAdjustment::create($data + ['created_by' => auth()->id()]);
        ActivityLog::record('leave', "Leave adjustment {$adj->days} d for {$adj->employee->label()} ({$adj->month}/{$adj->year})");

        return back()->with('success', 'Leave usage recorded.');
    }

    public function destroyAdjustment(LeaveAdjustment $adjustment)
    {
        abort_if(PayrollRun::isLocked($adjustment->year, $adjustment->month), 422, 'Payroll for this month is finalized.');
        $adjustment->delete();
        ActivityLog::record('leave', "Deleted leave adjustment #{$adjustment->id}");

        return back()->with('success', 'Entry deleted.');
    }
}
