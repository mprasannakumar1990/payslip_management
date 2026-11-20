<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\OtEntry;
use App\Models\PayrollRun;
use App\Models\Setting;
use App\Services\LeaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Monthly OT & shift-allowance hours, entered plant-wise as in the OT sheet. */
class OtController extends Controller
{
    public function __construct(private LeaveService $leave)
    {
    }

    private function period(Request $request): array
    {
        $ym = $request->input('period') ?: working_period()->format('Y-m');
        $d = Carbon::createFromFormat('Y-m-d', $ym.'-01');

        return [(int) $d->year, (int) $d->month];
    }

    private function employeesFor(int $year, int $month)
    {
        $start = Carbon::create($year, $month, 1);

        return Employee::query()->where('ot_eligible', true)->whereIn('status', ['active', 'exited'])
            ->where(fn ($q) => $q->whereNull('date_of_joining')->orWhereDate('date_of_joining', '<=', $start->copy()->endOfMonth()))
            ->where(fn ($q) => $q->whereNull('date_of_exit')->orWhereDate('date_of_exit', '>=', $start))
            ->orderBy('emp_code')->get();
    }

    /** Group rows by plant in the configured order. */
    private function grouped($employees): array
    {
        $order = Setting::otGroups();
        $groups = [];
        foreach ($order as $g) {
            $groups[$g] = [];
        }
        foreach ($employees as $e) {
            $groups[$e->ot_group ?: 'OTHERS'][] = $e;
        }

        return array_filter($groups);
    }

    public function index(Request $request)
    {
        [$year, $month] = $this->period($request);
        $employees = $this->employeesFor($year, $month);
        $entries = OtEntry::where('year', $year)->where('month', $month)->get()->keyBy('employee_id');
        $leaveDays = $employees->mapWithKeys(fn ($e) => [$e->id => $this->leave->monthlyUsage($e, $year)[$month]['total']]);

        return view('ot.index', [
            'year' => $year,
            'month' => $month,
            'groups' => $this->grouped($employees),
            'entries' => $entries,
            'leaveDays' => $leaveDays,
            'locked' => PayrollRun::isLocked($year, $month),
        ]);
    }

