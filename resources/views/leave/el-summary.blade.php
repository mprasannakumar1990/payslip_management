@extends('layouts.app')
@section('title', "Staff's Earn Leave Balance — $fromYear to $toYear")
@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center no-print">
        <form class="d-flex gap-2 flex-grow-1 align-items-center auto-filter" method="GET">
            <span class="small">From</span><select name="from" class="form-select form-select-sm" style="width:100px">@foreach (year_options() as $y)<option @selected($y == $fromYear)>{{ $y }}</option>@endforeach</select>
            <span class="small">to</span><select name="to" class="form-select form-select-sm" style="width:100px">@foreach (year_options() as $y)<option @selected($y == $toYear)>{{ $y }}</option>@endforeach</select>
            
        </form>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Print</button>
        <a href="{{ route('leave.el-summary', ['from' => $fromYear, 'to' => $toYear, 'export' => 1]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Export</a>
    </div>
    <div class="card-body py-2 small text-muted">EL days credited per year (manual entries in <span class="text-info">blue</span>, current year provisional in <span class="text-warning">amber</span>). EL amount = Total EL × per day (Basic ÷ {{ num_clean(setting('working_days')) }}). Edit a year from the employee's page.</div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-register mb-0">
            <thead><tr>
                <th>Sl</th><th>Name</th><th>Date of Joining</th><th class="num">Before {{ $fromYear }}</th>
                @foreach (range($fromYear, $toYear) as $y)<th class="num">{{ $y }}</th>@endforeach
                <th class="num">Total EL</th><th class="num">Per day</th><th class="num">EL Amount</th><th class="num">KRW</th>
            </tr></thead>
            <tbody>
            @foreach ($rows as $i => $r)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><a href="{{ route('employees.show', $r['employee']) }}">{{ $r['employee']->name }}</a></td>
                    <td>{{ $r['employee']->date_of_joining?->format('d-M-y') }}</td>
                    <td class="num text-muted">{{ $r['earlier'] ? num_clean($r['earlier']) : '' }}</td>
                    @foreach ($r['years'] as $h)
                        <td class="num {{ $h && $h['source'] === 'manual' ? 'text-info' : '' }} {{ $h && $h['provisional'] ? 'text-warning' : '' }}">{{ $h ? num_clean($h['days']) : '' }}</td>
                    @endforeach
                    <td class="num fw-bold">{{ num_clean($r['total']) }}</td>
                    <td class="num">{{ inr($r['per_day'], 0) }}</td>
                    <td class="num fw-semibold">{{ inr($r['amount']) }}</td>
                    <td class="num text-muted">{{ inr($r['amount'] * $krw, 0) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="{{ 4 + ($toYear - $fromYear + 1) }}">Grand Total</td>
                <td class="num">{{ num_clean($rows->sum('total')) }}</td><td></td><td class="num">{{ inr($rows->sum('amount')) }}</td><td class="num">{{ inr($rows->sum('amount') * $krw, 0) }}</td>
            </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
