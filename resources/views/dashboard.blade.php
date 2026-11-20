@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php
    $w = $workflow;
    $u = auth()->user();
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $stepsDone = collect($w['steps'])->where('state', 'done')->count();
    $maxPay = max(1, $payroll->max('total'));
    $maxOt = max(1, $otByMonth->max());
    $maxLeave = max(1, max($leaveByMonth));
    $pool = max(0.01, $alloc['allocated']);
    $usedPct = min(100, round($alloc['used'] / $pool * 100));
    $lopPct = min($usedPct, round($alloc['lop'] / $pool * 100));
    $short = fn ($v) => $v >= 10000000 ? round($v / 10000000, 1).'Cr' : ($v >= 100000 ? round($v / 100000, 1).'L' : ($v >= 1000 ? round($v / 1000).'K' : num_clean($v)));
@endphp

@if ($u->isSuperAdmin())
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

{{-- ===== hero: year at a glance + current payslip cycle ===== --}}
<div class="dash-hero mb-3">
    <div class="row g-3 align-items-center">
        <div class="col-lg-5">
            <div class="hero-sub small">{{ $greeting }}, {{ $u->name }}</div>
            <div class="d-flex align-items-end gap-3 mt-1">
                <div class="hero-year">{{ $year }}</div>
                <div class="pb-1 small hero-sub">{{ $year == now()->year ? 'current year' : ($year < now()->year ? 'past year' : 'next year') }}<br>{{ $stats['on_rolls'] }} employees on the rolls</div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <a class="hero-chip" href="{{ route('payroll.index', ['year' => $year]) }}"><i class="bi bi-cash-stack"></i> ₹ {{ inr($stats['payroll_total'], 0) }} paid</a>
                <a class="hero-chip" href="{{ route('leave.allocations', ['year' => $year]) }}"><i class="bi bi-calendar2-check"></i> {{ num_clean($alloc['used']) }} / {{ num_clean($alloc['allocated']) }} leave days used</a>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="p-3 rounded-4" style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18)">
                <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                    <div>
                        <div class="small hero-sub">{{ $isWorkingYear ? 'Current payslip cycle' : 'Payslip cycle' }}</div>
                        <div class="fw-semibold fs-5">{{ $w['period']->format('F Y') }}</div>
                        <div class="small hero-sub">Generate &amp; send by {{ $w['deadline']->format('d M Y') }}</div>
                    </div>
                    <div class="text-end">
                        @if ($w['complete'])<span class="badge bg-success">Done</span>
                        @elseif ($w['daysLeft'] < 0)<span class="badge bg-danger">Overdue {{ abs($w['daysLeft']) }} d</span>
                        @elseif (now()->gte($w['period']->copy()->addMonthNoOverflow()))<span class="badge bg-light text-dark">{{ $w['daysLeft'] }} day(s) left</span>
                        @else<span class="badge bg-light text-dark">Month in progress</span>@endif
                        <div class="small hero-sub mt-1">{{ $stepsDone }} of {{ count($w['steps']) }} steps done</div>
                    </div>
                </div>
                <div class="hero-steps my-3">@foreach ($w['steps'] as $s)<span class="{{ $s['state'] }}" title="{{ $s['title'] }} — {{ $s['detail'] }}"></span>@endforeach</div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('workflow.index', ['period' => $w['period']->format('Y-m')]) }}" class="btn btn-light btn-sm fw-semibold"><i class="bi bi-list-check"></i> Open workflow</a>
                    <a href="{{ route('ot.index', ['period' => $w['period']->format('Y-m')]) }}" class="hero-chip"><i class="bi bi-clock-history"></i> OT entry</a>
                    <a href="{{ route('payroll.index', ['year' => $year]) }}" class="hero-chip"><i class="bi bi-gear-wide-connected"></i> Run payroll</a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ===== key figures for the year ===== --}}
