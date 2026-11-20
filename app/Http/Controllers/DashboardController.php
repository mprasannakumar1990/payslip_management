<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\LeaveAdjustment;
use App\Models\LeaveRequest;
use App\Models\OtEntry;
use App\Models\PayrollRun;
use App\Models\Settlement;
use App\Services\LeaveService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

/** Year dashboard: every figure is for the year selected in the top bar. */
class DashboardController extends Controller
{
    public function index(Request $request, WorkflowService $workflow, LeaveService $leave)
    {
        $year = (int) ($request->input('year') ?: active_year());
        $period = year_period($year);
        $start = "$year-01-01";
        $end = "$year-12-31";

        // ---- people
        $onRolls = Employee::onRollsIn($year)->get();
        $joiners = Employee::whereYear('date_of_joining', $year)->orderBy('date_of_joining')->get();
        $leavers = Employee::whereYear('date_of_exit', $year)->orderBy('date_of_exit')->get();

        // ---- payroll by month
        $runs = PayrollRun::where('year', $year)->withSum('payslips', 'total_amount')->withSum('payslips', 'net_salary')
            ->withCount('payslips')->get()->keyBy('month');
        $payroll = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => [
            'total' => (float) ($runs[$m]->payslips_sum_total_amount ?? 0),
            'status' => $runs[$m]->status ?? null,
            'run' => $runs[$m] ?? null,
        ]]);

        // ---- OT by month (approved hours)
        $ot = OtEntry::where('year', $year)->where('status', 'approved')
            ->selectRaw('month, sum(normal_ot_hours + spl_ot_hours) ot, sum(second_shift_hours + third_shift_hours) shift')
            ->groupBy('month')->get()->keyBy('month');
        $otByMonth = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => (float) ($ot[$m]->ot ?? 0)]);
        $topOt = OtEntry::with('employee')->where('year', $year)->where('status', 'approved')
            ->selectRaw('employee_id, sum(normal_ot_hours + spl_ot_hours) hours')->groupBy('employee_id')
            ->orderByDesc('hours')->take(5)->get();

        // ---- leave by month (approved requests split across months + monthly register entries)
        $leaveByMonth = array_fill(1, 12, 0.0);
        LeaveRequest::where('status', 'approved')->whereDate('from_date', '<=', $end)->whereDate('to_date', '>=', $start)->get()
            ->each(function ($r) use ($leave, $year, &$leaveByMonth) {
                foreach ($leave->splitByMonth($r) as $ym => $days) {
                    [$y, $m] = array_map('intval', explode('-', $ym));
                    if ($y === $year) {
                        $leaveByMonth[$m] += $days;
                    }
                }
            });
        foreach (LeaveAdjustment::where('year', $year)->selectRaw('month, sum(days) d')->groupBy('month')->pluck('d', 'month') as $m => $d) {
            $leaveByMonth[$m] += (float) $d;
        }

        // ---- yearly leave allocation status
        $alloc = ['employees' => $onRolls->count(), 'generated' => 0, 'allocated' => 0.0, 'used' => 0.0, 'balance' => 0.0, 'lop' => 0.0];
        $generatedIds = \App\Models\LeaveAllocation::where('year', $year)->distinct()->pluck('employee_id')->flip();
        foreach ($onRolls as $e) {
            $b = $leave->balances($e, $year);
            $alloc['generated'] += isset($generatedIds[$e->id]) ? 1 : 0;
            $alloc['allocated'] += $b['pool'];
            $alloc['used'] += $b['used'];
            $alloc['balance'] += $b['balance'];
            $alloc['lop'] += $b['excess'] + $b['lop'];
        }

        $leaveInYear = fn ($q) => $q->whereDate('from_date', '<=', $end)->whereDate('to_date', '>=', $start);
        $isWorkingYear = $year === (int) working_period()->year;

        return view('dashboard', [
            'year' => $year,
            'period' => $period,
            'isWorkingYear' => $isWorkingYear,
            'workflow' => $workflow->status($period->year, $period->month),
            'stats' => [
                'on_rolls' => $onRolls->count(),
                'active' => $onRolls->where('status', 'active')->count(),
                'joiners' => $joiners->count(),
                'leavers' => $leavers->count(),
                'payroll_total' => $payroll->sum('total'),
                'payroll_months' => $runs->count(),
                'finalized' => $runs->where('status', 'finalized')->count(),
                'payslips' => $runs->sum('payslips_count'),
                'ot_hours' => $otByMonth->sum(),
                'ot_pending' => OtEntry::where('year', $year)->where('status', 'pending')->count(),
                'leave_days' => array_sum($leaveByMonth),
                'pending_leave' => LeaveRequest::where($leaveInYear)->where('status', 'pending')->count(),
                'on_leave_today' => $isWorkingYear || $year === (int) now()->year
                    ? LeaveRequest::where('status', 'approved')->whereDate('from_date', '<=', now())->whereDate('to_date', '>=', now())->count() : null,
                'settlements' => Settlement::whereYear('settlement_date', $year)->sum('total_amount'),
            ],
            'payroll' => $payroll,
            'otByMonth' => $otByMonth,
            'leaveByMonth' => $leaveByMonth,
            'alloc' => $alloc,
            'topOt' => $topOt,
            'joiners' => $joiners,
            'leavers' => $leavers,
            'pendingLeaves' => LeaveRequest::with(['employee', 'leaveType'])->where($leaveInYear)->where('status', 'pending')->latest()->take(6)->get(),
            'recentActivity' => auth()->user()->isSuperAdmin()
                ? ActivityLog::with('user')->whereYear('created_at', $year)->latest()->take(8)->get() : collect(),
        ]);
    }
}
