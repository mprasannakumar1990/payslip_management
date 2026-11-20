@extends('layouts.app')
@section('title', "Staff's OT & Allowances — ".month_name($month).' '.$year)
@section('back', route('ot.index'))
@section('content')
@php($period = sprintf('%04d-%02d', $year, $month))
<div class="card mb-3 no-print">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center py-2">
        <form class="d-flex gap-2 auto-filter" method="GET">
            @include('partials.period-select', ['p' => \Illuminate\Support\Carbon::create($year, $month, 1)])
            <select name="status" class="form-select form-select-sm" style="width:150px">
                <option value="">All statuses</option>
                @foreach (['approved', 'pending', 'rejected'] as $s)<option @selected(request('status') === $s)>{{ $s }}</option>@endforeach
            </select>
            
        </form>
        <div class="ms-auto d-flex gap-2">
            <a href="{{ route('ot.index', ['period' => $period]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit entries</a>
            <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Print</button>
            <a href="{{ route('ot.report', ['period' => $period, 'status' => request('status'), 'export' => 1]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Export</a>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body p-0">
        <h2 class="h6 text-center py-2 mb-0 d-none d-print-block">{{ setting('company_name') }} — Staff's OT &amp; Allowances Details for the Month of {{ month_name($month) }} {{ $year }}</h2>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead><tr><th>Sl No</th><th>Emp ID</th><th>Name</th><th class="num">2nd Shift</th><th class="num">3rd Shift</th><th class="num">Normal OT</th><th class="num">Spl OT</th><th class="num">Total OT</th><th>Remarks</th><th class="no-print">Status</th></tr></thead>
                <tbody>
                @php($i = 1)
                @forelse ($groups as $g => $list)
                    <tr class="group-row"><td colspan="10">{{ $g }} OT Calculations</td></tr>
                    @foreach ($list as $e)
                        <tr>
                            <td>{{ $i++ }}</td><td>{{ $e->employee->emp_code }}</td><td>{{ $e->employee->name }}</td>
                            <td class="num">{{ num_clean($e->second_shift_hours) }}</td><td class="num">{{ num_clean($e->third_shift_hours) }}</td>
                            <td class="num">{{ num_clean($e->normal_ot_hours) }}</td><td class="num">{{ num_clean($e->spl_ot_hours) }}</td>
                            <td class="num fw-semibold">{{ num_clean($e->totalOt()) }}</td><td class="small">{{ $e->remarks }}</td>
                            <td class="no-print"><span class="badge bg-light text-dark">{{ $e->status }}</span></td>
                        </tr>
                    @endforeach
                    @php($s = $summary[$g])
                    <tr class="total-row"><td colspan="3">Total</td><td class="num">{{ num_clean($s['second']) }}</td><td class="num">{{ num_clean($s['third']) }}</td><td class="num">{{ num_clean($s['normal']) }}</td><td class="num">{{ num_clean($s['spl']) }}</td><td class="num">{{ num_clean($s['total']) }}</td><td colspan="2"></td></tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">No OT entries for this month.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if ($summary->isNotEmpty())
<div class="card" style="max-width:860px">
    <div class="card-header">Summary</div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
            <thead><tr><th>Particulars</th><th class="num">2nd Shift</th><th class="num">3rd Shift</th><th class="num">Normal OT</th><th class="num">Spl OT</th><th class="num">Total OT</th><th class="num">Shift allowance ₹</th></tr></thead>
            @foreach ($summary as $g => $s)
                <tr><td>{{ $g }}</td><td class="num">{{ num_clean($s['second']) }}</td><td class="num">{{ num_clean($s['third']) }}</td><td class="num">{{ num_clean($s['normal']) }}</td><td class="num">{{ num_clean($s['spl']) }}</td><td class="num">{{ num_clean($s['total']) }}</td><td class="num">{{ inr($s['second'] * $rate2 + $s['third'] * $rate3) }}</td></tr>
            @endforeach
            <tr class="total-row"><td>Total</td><td class="num">{{ num_clean($grand['second']) }}</td><td class="num">{{ num_clean($grand['third']) }}</td><td class="num">{{ num_clean($grand['normal']) }}</td><td class="num">{{ num_clean($grand['spl']) }}</td><td class="num">{{ num_clean($grand['total']) }}</td><td class="num">{{ inr($grand['second'] * $rate2 + $grand['third'] * $rate3) }}</td></tr>
        </table>
    </div>
    <div class="card-body d-none d-print-flex justify-content-around pt-5"><span>Prepared</span><span>MD</span></div>
</div>
@endif
@endsection