<div class="row g-3 mb-3">
    @foreach ([
        ['Employees', $stats['active'].' active', 'bi-people', 'c-blue', route('employees.index', ['year' => $year]), '+'.$stats['joiners'].' joined · −'.$stats['leavers'].' left'],
        ['Payroll '.$year, '₹ '.$short($stats['payroll_total']), 'bi-cash-stack', 'c-indigo', route('payroll.index', ['year' => $year]), $stats['payroll_months'].' months · '.$stats['finalized'].' finalized'],
        ['Leave days taken', num_clean($stats['leave_days']), 'bi-calendar-x', 'c-green', route('leave.register', ['year' => $year]), num_clean($alloc['balance']).' days balance'],
        ['Pending leave', $stats['pending_leave'], 'bi-hourglass-split', 'c-orange', route('leave-requests.index', ['year' => $year, 'status' => 'pending']), $stats['on_leave_today'] !== null ? $stats['on_leave_today'].' on leave today' : 'requests in '.$year],
        ['OT hours', num_clean($stats['ot_hours']), 'bi-stopwatch', 'c-pink', route('ot.history', ['year' => $year]), $stats['ot_pending'].' entries pending'],
        ['Settlements', '₹ '.$short($stats['settlements']), 'bi-briefcase', 'c-violet', route('settlements.index', ['year' => $year]), 'EL & gratuity paid'],
    ] as [$label, $value, $icon, $color, $link, $foot])
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ $link }}" class="text-decoration-none d-block h-100">
                <div class="card kpi h-100"><div class="card-body">
                    <div class="d-flex align-items-center gap-2">
                        <span class="kpi-icon {{ $color }}"><i class="bi {{ $icon }}"></i></span>
                        <div class="min-w-0"><div class="kpi-value text-truncate">{{ $value }}</div><div class="kpi-label">{{ $label }}</div></div>
                    </div>
                    <div class="kpi-foot text-truncate">{{ $foot }}</div>
                </div></div>
            </a>
        </div>
    @endforeach
</div>

