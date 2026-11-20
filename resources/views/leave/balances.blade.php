@extends('layouts.app')
@section('title', 'Leave Balances')
@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        <form class="d-flex gap-2 flex-grow-1 auto-filter" method="GET">
            <select name="year" class="form-select form-select-sm" style="width:100px">@foreach (year_options() as $y)<option @selected($y == $year)>{{ $y }}</option>@endforeach</select>
            <select name="month" class="form-select form-select-sm" style="width:150px">
                @foreach (range(1, 12) as $m)<option value="{{ $m }}" @selected($m == $month)>up to {{ month_name($m) }}</option>@endforeach
            </select>
            
        </form>
        <a href="{{ route('leave.balances', ['year' => $year, 'month' => $month, 'export' => 1]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Export</a>
    </div>
    <div class="card-body small text-muted py-2">
        Yearly entitlement: {{ $types->map(fn ($t) => $t->code.' '.num_clean($t->yearly_quota))->implode(' + ') }} = {{ num_clean(setting('annual_leave_pool')) }} days (pro-rated for joiners). Leave without a specific type (monthly register entries) is charged SL → CL → EL. Usage beyond the entitlement is Loss of Pay.
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover table-register mb-0">
            <thead>
                <tr>
                    <th rowspan="2">Emp ID</th><th rowspan="2">Name</th>
                    @foreach ($types as $t)<th colspan="3" class="text-center">{{ $t->name }}</th>@endforeach
                    <th colspan="3" class="text-center">Total</th><th rowspan="2" class="num">LOP</th>
                </tr>
                <tr>
                    @foreach ($types as $t)<th class="num">Quota</th><th class="num">Used</th><th class="num">Bal.</th>@endforeach
                    <th class="num">Entitled</th><th class="num">Used</th><th class="num">Bal.</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td><a href="{{ route('employees.show', [$r['employee'], 'year' => $year]) }}">{{ $r['employee']->emp_code }}</a></td>
                    <td>{{ $r['employee']->name }}</td>
                    @foreach ($types as $t)
                        @php($x = collect($r['b']['types'])->firstWhere('code', $t->code))
                        <td class="num text-muted">{{ num_clean($x['quota'] ?? 0) }}</td>
                        <td class="num">{{ num_clean($x['used'] ?? 0) }}</td>
                        <td class="num fw-semibold {{ ($x['balance'] ?? 0) <= 0 ? 'text-danger' : '' }}">{{ num_clean($x['balance'] ?? 0) }}</td>
                    @endforeach
                    <td class="num text-muted">{{ num_clean($r['b']['pool']) }}</td>
                    <td class="num">{{ num_clean($r['b']['used']) }}</td>
                    <td class="num fw-bold">{{ num_clean($r['b']['balance']) }}</td>
                    <td class="num {{ $r['b']['excess'] + $r['b']['lop'] > 0 ? 'text-danger fw-bold' : '' }}">{{ num_clean($r['b']['excess'] + $r['b']['lop']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
