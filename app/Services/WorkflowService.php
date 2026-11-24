<?php

namespace App\Services;

use App\Models\ElLedger;
use App\Models\Employee;
use App\Models\LeaveAdjustment;
use App\Models\LeaveRequest;
use App\Models\OtEntry;
use App\Models\PayrollRun;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/** Status of the monthly payslip cycle for one month — everything is computed live from the data. */
class WorkflowService
{
    public function status(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $deadline = $start->copy()->addMonthNoOverflow()->day((int) Setting::get('payroll_deadline_day', 10) ?: 10)->endOfDay();
        $run = PayrollRun::where('year', $year)->where('month', $month)->withCount('payslips')->first();

        $onRolls = Employee::whereIn('status', ['active', 'exited'])
            ->where(fn ($q) => $q->whereNull('date_of_joining')->orWhereDate('date_of_joining', '<=', $end))
            ->where(fn ($q) => $q->whereNull('date_of_exit')->orWhereDate('date_of_exit', '>=', $start));
        $employees = (clone $onRolls)->count();
        $joiners = Employee::whereBetween('date_of_joining', [$start->toDateString(), $end->toDateString()])->get(['id', 'emp_code', 'name']);
        $leavers = Employee::whereBetween('date_of_exit', [$start->toDateString(), $end->toDateString()])->get(['id', 'emp_code', 'name']);
        $noEmail = (clone $onRolls)->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))->get(['id', 'emp_code', 'name']);
        $noBasic = (clone $onRolls)->where('basic_salary', '<=', 0)->count();

        $leaveInMonth = LeaveRequest::whereDate('from_date', '<=', $end)->whereDate('to_date', '>=', $start);
        $pendingLeave = (clone $leaveInMonth)->where('status', 'pending')->count();
        $approvedLeave = (clone $leaveInMonth)->where('status', 'approved')->count();
        $monthlyEntries = LeaveAdjustment::where('year', $year)->where('month', $month)->count();

        $otEligible = (clone $onRolls)->where('ot_eligible', true)->count();
        $ot = OtEntry::where('year', $year)->where('month', $month);
        $otCount = (clone $ot)->count();
        $otPending = (clone $ot)->where('status', 'pending')->count();

        // has anything changed since the payslips were last calculated?
        $stale = false;
        if ($run && $run->payslips_count && ! $run->isFinalized()) {
            $calcAt = $run->payslips()->max('updated_at');
            $stale = OtEntry::where('year', $year)->where('month', $month)->where('updated_at', '>', $calcAt)->exists()
                || (clone $leaveInMonth)->where('updated_at', '>', $calcAt)->exists()
                || LeaveAdjustment::where('year', $year)->where('month', $month)->where('updated_at', '>', $calcAt)->exists()
                || Employee::where('updated_at', '>', $calcAt)->exists();
        }
        $sent = $run ? $run->payslips()->whereNotNull('emailed_at')->count() : 0;
        $failed = $run ? $run->payslips()->whereNull('emailed_at')->whereNotNull('email_error')->count() : 0;

        $steps = [
            [
                'key' => 'employees', 'title' => 'Employee master up to date',
                'state' => $noBasic ? 'attention' : 'done',
                'detail' => "$employees employees on rolls"
                    .($joiners->count() ? ' · '.$joiners->count().' joined: '.$joiners->pluck('name')->implode(', ') : '')
                    .($leavers->count() ? ' · '.$leavers->count().' exited: '.$leavers->pluck('name')->implode(', ') : '')
                    .($noBasic ? " · $noBasic without basic salary" : ''),
                'links' => [['Employees', route('employees.index')], ['Import / update from Excel', route('imports.index')]],
            ],
            [
                'key' => 'leave', 'title' => 'Leave recorded & approved',
                'state' => $pendingLeave ? 'attention' : (($approvedLeave + $monthlyEntries) ? 'done' : 'todo'),
                'detail' => "$approvedLeave approved leave requests, $monthlyEntries monthly entries"
                    .($pendingLeave ? " · $pendingLeave PENDING approval (not counted in payroll)" : '')
                    .(($approvedLeave + $monthlyEntries) ? '' : ' · nothing recorded yet (fine if nobody took leave)'),
                'links' => [['Leave requests', route('leave-requests.index', ['year' => $year, 'month' => $month, 'status' => $pendingLeave ? 'pending' : null])],
                    ['Monthly leave entry', route('leave.adjustments', ['year' => $year, 'month' => $month])], ['Import register', route('imports.index')]],
            ],
            [
                'key' => 'ot', 'title' => 'OT & shift hours entered and approved',
                'state' => $otPending ? 'attention' : ($otCount ? 'done' : 'todo'),
                'detail' => "$otCount of $otEligible OT-eligible employees have an entry".($otPending ? " · $otPending PENDING approval (not paid)" : ''),
                'links' => [['OT entry', route('ot.index', ['period' => $start->format('Y-m')])], ['Import OT sheet', route('imports.index')]],
            ],
            [
                'key' => 'generate', 'title' => 'Generate payslips',
                'state' => ! $run ? 'todo' : ($stale ? 'attention' : 'done'),
                'detail' => ! $run ? 'Payroll not created yet' : ($run->payslips_count.' payslips'.($stale ? ' · data changed after the last calculation — click Recalculate' : '')),
                'links' => $run ? [['Open payroll', route('payroll.show', $run)]] : [],
            ],
            [
                'key' => 'finalize', 'title' => 'Review & finalize',
                'state' => $run?->isFinalized() ? 'done' : 'todo',
                'detail' => $run?->isFinalized() ? 'Finalized by '.($run->finalizer?->name ?? '—').' on '.$run->finalized_at?->format('d M Y') : 'Check payslips, add advances / birthday welfare, then finalize (locks the month)',
                'links' => $run ? [['Salary register', route('payroll.show', $run)]] : [],
            ],
            [
                'key' => 'email', 'title' => 'Email payslips to employees',
                'state' => ! $run?->isFinalized() ? 'todo' : ($run->payslips_count && $sent >= $run->payslips_count ? 'done' : (($failed || $noEmail->count()) ? 'attention' : 'todo')),
                'detail' => ($run ? "$sent of {$run->payslips_count} sent" : 'Not started')
                    .($failed ? " · $failed failed" : '')
                    .($noEmail->count() ? ' · '.$noEmail->count().' employees have no email: '.$noEmail->pluck('name')->take(8)->implode(', ').($noEmail->count() > 8 ? '…' : '') : '')
                    .(Setting::mailConfigured() ? '' : ' · SMTP not configured (Settings → Email)'),
                'links' => $run ? [['Payslips & email', route('payroll.show', $run)]] : [],
            ],
        ];

        $yearEnd = null;
        if ($month === 12) {
            $closed = ElLedger::where('year', $year)->count();
            $yearEnd = ['year' => $year, 'closed' => $closed, 'employees' => $employees];
        }

        $done = collect($steps)->where('state', 'done')->count();

        return [
            'period' => $start, 'deadline' => $deadline, 'run' => $run, 'steps' => $steps, 'done' => $done,
            'total' => count($steps), 'daysLeft' => (int) now()->startOfDay()->diffInDays($deadline->copy()->startOfDay(), false),
            'complete' => $done === count($steps), 'yearEnd' => $yearEnd,
            'canGenerate' => ! $run && now()->gte($start),
        ];
    }

    /** The last N months with their completion, for the overview list. */
    public function recent(int $months = 12): array
    {
        $out = [];
        $p = working_period();
        for ($i = 0; $i < $months; $i++) {
            $d = $p->copy()->subMonthsNoOverflow($i);
            $run = PayrollRun::where('year', $d->year)->where('month', $d->month)->withCount('payslips')->first();
            $out[] = [
                'period' => $d, 'run' => $run,
                'sent' => $run ? $run->payslips()->whereNotNull('emailed_at')->count() : 0,
            ];
        }

        return $out;
    }
}