{{-- ===== celebrations: birthdays today, upcoming birthdays, work anniversaries ===== --}}
@if ($birthdaysToday->isNotEmpty())
    <div class="bday-hero mb-3" id="bdayHero">
        <canvas id="confetti" aria-hidden="true"></canvas>
        <div class="d-flex flex-wrap align-items-center gap-3 position-relative">
            <div class="bday-faces">
                @foreach ($birthdaysToday->take(4) as $e)@include('partials.avatar', ['e' => $e, 'size' => 76, 'ring' => 'ring'])@endforeach
            </div>
            <div class="flex-grow-1">
                <div class="small fw-semibold text-uppercase opacity-75">🎉 Celebration today</div>
                <div class="fs-3 fw-bold lh-sm">Happy Birthday, {{ $birthdaysToday->pluck('name')->join(', ', ' & ') }}!</div>
                <div class="opacity-75">Wishing {{ $birthdaysToday->count() > 1 ? 'them' : 'you' }} a wonderful year ahead from everyone at {{ setting('company_name') }}.
                    @foreach ($birthdaysToday as $e)@if ($e->date_of_birth)<span class="badge bg-light ms-1">{{ $e->name }} turns {{ $e->date_of_birth->age }}</span>@endif @endforeach
                </div>
            </div>
            <button type="button" class="btn btn-light fw-semibold" id="moreConfetti"><i class="bi bi-stars"></i> Celebrate</button>
        </div>
    </div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-balloon-heart"></i> Birthdays · next 30 days</span>
                <span class="badge bg-warning">{{ $birthdays->count() }}</span>
            </div>
            <div class="card-body py-2 celebrate-list">
                @forelse ($birthdays->take(6) as $e)
                    @php($nb = $e->nextBirthday())
                    @php($days = (int) now()->startOfDay()->diffInDays($nb, true))
                    <a href="{{ route('employees.show', $e) }}" class="cel-row {{ $days === 0 ? 'today' : '' }}">
                        @include('partials.avatar', ['e' => $e, 'size' => 40])
                        <span class="flex-grow-1 min-w-0"><span class="d-block fw-semibold text-truncate text-body">{{ $e->name }}</span>
                            <span class="small text-muted">{{ $nb->format('D, d M') }} · turns {{ $nb->year - $e->date_of_birth->year }}</span></span>
                        <span class="cel-when">{{ $days === 0 ? '🎂 Today' : ($days === 1 ? 'Tomorrow' : "in $days days") }}</span>
                    </a>
                @empty
                    <p class="text-muted small my-3">No birthdays in the next 30 days. Add dates of birth on the employee records to see them here.</p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-award"></i> Work anniversaries · next 30 days</span>
                <span class="badge bg-primary">{{ $anniversaries->count() }}</span>
            </div>
            <div class="card-body py-2 celebrate-list">
                @forelse ($anniversaries->take(6) as $e)
                    @php($days = (int) now()->startOfDay()->diffInDays($e->anniv_date, true))
                    <a href="{{ route('employees.show', $e) }}" class="cel-row {{ $days === 0 ? 'today' : '' }}">
                        @include('partials.avatar', ['e' => $e, 'size' => 40])
                        <span class="flex-grow-1 min-w-0"><span class="d-block fw-semibold text-truncate text-body">{{ $e->name }}</span>
                            <span class="small text-muted">Joined {{ $e->date_of_joining->format('d M Y') }} · completes <strong>{{ $e->anniv_years }} year{{ $e->anniv_years > 1 ? 's' : '' }}</strong></span></span>
                        <span class="cel-when">{{ $days === 0 ? '🏆 Today' : $e->anniv_date->format('d M') }}</span>
                    </a>
                @empty
                    <p class="text-muted small my-3">No work anniversaries in the next 30 days.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- payroll by month --}}
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart-line"></i> Payroll by month — {{ $year }}</span>
                <span class="small text-muted"><span class="legend-dot accent"></span>Finalized <span class="legend-dot ms-2" style="background:#f59e0b"></span>Draft <span class="legend-dot ms-2" style="background:#e7ebf3"></span>Not run</span>
            </div>
            <div class="card-body">
                <div class="bars">
                    @foreach ($payroll as $m => $p)
                        <a class="bar {{ $p['run'] ? ($p['status'] === 'finalized' ? '' : 'draft') : 'empty' }} {{ $isWorkingYear && $m == $period->month ? 'current' : '' }}"
                           href="{{ $p['run'] ? route('payroll.show', $p['run']) : route('payroll.index', ['year' => $year]) }}"
                           title="{{ month_name($m) }} {{ $year }}: {{ $p['run'] ? '₹ '.inr($p['total'], 0).' ('.$p['status'].')' : 'payroll not run' }}">
                            @if ($p['total'])<b>{{ $short($p['total']) }}</b>@endif
                            <i style="height:{{ $p['total'] ? max(4, round($p['total'] / $maxPay * 100)) : 2 }}%"></i>
                        </a>
                    @endforeach
                </div>
                <div class="bars-x">@foreach (range(1, 12) as $m)<span>{{ month_name($m, true) }}</span>@endforeach</div>
                <div class="row text-center mt-3 g-2">
                    <div class="col-4"><div class="small text-muted">Total payable</div><div class="fw-bold">₹ {{ inr($stats['payroll_total'], 0) }}</div></div>
                    <div class="col-4"><div class="small text-muted">Monthly average</div><div class="fw-bold">₹ {{ inr($stats['payroll_months'] ? $stats['payroll_total'] / $stats['payroll_months'] : 0, 0) }}</div></div>
                    <div class="col-4"><div class="small text-muted">Payslips</div><div class="fw-bold">{{ $stats['payslips'] }}</div></div>
                </div>
            </div>
        </div>
    </div>

    {{-- yearly leave allocation --}}
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar2-plus"></i> Leave {{ $year }}</span>
                <a href="{{ route('leave.allocations', ['year' => $year]) }}" class="small">Allocation</a>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="donut" style="--p1: {{ $usedPct }}; --p2: {{ $lopPct }}">
                        <div class="donut-c"><div><div class="fs-4 fw-bold">{{ $usedPct }}%</div><div class="small text-muted">used</div></div></div>
                    </div>
                    <div class="small flex-grow-1">
                        <div class="d-flex justify-content-between"><span><span class="legend-dot" style="background:#e7ebf3;border:1px solid #cbd5e1"></span>Allocated</span><strong>{{ num_clean($alloc['allocated']) }}</strong></div>
                        <div class="d-flex justify-content-between mt-1"><span><span class="legend-dot accent"></span>Used</span><strong>{{ num_clean($alloc['used']) }}</strong></div>
                        <div class="d-flex justify-content-between mt-1"><span><span class="legend-dot" style="background:#10b981"></span>Balance</span><strong>{{ num_clean($alloc['balance']) }}</strong></div>
                        <div class="d-flex justify-content-between mt-1"><span><span class="legend-dot" style="background:#db2777"></span>Loss of pay</span><strong class="{{ $alloc['lop'] > 0 ? 'text-danger' : '' }}">{{ num_clean($alloc['lop']) }}</strong></div>
                    </div>
                </div>
                <hr>
                @if ($alloc['generated'] < $alloc['employees'])
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle text-warning fs-5"></i>
                        <div class="small flex-grow-1">Leave generated for <strong>{{ $alloc['generated'] }} of {{ $alloc['employees'] }}</strong> employees.</div>
                        <form method="POST" action="{{ route('leave.allocations.generate') }}" data-busy="Generating leave…">@csrf
                            <input type="hidden" name="year" value="{{ $year }}">
                            <button class="btn btn-sm btn-primary"><i class="bi bi-magic"></i> Generate</button>
                        </form>
                    </div>
                @else
                    <div class="small text-success"><i class="bi bi-check-circle-fill"></i> {{ $year }} leave generated for all {{ $alloc['employees'] }} employees.</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- leave & OT by month --}}
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-activity"></i> Leave days &amp; OT hours by month — {{ $year }}</div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="small fw-semibold mb-1"><span class="legend-dot" style="background:#059669"></span>Leave days <span class="text-muted fw-normal">· {{ num_clean($stats['leave_days']) }}</span></div>
                        <div class="bars sm green">
                            @foreach ($leaveByMonth as $m => $d)
                                <a class="bar {{ $d ? '' : 'empty' }}" href="{{ route('leave-requests.index', ['year' => $year, 'month' => $m]) }}" title="{{ month_name($m) }}: {{ num_clean($d) }} day(s)">
                                    @if ($d)<b>{{ num_clean(round($d)) }}</b>@endif
                                    <i style="height:{{ $d ? max(4, round($d / $maxLeave * 100)) : 2 }}%"></i>
                                </a>
                            @endforeach
                        </div>
                        <div class="bars-x">@foreach (range(1, 12) as $m)<span>{{ mb_substr(month_name($m, true), 0, 1) }}</span>@endforeach</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small fw-semibold mb-1"><span class="legend-dot" style="background:#ea580c"></span>Approved OT hours <span class="text-muted fw-normal">· {{ num_clean($stats['ot_hours']) }}</span></div>
                        <div class="bars sm orange">
                            @foreach ($otByMonth as $m => $h)
                                <a class="bar {{ $h ? '' : 'empty' }}" href="{{ route('ot.report', ['period' => sprintf('%04d-%02d', $year, $m)]) }}" title="{{ month_name($m) }}: {{ num_clean($h) }} h">
                                    @if ($h)<b>{{ num_clean(round($h)) }}</b>@endif
                                    <i style="height:{{ $h ? max(4, round($h / $maxOt * 100)) : 2 }}%"></i>
                                </a>
                            @endforeach
                        </div>
                        <div class="bars-x">@foreach (range(1, 12) as $m)<span>{{ mb_substr(month_name($m, true), 0, 1) }}</span>@endforeach</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- top OT --}}
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-trophy"></i> Most OT — {{ $year }}</span>
                <a href="{{ route('ot.history', ['year' => $year]) }}" class="small">OT history</a>
            </div>
            <div class="card-body py-2">
                @php($topMax = max(1, (float) ($topOt->first()->hours ?? 1)))
                @forelse ($topOt as $i => $t)
                    <div class="rank-row">
                        <span class="rank-n">{{ $i + 1 }}</span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between small"><span class="text-truncate fw-semibold">{{ $t->employee?->name }}</span><span class="text-nowrap">{{ num_clean($t->hours) }} h</span></div>
                            <div class="mini-progress mt-1"><span style="width:{{ round($t->hours / $topMax * 100) }}%"></span></div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted small my-3">No approved OT in {{ $year }}.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- pending leave --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-hourglass-split"></i> Pending leave requests <span class="badge bg-warning text-dark">{{ $stats['pending_leave'] }}</span></span>
                <a href="{{ route('leave-requests.create') }}" class="small">+ New</a>
            </div>
            <ul class="list-group list-group-flush">
                @forelse ($pendingLeaves as $l)
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate">{{ $l->employee->name }}</div>
                            <div class="small text-muted">{{ $l->leaveType->code }} · {{ $l->from_date->format('d M') }}{{ $l->from_date->ne($l->to_date) ? ' – '.$l->to_date->format('d M') : '' }} · {{ num_clean($l->days) }} d</div>
                        </div>
                        <div class="d-flex gap-1">
                            <form method="POST" action="{{ route('leave-requests.approve', $l) }}">@csrf<button class="btn btn-sm btn-success" title="Approve"><i class="bi bi-check-lg"></i></button></form>
                            <form method="POST" action="{{ route('leave-requests.reject', $l) }}">@csrf<button class="btn btn-sm btn-outline-danger" title="Reject"><i class="bi bi-x-lg"></i></button></form>
                        </div>
                    </li>
                @empty
                    <li class="list-group-item text-muted small py-3"><i class="bi bi-check2-circle text-success"></i> No pending requests in {{ $year }}.</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- joiners & leavers --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-arrow-left-right"></i> Joined &amp; left in {{ $year }}</span>
                <a href="{{ route('employees.create') }}" class="small">+ Register</a>
            </div>
            <ul class="list-group list-group-flush">
                @forelse ($joiners->map(fn ($e) => [$e, 'in', $e->date_of_joining])->concat($leavers->map(fn ($e) => [$e, 'out', $e->date_of_exit]))->sortByDesc(fn ($x) => $x[2])->take(8) as [$e, $dir, $date])
                    <li class="list-group-item d-flex align-items-center gap-2">
                        <span class="badge {{ $dir === 'in' ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' }}"><i class="bi {{ $dir === 'in' ? 'bi-box-arrow-in-right' : 'bi-box-arrow-right' }}"></i> {{ $dir === 'in' ? 'Joined' : 'Left' }}</span>
                        @include('partials.avatar', ['e' => $e, 'size' => 32])<a href="{{ route('employees.show', [$e, 'year' => $year]) }}" class="flex-grow-1 text-truncate">{{ $e->emp_code }} · {{ $e->name }}</a>
                        <span class="small text-muted text-nowrap">{{ $date->format('d M') }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-muted small py-3">No joiners or leavers in {{ $year }}.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="{{ $recentActivity->isNotEmpty() ? 'col-lg-5' : 'col-12' }}">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-lightning-charge"></i> Quick actions</div>
            <div class="card-body d-grid gap-2" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr))">
                <a href="{{ route('employees.create') }}" class="btn btn-outline-primary text-start"><i class="bi bi-person-plus"></i> Register employee</a>
                <a href="{{ route('leave-requests.create') }}" class="btn btn-outline-primary text-start"><i class="bi bi-calendar-plus"></i> Record leave</a>
                <a href="{{ route('ot.index', ['period' => $period->format('Y-m')]) }}" class="btn btn-outline-primary text-start"><i class="bi bi-clock-history"></i> Enter OT · {{ $period->format('M Y') }}</a>
                <a href="{{ route('payroll.index', ['year' => $year]) }}" class="btn btn-outline-primary text-start"><i class="bi bi-cash-stack"></i> Run payroll</a>
                <a href="{{ route('leave.allocations', ['year' => $year]) }}" class="btn btn-outline-primary text-start"><i class="bi bi-calendar2-plus"></i> Yearly leave</a>
                <a href="{{ route('settlements.create') }}" class="btn btn-outline-primary text-start"><i class="bi bi-briefcase"></i> EL / Gratuity</a>
            </div>
        </div>
    </div>
    @if ($recentActivity->isNotEmpty())
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between"><span><i class="bi bi-journal-text"></i> Recent activity — {{ $year }}</span><a class="small" href="{{ route('activity-logs.index', ['year' => $year]) }}">View all</a></div>
                <ul class="list-group list-group-flush">
                    @foreach ($recentActivity as $a)
                        <li class="list-group-item small d-flex gap-2">
                            <span class="text-muted text-nowrap" style="width:92px">{{ $a->created_at->format('d M H:i') }}</span>
                            <span class="text-truncate"><strong>{{ $a->user?->name ?? 'System' }}</strong> · {{ $a->description }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</div>
@endsection
@push('scripts')
<script>
(function () {
    // birthday wishes: toast + a short burst of confetti (respects "reduce motion")
    const names = @json($birthdaysToday->pluck('name'));
    if (!names.length) return;
    window.addEventListener('load', () => setTimeout(() => window.notify?.({type: 'success', title: '🎂 Birthday today', message: 'Happy birthday, ' + names.join(', ') + '!', duration: 7000}), 600));
    const canvas = document.getElementById('confetti');
    if (!canvas || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const ctx = canvas.getContext('2d');
    const colors = ['#f97316', '#a855f7', '#facc15', '#22d3ee', '#ec4899', '#22c55e'];
    let parts = [], raf = null;
    const size = () => { canvas.width = canvas.offsetWidth * devicePixelRatio; canvas.height = canvas.offsetHeight * devicePixelRatio; };
    function burst(n) {
        size();
        for (let i = 0; i < n; i++) parts.push({x: Math.random() * canvas.width, y: -20 - Math.random() * canvas.height * .5, r: (4 + Math.random() * 5) * devicePixelRatio,
            vx: (Math.random() - .5) * 2, vy: (2 + Math.random() * 3) * devicePixelRatio, rot: Math.random() * 6, vr: (Math.random() - .5) * .3, c: colors[i % colors.length]});
        if (!raf) loop();
    }
    function loop() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        parts.forEach(p => { p.x += p.vx; p.y += p.vy; p.rot += p.vr; ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rot); ctx.fillStyle = p.c; ctx.fillRect(-p.r / 2, -p.r / 4, p.r, p.r / 2); ctx.restore(); });
        parts = parts.filter(p => p.y < canvas.height + 20);
        raf = parts.length ? requestAnimationFrame(loop) : null;
    }
    burst(160);
    document.getElementById('moreConfetti')?.addEventListener('click', () => burst(200));
})();
</script>
@endpush
