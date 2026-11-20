@extends('layouts.app')
@section('title', "Staff's Leave Register — $year")
@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center no-print">
        <form class="d-flex gap-2 flex-grow-1 auto-filter" method="GET">
            <select name="year" class="form-select form-select-sm" style="width:100px">@foreach (year_options() as $y)<option @selected($y == $year)>{{ $y }}</option>@endforeach</select>
            
        </form>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Print</button>
        <a href="{{ route('leave.register', ['year' => $year, 'export' => 1]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Export</a>
    </div>
    <div class="card-body py-2 small text-muted">Staff's Earn Leave Balance for the period Jan {{ $year }} to Dec {{ $year }}. Monthly figures include approved leave requests and monthly register entries. EL credit = min({{ num_clean(setting('el_max_per_year')) }}, CL/SL/EL − Total).</div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-register mb-0">
            <thead><tr>
                <th>Sl</th><th>Name</th><th>Date of Joining</th>
                @foreach (range(1, 12) as $m)<th class="num">{{ month_name($m, true) }}</th>@endforeach
                <th class="num">Total Leave</th><th class="num">CL/SL/EL</th><th class="num">Total Less CL/SL/EL</th><th class="num">LOP leave</th><th class="num">EL credit</th>
            </tr></thead>
            <tbody>
            @foreach ($rows as $i => $r)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><a href="{{ route('employees.show', [$r['employee'], 'year' => $year]) }}">{{ $r['employee']->name }}</a></td>
                    <td>{{ $r['employee']->date_of_joining?->format('d-M-y') }}</td>
                    @foreach ($r['months'] as $d)<td class="num">{{ $d ? num_clean($d) : '' }}</td>@endforeach
                    <td class="num fw-semibold">{{ num_clean($r['total']) }}</td>
                    <td class="num text-muted">{{ num_clean($r['entitled']) }}</td>
                    <td class="num fw-bold {{ $r['balance'] < 0 ? 'text-danger' : '' }}">{{ num_clean($r['balance']) }}</td>
                    <td class="num {{ $r['lop'] ? 'text-danger' : '' }}">{{ $r['lop'] ? num_clean($r['lop']) : '' }}</td>
                    <td class="num">{{ num_clean($r['el_credit']) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3">Grand Total</td>
                @foreach (range(1, 12) as $m)<td class="num">{{ num_clean($rows->sum(fn ($r) => $r['months'][$m])) }}</td>@endforeach
                <td class="num">{{ num_clean($rows->sum('total')) }}</td><td></td><td></td><td class="num">{{ num_clean($rows->sum('lop')) }}</td><td class="num">{{ num_clean($rows->sum('el_credit')) }}</td>
            </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
