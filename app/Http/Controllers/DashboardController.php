<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OtEntry;
use App\Models\PayrollRun;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(\App\Services\WorkflowService $workflow)
    {
        $now = now();
        $wp = working_period();
        view()->share('workflow', $workflow->status($wp->year, $wp->month));
        $latestRun = PayrollRun::withSum('payslips', 'total_amount')->withCount('payslips')
            ->orderByDesc('year')->orderByDesc('month')->first();

        $otThisMonth = OtEntry::where('year', $now->year)->where('month', $now->month);

        $runs = PayrollRun::withSum('payslips', 'total_amount')->orderByDesc('year')->orderByDesc('month')->take(6)->get()->reverse();

        return view('dashboard', [
            'stats' => [
                'employees' => Employee::where('status', 'active')->count(),
                'pending_leave' => LeaveRequest::where('status', 'pending')->count(),
                'on_leave_today' => LeaveRequest::where('status', 'approved')->whereDate('from_date', '<=', $now)->whereDate('to_date', '>=', $now)->count(),
                'ot_pending' => OtEntry::where('status', 'pending')->count(),
                'ot_hours_month' => (float) (clone $otThisMonth)->where('status', 'approved')->sum('normal_ot_hours') + (float) (clone $otThisMonth)->where('status', 'approved')->sum('spl_ot_hours'),
                'admins' => User::count(),
            ],
            'latestRun' => $latestRun,
            'runs' => $runs,
            'pendingLeaves' => LeaveRequest::with(['employee', 'leaveType'])->where('status', 'pending')->latest()->take(6)->get(),
            'recentActivity' => auth()->user()->isSuperAdmin() ? ActivityLog::with('user')->latest()->take(8)->get() : collect(),
        ]);
    }
}
