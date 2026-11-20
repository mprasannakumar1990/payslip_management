@extends('layouts.app')
@section('title', 'Monthly Leave Entry')
@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Record leave days for a month</div>
            <div class="card-body">
                <p class="small text-muted">Use this when you only have a monthly count (as in the Excel leave register) rather than specific dates. For dated leave, use <a href="{{ route('leave-requests.create') }}">Leave Requests</a>.</p>
                <form method="POST" action="{{ route('leave.adjustments.store') }}">@csrf
                    <div class="mb-2">
                        <label class="form-label">Employee *</label>
                        <select name="employee_id" class="form-select select-search" required>
                            <option value="">Select</option>
                            @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(old('employee_id') == $e->id)>{{ $e->emp_code }} - {{ $e->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label">Year *</label><select name="year" class="form-select">@foreach (year_options() as $y)<option @selected($y == old('year', $year))>{{ $y }}</option>@endforeach</select></div>
                        <div class="col-6"><label class="form-label">Month *</label>
                            <select name="month" class="form-select">@foreach (range(1, 12) as $m)<option value="{{ $m }}" @selected(old('month', working_period()->month) == $m)>{{ month_name($m) }}</option>@endforeach</select>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label">Days *</label><input type="number" step="0.5" min="0.5" name="days" class="form-control" value="{{ old('days') }}" required></div>
                        <div class="col-6"><label class="form-label">Type</label>
                            <select name="leave_type_id" class="form-select"><option value="">Unspecified</option>@foreach ($types as $t)<option value="{{ $t->id }}">{{ $t->code }}</option>@endforeach</select>
                        </div>
                    </div>
                    <div class="mb-3"><label class="form-label">Note</label><input name="note" class="form-control" value="{{ old('note') }}"></div>
                    <button class="btn btn-primary w-100">Add</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <form class="d-flex flex-wrap gap-2 auto-filter" method="GET">
                    <select name="year" class="form-select form-select-sm" style="width:100px">@foreach (year_options() as $y)<option @selected($y == $year)>{{ $y }}</option>@endforeach</select>
                    <select name="month" class="form-select form-select-sm" style="width:140px"><option value="">All months</option>@foreach (range(1, 12) as $m)<option value="{{ $m }}" @selected(request('month') == $m)>{{ month_name($m) }}</option>@endforeach</select>
                    <select name="employee_id" class="form-select form-select-sm select-search" style="min-width:220px"><option value="">All employees</option>@foreach ($employees as $e)<option value="{{ $e->id }}" @selected(request('employee_id') == $e->id)>{{ $e->emp_code }} - {{ $e->name }}</option>@endforeach</select>
                    
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 table-stack">
                    <thead><tr><th>Month</th><th>Employee</th><th>Type</th><th class="num">Days</th><th>Note</th><th></th></tr></thead>
                    @forelse ($items as $a)
                        <tr>
                            <td>{{ month_name($a->month, true) }} {{ $a->year }}</td>
                            <td>{{ $a->employee->emp_code }} — {{ $a->employee->name }}</td>
                            <td>{{ $a->leaveType?->code ?? '—' }}</td>
                            <td class="num">{{ num_clean($a->days) }}</td>
                            <td class="small text-muted">{{ $a->note }}</td>
                            <td class="text-end"><form method="POST" action="{{ route('leave.adjustments.destroy', $a) }}" data-confirm="Delete this entry?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No entries.</td></tr>
                    @endforelse
                </table>
            </div>
            <div class="card-body py-2">{{ $items->links() }}</div>
        </div>
    </div>
</div>
@endsection
