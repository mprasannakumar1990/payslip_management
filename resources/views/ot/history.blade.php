@extends('layouts.app')
@section('title', 'OT History')
@section('content')
<div class="card">
    <div class="card-header">
        <form class="d-flex flex-wrap gap-2 align-items-center auto-filter" method="GET">
            <span class="small">From</span><input type="month" name="from" value="{{ $from->format('Y-m') }}" class="form-control form-control-sm" style="width:160px">
            <span class="small">to</span><input type="month" name="to" value="{{ $to->format('Y-m') }}" class="form-control form-control-sm" style="width:160px">
            <select name="employee_id" class="form-select form-select-sm select-search" style="min-width:220px"><option value="">All employees</option>@foreach ($employees as $e)<option value="{{ $e->id }}" @selected(request('employee_id') == $e->id)>{{ $e->emp_code }} - {{ $e->name }}</option>@endforeach</select>
            
        </form>
    </div>
    <div class="card-body py-2 small text-muted">Approved OT hours (normal + special) per month. Max 24 months.</div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-register mb-0">
            <thead><tr><th>Emp ID</th><th>Name</th>@foreach ($months as [$y, $m])<th class="num">{{ month_name($m, true) }} {{ substr($y, 2) }}</th>@endforeach<th class="num">Total</th></tr></thead>
            <tbody>
            @forelse ($entries->sortBy(fn ($l) => $l->first()->employee->emp_code) as $list)
                @php($emp = $list->first()->employee)
                @php($byMonth = $list->keyBy(fn ($e) => $e->year * 100 + $e->month))
                <tr>
                    <td>{{ $emp->emp_code }}</td><td>{{ $emp->name }}</td>
                    @foreach ($months as [$y, $m])
                        @php($en = $byMonth[$y * 100 + $m] ?? null)
                        <td class="num" title="{{ $en ? '2nd '.num_clean($en->second_shift_hours).' · 3rd '.num_clean($en->third_shift_hours) : '' }}">{{ $en ? num_clean($en->totalOt()) : '' }}</td>
                    @endforeach
                    <td class="num fw-bold">{{ num_clean($list->sum(fn ($e) => $e->totalOt())) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ count($months) + 3 }}" class="text-center text-muted py-4">No approved OT in this range.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
