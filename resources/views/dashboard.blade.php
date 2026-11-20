@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php($w = $workflow)
@if (auth()->user()->isSuperAdmin())
    @php($lastBackup = setting('backup_last_at') ? \Illuminate\Support\Carbon::parse(setting('backup_last_at')) : null)
    @if (! $lastBackup || $lastBackup->lt(now()->subHours(26)) || setting('backup_last_status') === 'failed')
        <div class="alert alert-danger d-flex flex-wrap gap-2 align-items-center">
            <i class="bi bi-shield-exclamation fs-5"></i>
            <div class="flex-grow-1"><strong>Database backup needs attention.</strong>
                Last successful backup: {{ $lastBackup ? $lastBackup->diffForHumans() : 'never' }}.{{ setting('backup_last_status') === 'failed' ? ' Last attempt failed: '.\Illuminate\Support\Str::limit(setting('backup_last_error'), 120) : '' }}</div>
            <a href="{{ route('backups.index') }}" class="btn btn-sm btn-danger">Open backups</a>
        </div>
    @endif
@endif
<div class="card mb-3 overflow-hidden" style="border-left: 5px solid {{ $w['complete'] ? '#10b981' : ($w['daysLeft'] < 0 ? '#ef4444' : '#6366f1') }}; background: linear-gradient(110deg, #fff 55%, #eef2ff);">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center">
        <div>
            <div class="small text-muted">This cycle</div>
            <div class="fw-semibold fs-5">{{ $w['period']->format('F Y') }} payslips</div>
            <div class="small">Generate &amp; send by <strong>{{ $w['deadline']->format('d M Y') }}</strong>
                @if ($w['complete'])<span class="badge bg-success">done</span>
                @elseif ($w['daysLeft'] < 0)<span class="badge bg-danger">overdue {{ abs($w['daysLeft']) }} d</span>
                @elseif (now()->gte($w['period']->copy()->addMonthNoOverflow()))<span class="badge bg-primary">{{ $w['daysLeft'] }} day(s) left</span>
                @else<span class="badge bg-secondary">month in progress</span>@endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 flex-grow-1 justify-content-center">
            @foreach ($w['steps'] as $i => $s)
                <span class="badge step-pill rounded-pill {{ ['done' => 'bg-success', 'attention' => 'bg-warning text-dark', 'todo' => 'bg-light text-dark border'][$s['state']] }} px-3 py-2" title="{{ $s['detail'] }}">
                    {{ $i + 1 }}. {{ $s['title'] }}
                </span>
            @endforeach
        </div>
        <a href="{{ route('workflow.index') }}" class="btn btn-primary"><i class="bi bi-list-check"></i> Open workflow</a>
    </div>
