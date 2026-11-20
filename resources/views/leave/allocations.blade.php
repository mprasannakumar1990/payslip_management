@extends('layouts.app')
@section('title', 'Yearly Leave Allocation')
@section('content')
@php($generated = $rows->where('generated', true)->count())
<div class="row g-3 mb-3">
    @foreach ([
        ['Employees in '.$year, $rows->count(), 'bi-people', 'c-blue'],
        ['Leave generated', $generated.' / '.$rows->count(), 'bi-calendar2-plus', $missing ? 'c-orange' : 'c-green'],
        ['Days allocated', num_clean($rows->sum('total')), 'bi-calendar-check', 'c-indigo'],
        ['Days used', num_clean($rows->sum('used')), 'bi-calendar-minus', 'c-pink'],
    ] as [$label, $value, $icon, $color])
        <div class="col-6 col-xl-3">
            <div class="card kpi h-100"><div class="card-body d-flex align-items-center gap-3">
                <span class="kpi-icon {{ $color }}"><i class="bi {{ $icon }}"></i></span>
                <div><div class="kpi-value">{{ $value }}</div><div class="kpi-label">{{ $label }}</div></div>
            </div></div>
        </div>
    @endforeach
</div>

@if ($missing)
    <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center">
        <i class="bi bi-exclamation-triangle fs-5"></i>
        <div class="flex-grow-1"><strong>{{ $missing }} employee(s)</strong> have no leave generated for {{ $year }} yet.</div>
    </div>
@endif

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        <form class="d-flex flex-wrap gap-2 flex-grow-1 auto-filter" method="GET">
            @include('partials.year-select', ['year' => $year])
            <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:240px" placeholder="Search ID / name">
        </form>
        <a href="{{ route('leave.allocations', ['year' => $year, 'q' => request('q'), 'export' => 1]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Export</a>
        <form method="POST" action="{{ route('leave.allocations.generate') }}" data-confirm="Generate {{ $types->pluck('code')->implode(' / ') }} leave for {{ $year }} for every employee on the rolls? Joiners and leavers get a pro-rated share. Values you edited by hand are kept." data-confirm-title="Generate leave for {{ $year }}" data-busy="Generating leave…">@csrf
            <input type="hidden" name="year" value="{{ $year }}">
            <button class="btn btn-sm btn-primary"><i class="bi bi-magic"></i> {{ $generated ? 'Refresh' : 'Generate' }} leave for {{ $year }}</button>
        </form>
    </div>
    <div class="card-body small text-muted py-2">
        Standard entitlement per year: {{ $types->map(fn ($t) => $t->code.' '.num_clean($t->yearly_quota))->implode(' + ') }} = {{ num_clean($types->sum('yearly_quota')) }} days,
        pro-rated by month for the joining and exit year. Leave is generated automatically on the first visit of each new year and for every new employee.
        Change a figure and press <i class="bi bi-check-lg"></i> to override it for one employee; <i class="bi bi-arrow-counterclockwise"></i> goes back to the standard entitlement.
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 table-register">
            <thead>
                <tr>
                    <th>Emp ID</th><th>Name</th><th>Joined</th>
                    @foreach ($types as $t)<th class="num" title="{{ $t->name }}">{{ $t->code }}</th>@endforeach
                    <th class="num">Total</th><th class="num">Used</th><th class="num">Balance</th><th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($rows as $r)
                @php($e = $r['employee'])
                <tr class="{{ $r['generated'] ? '' : 'table-warning' }}">
                    <td><a href="{{ route('employees.show', [$e, 'year' => $year]) }}">{{ $e->emp_code }}</a></td>
                    <td>{{ $e->name }}
                        @if ($r['manual'])<span class="badge bg-info-subtle text-info-emphasis">edited</span>@endif
                        @if ($e->date_of_exit && $e->date_of_exit->year == $year)<span class="badge bg-dark-subtle text-dark-emphasis">exit {{ $e->date_of_exit->format('d M') }}</span>@endif
                    </td>
                    <td class="small text-muted text-nowrap">{{ $e->date_of_joining?->format('d M Y') }}</td>
                    @foreach ($types as $t)
                        @php($a = $r['alloc'][$t->id])
                        <td class="num">
                            <input type="number" step="0.5" min="0" max="366" name="days[{{ $t->id }}]" form="alloc-{{ $e->id }}"
                                value="{{ $a ? num_clean($a->days) : '' }}" placeholder="{{ num_clean($r['default'][$t->id]) }}"
                                class="form-control form-control-sm text-end alloc-input {{ $a?->is_manual ? 'border-info' : '' }}" style="width:76px;margin-left:auto"
                                title="Standard: {{ num_clean($r['default'][$t->id]) }}">
                        </td>
                    @endforeach
                    <td class="num fw-semibold">{{ $r['generated'] ? num_clean($r['total']) : '—' }}</td>
                    <td class="num">{{ num_clean($r['used']) }}</td>
                    <td class="num fw-bold {{ $r['balance'] <= 0 && $r['generated'] ? 'text-danger' : '' }}">{{ num_clean($r['balance']) }}</td>
                    <td class="text-end text-nowrap">
                        <form id="alloc-{{ $e->id }}" method="POST" action="{{ route('leave.allocations.update', $e) }}" class="d-inline">@csrf @method('PUT')
                            <input type="hidden" name="year" value="{{ $year }}">
                            <button class="btn btn-sm btn-outline-primary" title="Save"><i class="bi bi-check-lg"></i></button>
                        </form>
                        <form method="POST" action="{{ route('leave.allocations.update', $e) }}" class="d-inline" data-confirm="Reset {{ $e->name }}'s {{ $year }} leave to the standard entitlement?" data-confirm-title="Reset leave">@csrf @method('PUT')
                            <input type="hidden" name="year" value="{{ $year }}"><input type="hidden" name="reset" value="1">
                            <button class="btn btn-sm btn-outline-secondary" title="Reset to standard"><i class="bi bi-arrow-counterclockwise"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ 7 + $types->count() }}" class="text-center text-muted py-4">No employees on the rolls in {{ $year }}.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
