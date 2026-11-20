@extends('layouts.app')
@section('title', 'Monthly Payslip Workflow')
@section('content')
@php($p = $w['period'])
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center">
        <form method="GET" class="auto-filter d-flex gap-2 align-items-center">
            <span class="small">Month</span>
            <input type="month" name="period" value="{{ $p->format('Y-m') }}" class="form-control form-control-sm" style="width:170px">
        </form>
        <div>
            <div class="fw-semibold fs-5">{{ $p->format('F Y') }} payslips</div>
            <div class="small text-muted">To be generated 1 – {{ $w['deadline']->format('d M Y') }}</div>
        </div>
        <div class="ms-auto text-end">
            @if ($w['complete'])
                <span class="badge bg-success fs-6"><i class="bi bi-check2-all"></i> Completed</span>
            @elseif ($w['daysLeft'] < 0)
                <span class="badge bg-danger fs-6">Overdue by {{ abs($w['daysLeft']) }} day(s)</span>
            @elseif (now()->lt($p->copy()->addMonthNoOverflow()))
                <span class="badge bg-secondary fs-6">Month in progress</span>
            @else
                <span class="badge {{ $w['daysLeft'] <= 3 ? 'bg-warning text-dark' : 'bg-primary' }} fs-6">{{ $w['daysLeft'] }} day(s) left</span>
            @endif
            <div class="progress mt-2" style="height:8px;width:220px">
                <div class="progress-bar bg-success" style="width:{{ round($w['done'] / $w['total'] * 100) }}%"></div>
            </div>
            <div class="small text-muted">{{ $w['done'] }} of {{ $w['total'] }} steps done</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card">
            <ul class="list-group list-group-flush">
                @foreach ($w['steps'] as $i => $s)
                    @php($icon = ['done' => ['bi-check-circle-fill', 'text-success'], 'attention' => ['bi-exclamation-circle-fill', 'text-warning'], 'todo' => ['bi-circle', 'text-secondary']][$s['state']])
                    <li class="list-group-item py-3 wf-step s-{{ $s['state'] }}">
                        <div class="d-flex gap-3">
                            <i class="bi {{ $icon[0] }} {{ $icon[1] }} fs-4"></i>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $i + 1 }}. {{ $s['title'] }}</div>
                                <div class="small {{ $s['state'] === 'attention' ? 'text-danger' : 'text-muted' }}">{{ $s['detail'] }}</div>
                                <div class="mt-2 d-flex flex-wrap gap-2">
                                    @foreach ($s['links'] as [$label, $url])
                                        <a href="{{ $url }}" class="btn btn-sm btn-outline-primary">{{ $label }}</a>
                                    @endforeach

                                    @if ($s['key'] === 'generate' && ! $w['run'])
                                        <form method="POST" action="{{ route('payroll.store') }}" class="d-flex flex-wrap gap-2 align-items-center" data-confirm="Generate {{ $p->format('F Y') }} payslips for every employee?" data-confirm-title="Generate payslips" data-confirm-level="double" data-busy="Generating payslips…">@csrf
                                            <input type="hidden" name="period" value="{{ $p->format('Y-m') }}">
                                            <span class="small">Working days</span>
                                            <input type="number" step="0.5" name="working_days" value="{{ num_clean($workingDays) }}" class="form-control form-control-sm" style="width:80px">
                                            <button class="btn btn-sm btn-primary"><i class="bi bi-gear-wide-connected"></i> Generate {{ $p->format('M Y') }} payslips</button>
                                        </form>
                                    @elseif ($s['key'] === 'generate' && $s['state'] === 'attention')
                                        <form method="POST" action="{{ route('payroll.generate', $w['run']) }}" data-confirm="Recalculate {{ $p->format('F Y') }} payslips from the latest data? Manual entries are kept." data-confirm-title="Recalculate payslips" data-confirm-level="double" data-busy="Recalculating…">@csrf<button class="btn btn-sm btn-warning"><i class="bi bi-arrow-repeat"></i> Recalculate</button></form>
                                    @endif

                                    @if ($s['key'] === 'finalize' && $w['run'] && ! $w['run']->isFinalized())
                                        <form method="POST" action="{{ route('payroll.finalize', $w['run']) }}" data-confirm="Finalize {{ $p->format('F Y') }}? Payslips, leave and OT for the month will be locked." data-confirm-title="Finalize payroll" data-confirm-level="double" data-confirm-final="After finalizing, only a Super Admin can re-open this month.">@csrf
                                            <button class="btn btn-sm btn-success"><i class="bi bi-lock"></i> Finalize</button>
                                        </form>
                                    @endif

                                    @if ($s['key'] === 'email' && $w['run']?->isFinalized() && $s['state'] !== 'done')
                                        <form method="POST" action="{{ route('payroll.email', $w['run']) }}" data-confirm="Email payslips to every employee who has not received one yet?" data-confirm-title="Email payslips" data-confirm-variant="success" data-confirm-level="double" data-confirm-final="Emails cannot be recalled once sent. Please make sure the payslips are correct." data-busy="Sending emails… this can take a minute">@csrf
                                            <button class="btn btn-sm btn-success"><i class="bi bi-send"></i> Email all unsent payslips</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($w['yearEnd'])
            <div class="card mt-3 border-primary">
                <div class="card-header"><i class="bi bi-calendar2-range"></i> Year-end {{ $w['yearEnd']['year'] }} → {{ $w['yearEnd']['year'] + 1 }}</div>
                <div class="card-body">
                    <p class="small mb-2">After December payroll is finalized, close {{ $w['yearEnd']['year'] }}: each employee's unused leave is credited as EL (max {{ num_clean(setting('el_max_per_year')) }} days) and frozen in the EL ledger. Leave entitlement for {{ $w['yearEnd']['year'] + 1 }} starts fresh automatically on 1 January — nothing else needs to be set up.</p>
                    <p class="small text-muted">{{ $w['yearEnd']['closed'] }} EL ledger entries already saved for {{ $w['yearEnd']['year'] }}.</p>
                    <form method="POST" action="{{ route('leave.close-year') }}" data-confirm="Save the computed EL credit of {{ $w['yearEnd']['year'] }} for all employees? Manual entries are kept." data-confirm-title="Close leave year" data-confirm-level="double">@csrf
                        <input type="hidden" name="year" value="{{ $w['yearEnd']['year'] }}">
                        <button class="btn btn-sm btn-primary"><i class="bi bi-lock"></i> Close leave year {{ $w['yearEnd']['year'] }}</button>
                    </form>
                    <p class="small text-muted mt-2 mb-0">Tip: for the yearly increment, add an allowance type such as "Increment {{ $w['yearEnd']['year'] + 1 }}" in Settings, then update amounts via Employees → Export → edit → Import.</p>
                </div>
            </div>
        @endif
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">Last 12 months</div>
            <ul class="list-group list-group-flush">
                @foreach ($recent as $r)
                    <a href="{{ route('workflow.index', ['period' => $r['period']->format('Y-m')]) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $r['period']->eq($p) ? 'active' : '' }}">
                        <span>{{ $r['period']->format('M Y') }}</span>
                        <span>
                            @if (! $r['run'])
                                <span class="badge bg-light text-dark">not generated</span>
                            @else
                                <span class="badge {{ $r['run']->isFinalized() ? 'bg-success' : 'bg-secondary' }}">{{ $r['run']->status }}</span>
                                <span class="badge bg-info text-dark" title="emailed">{{ $r['sent'] }}/{{ $r['run']->payslips_count }} <i class="bi bi-envelope"></i></span>
                            @endif
                        </span>
                    </a>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