</div>
<div class="row g-3 mb-3">
    @foreach ([
        ['Active Employees', $stats['employees'], 'bi-people', 'grad-1', route('employees.index')],
        ['Pending Leave Requests', $stats['pending_leave'], 'bi-hourglass-split', 'grad-2', route('leave-requests.index', ['status' => 'pending'])],
        ['On Leave Today', $stats['on_leave_today'], 'bi-calendar-x', 'grad-3', route('leave-requests.index', ['status' => 'approved'])],
        ['OT Entries Pending', $stats['ot_pending'], 'bi-clock', 'grad-4', route('ot.index')],
        ['OT Hours (this month)', num_clean($stats['ot_hours_month']), 'bi-stopwatch', 'grad-5', route('ot.report')],
    ] as [$label, $value, $icon, $color, $link])
        <div class="col-6 col-md-4 col-xl">
            <a href="{{ $link }}" class="text-decoration-none">
                <div class="card stat-card {{ $color }} h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="icon"><i class="bi {{ $icon }}"></i></div>
                        <div>
                            <div class="fs-3 fw-bold">{{ $value }}</div>
                            <div class="small opacity-75">{{ $label }}</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-cash-stack"></i> Payroll — last 6 months</span>
                <a href="{{ route('payroll.index') }}" class="small">All payroll</a>
            </div>
            <div class="card-body">
                @if ($runs->isEmpty())
                    <p class="text-muted mb-0">No payroll has been run yet. <a href="{{ route('payroll.index') }}">Create the first payroll</a>.</p>
                @else
                    @php($max = max(1, $runs->max('payslips_sum_total_amount')))
                    @foreach ($runs as $r)
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <a href="{{ route('payroll.show', $r) }}" style="width:80px" class="small">{{ \Illuminate\Support\Carbon::create($r->year, $r->month)->format('M Y') }}</a>
                            <div class="flex-grow-1 bg-light rounded" style="height:22px">
                                <div class="rounded" style="background:linear-gradient(90deg,#6366f1,#ec4899);height:22px;width:{{ round($r->payslips_sum_total_amount / $max * 100) }}%"></div>
                            </div>
                            <span class="small num" style="width:110px">₹ {{ inr($r->payslips_sum_total_amount, 0) }}</span>
                            <span class="badge {{ $r->status === 'finalized' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($r->status) }}</span>
                        </div>
                    @endforeach
                @endif
                @if ($latestRun)
                    <hr>
                    <div class="row text-center">
                        <div class="col"><div class="text-muted small">Latest run</div><div class="fw-semibold">{{ $latestRun->periodLabel() }}</div></div>
                        <div class="col"><div class="text-muted small">Payslips</div><div class="fw-semibold">{{ $latestRun->payslips_count }}</div></div>
                        <div class="col"><div class="text-muted small">Total payable</div><div class="fw-semibold">₹ {{ inr($latestRun->payslips_sum_total_amount, 0) }}</div></div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-calendar2-check"></i> Pending leave requests</span>
                <a href="{{ route('leave-requests.create') }}" class="small">+ New</a>
            </div>
            <ul class="list-group list-group-flush">
                @forelse ($pendingLeaves as $l)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">{{ $l->employee->name }}</div>
                            <div class="small text-muted">{{ $l->leaveType->code }} · {{ $l->from_date->format('d M') }}{{ $l->from_date->ne($l->to_date) ? ' – '.$l->to_date->format('d M') : '' }} · {{ num_clean($l->days) }} d</div>
                        </div>
                        <div class="d-flex gap-1">
                            <form method="POST" action="{{ route('leave-requests.approve', $l) }}">@csrf<button class="btn btn-sm btn-success" title="Approve"><i class="bi bi-check-lg"></i></button></form>
                            <form method="POST" action="{{ route('leave-requests.reject', $l) }}">@csrf<button class="btn btn-sm btn-outline-danger" title="Reject"><i class="bi bi-x-lg"></i></button></form>
                        </div>
                    </li>
                @empty
                    <li class="list-group-item text-muted">No pending requests.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-lg-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-lightning"></i> Quick actions</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <a href="{{ route('employees.create') }}" class="btn btn-outline-primary"><i class="bi bi-person-plus"></i> Register employee</a>
                <a href="{{ route('leave-requests.create') }}" class="btn btn-outline-primary"><i class="bi bi-calendar-plus"></i> Record leave</a>
                <a href="{{ route('ot.index') }}" class="btn btn-outline-primary"><i class="bi bi-clock-history"></i> Enter OT for {{ now()->format('M Y') }}</a>
                <a href="{{ route('payroll.index') }}" class="btn btn-outline-primary"><i class="bi bi-cash-stack"></i> Run payroll</a>
                <a href="{{ route('settlements.create') }}" class="btn btn-outline-primary"><i class="bi bi-briefcase"></i> EL / Gratuity settlement</a>
            </div>
        </div>
    </div>

    @if ($recentActivity->isNotEmpty())
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between"><span><i class="bi bi-journal-text"></i> Recent activity</span><a class="small" href="{{ route('activity-logs.index') }}">View all</a></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        @foreach ($recentActivity as $a)
                            <tr><td class="text-muted small" style="width:150px">{{ $a->created_at->format('d M Y H:i') }}</td><td style="width:160px">{{ $a->user?->name ?? '—' }}</td><td>{{ $a->description }}</td></tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