    public function save(Request $request)
    {
        [$year, $month] = $this->period($request);
        abort_if(PayrollRun::isLocked($year, $month), 422, 'Payroll for this month is finalized; OT cannot be changed.');
        $request->validate([
            'rows' => ['array'],
            'rows.*.second' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'rows.*.third' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'rows.*.normal' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'rows.*.spl' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'rows.*.remarks' => ['nullable', 'string', 'max:200'],
        ]);
        $approve = $request->input('action') === 'approve';
        $valid = Employee::whereIn('id', array_keys($request->input('rows', [])))->pluck('id')->flip();
        $saved = 0;
        DB::transaction(function () use ($request, $year, $month, $approve, $valid, &$saved) {
            foreach ($request->input('rows', []) as $empId => $r) {
                if (! isset($valid[$empId])) {
                    continue;
                }
                $vals = [
                    'second_shift_hours' => (float) ($r['second'] ?? 0),
                    'third_shift_hours' => (float) ($r['third'] ?? 0),
                    'normal_ot_hours' => (float) ($r['normal'] ?? 0),
                    'spl_ot_hours' => (float) ($r['spl'] ?? 0),
                    'remarks' => $r['remarks'] ?? null,
                ];
                $entry = OtEntry::where(['employee_id' => $empId, 'year' => $year, 'month' => $month])->first();
                $empty = array_sum(array_slice($vals, 0, 4)) == 0 && empty($vals['remarks']);
                if (! $entry && $empty) {
                    continue;
                }
                $entry ??= new OtEntry(['employee_id' => $empId, 'year' => $year, 'month' => $month, 'created_by' => auth()->id()]);
                $changed = ! $entry->exists
                    || collect(array_slice($vals, 0, 4, true))->contains(fn ($v, $k) => (float) $entry->$k != (float) $v)
                    || (string) $entry->remarks !== (string) $vals['remarks'];
                $entry->fill($vals);
                if ($approve) {
                    if ($entry->status !== 'approved' || $changed) {
                        $entry->fill(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
                    }
                } elseif ($changed) {
                    $entry->fill(['status' => 'pending', 'approved_by' => null, 'approved_at' => null]);
                }
                $entry->save();
                $saved++;
            }
        });
        $label = Carbon::create($year, $month)->format('M Y');
        ActivityLog::record('ot', "Saved OT sheet for $label ($saved rows".($approve ? ', approved' : '').')');

        return redirect()->route('ot.index', ['period' => sprintf('%04d-%02d', $year, $month)])
            ->with('success', "OT for $label saved".($approve ? ' and approved' : '').'.');
    }

    public function approveMonth(Request $request)
    {
        [$year, $month] = $this->period($request);
        abort_if(PayrollRun::isLocked($year, $month), 422, 'Payroll for this month is finalized.');
        $n = OtEntry::where('year', $year)->where('month', $month)->where('status', 'pending')
            ->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        ActivityLog::record('ot', "Approved $n pending OT entries for $month/$year");

        return back()->with('success', "$n OT entries approved.");
    }

    public function setStatus(Request $request, OtEntry $entry)
    {
        abort_if(PayrollRun::isLocked($entry->year, $entry->month), 422, 'Payroll for this month is finalized.');
        $data = $request->validate(['status' => ['required', 'in:approved,rejected,pending']]);
        $entry->update([
            'status' => $data['status'],
            'approved_by' => $data['status'] === 'pending' ? null : auth()->id(),
            'approved_at' => $data['status'] === 'pending' ? null : now(),
        ]);
        ActivityLog::record('ot', ucfirst($data['status'])." OT of {$entry->employee->label()} for {$entry->month}/{$entry->year}");

        return back()->with('success', 'OT entry '.$data['status'].'.');
    }

    /** Plant-wise report with sub-totals and summary, as in the OT sheet. */
    public function report(Request $request)
    {
        [$year, $month] = $this->period($request);
        $rate2 = Setting::num('second_shift_rate');
        $rate3 = Setting::num('third_shift_rate');
        $entries = OtEntry::with('employee')->where('year', $year)->where('month', $month)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->get()->sortBy(fn ($e) => $e->employee->emp_code);
        $groups = [];
        foreach (Setting::otGroups() as $g) {
            $groups[$g] = collect();
        }
        foreach ($entries as $e) {
            $g = $e->employee->ot_group ?: 'OTHERS';
            $groups[$g] = ($groups[$g] ?? collect())->push($e);
        }
        $groups = array_filter($groups, fn ($c) => $c->isNotEmpty());
        $sum = fn ($c) => [
            'second' => $c->sum('second_shift_hours'), 'third' => $c->sum('third_shift_hours'),
            'normal' => $c->sum('normal_ot_hours'), 'spl' => $c->sum('spl_ot_hours'),
            'total' => $c->sum('normal_ot_hours') + $c->sum('spl_ot_hours'),
        ];
        $summary = collect($groups)->map($sum);

        if ($request->export) {
            $rows = [];
            $i = 1;
            foreach ($groups as $g => $list) {
                $rows[] = ["$g OT Calculation"];
                foreach ($list as $e) {
                    $rows[] = [$i++, $e->employee->emp_code, $e->employee->name, $e->second_shift_hours, $e->third_shift_hours,
                        $e->normal_ot_hours, $e->spl_ot_hours, $e->totalOt(), $e->remarks, $e->status];
                }
                $s = $summary[$g];
                $rows[] = ['Total', '', '', $s['second'], $s['third'], $s['normal'], $s['spl'], $s['total']];
            }
            $rows[] = [];
            $rows[] = ['Summary'];
            foreach ($summary as $g => $s) {
                $rows[] = ['', $g, '', $s['second'], $s['third'], $s['normal'], $s['spl'], $s['total']];
            }

            return xlsx_download(sprintf('ot-%04d-%02d.xlsx', $year, $month), ['Sl No', 'Emp ID', 'Name', '2nd Shift', '3rd Shift', 'Normal OT', 'Spl OT', 'Total OT', 'Remarks', 'Status'], $rows,
                ['title' => Carbon::create($year, $month)->format('M Y'), 'autofilter' => false, 'highlight' => ['/OT Calculation$/' => 'E0E7FF', '/^Total$/' => 'F1F5F9', '/^Summary$/' => 'FDE68A']]);
        }

        return view('ot.report', [
            'year' => $year, 'month' => $month, 'groups' => $groups, 'summary' => $summary,
            'grand' => $sum($entries), 'rate2' => $rate2, 'rate3' => $rate3,
        ]);
    }

    /** OT history for a period range, one row per employee and month columns. */
    public function history(Request $request)
    {
        $from = Carbon::createFromFormat('Y-m-d', $request->input('from', now()->subMonths(5)->format('Y-m')).'-01');
        $to = Carbon::createFromFormat('Y-m-d', $request->input('to', now()->format('Y-m')).'-01');
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInMonths($to) > 23) {
            $from = $to->copy()->subMonths(23);
        }
        $months = [];
        for ($d = $from->copy(); $d->lte($to); $d->addMonth()) {
            $months[] = [$d->year, $d->month];
        }
        $entries = OtEntry::with('employee')->where('status', 'approved')
            ->whereRaw('(year * 100 + month) between ? and ?', [$from->year * 100 + $from->month, $to->year * 100 + $to->month])
            ->when($request->employee_id, fn ($q, $e) => $q->where('employee_id', $e))
            ->get()->groupBy('employee_id');

        return view('ot.history', [
            'months' => $months, 'entries' => $entries, 'from' => $from, 'to' => $to,
            'employees' => Employee::orderBy('name')->get(['id', 'emp_code', 'name']),
        ]);
    }
}
