@extends('layouts.app')
@section('title', 'Leave Requests')
@section('content')
<ul class="nav nav-pills mb-3">
    @foreach (['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $k => $label)
        <li class="nav-item">
            <a class="nav-link {{ request('status', '') === $k ? 'active' : '' }}" href="{{ route('leave-requests.index', array_merge(request()->except('status', 'page'), $k ? ['status' => $k] : [])) }}">
                {{ $label }} @php($c = $k ? ($counts[$k] ?? 0) : $counts->sum())@if($c)<span class="badge bg-light text-dark">{{ $c }}</span>@endif
            </a>
        </li>
    @endforeach
</ul>
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        <form class="d-flex flex-wrap gap-2 flex-grow-1 auto-filter" method="GET">
            <input type="hidden" name="status" value="{{ request('status') }}">
            <select name="employee_id" class="form-select form-select-sm select-search" style="min-width:230px">
                <option value="">All employees</option>
                @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(request('employee_id') == $e->id)>{{ $e->emp_code }} - {{ $e->name }}</option>@endforeach
            </select>
            <select name="leave_type_id" class="form-select form-select-sm" style="max-width:150px">
                <option value="">All types</option>
                @foreach ($types as $t)<option value="{{ $t->id }}" @selected(request('leave_type_id') == $t->id)>{{ $t->code }} - {{ $t->name }}</option>@endforeach
            </select>
            @include('partials.year-select', ['year' => $year])
            <select name="month" class="form-select form-select-sm" style="max-width:150px">
                <option value="">Whole year</option>
                @foreach (range(1, 12) as $m)<option value="{{ $m }}" @selected($m == $month)>{{ month_name($m) }}</option>@endforeach
            </select>
            <a href="{{ route('leave-requests.index') }}" class="btn btn-sm btn-link">Clear</a>
        </form>
        <a href="{{ route('leave-requests.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> New leave</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-stack">
            <thead><tr><th>Employee</th><th>Type</th><th>From</th><th>To</th><th class="num">Days</th><th>Reason</th><th>Status</th><th>By</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse ($requests as $r)
                <tr>
                    <td><a href="{{ route('employees.show', $r->employee_id) }}">{{ $r->employee->name }}</a><div class="small text-muted">{{ $r->employee->emp_code }}</div></td>
                    <td><span class="badge {{ $r->leaveType->is_paid ? 'bg-primary' : 'bg-danger' }}">{{ $r->leaveType->code }}</span></td>
                    <td class="text-nowrap">{{ $r->from_date->format('d M Y') }}</td>
                    <td class="text-nowrap">{{ $r->to_date->format('d M Y') }} @if($r->half_day)<span class="badge bg-light text-dark">½</span>@endif</td>
                    <td class="num">{{ num_clean($r->days) }}</td>
                    <td class="small" style="max-width:220px">{{ $r->reason }} @if($r->admin_remarks)<div class="text-muted fst-italic">{{ $r->admin_remarks }}</div>@endif</td>
                    <td><span class="badge {{ ['pending' => 'bg-warning text-dark', 'approved' => 'bg-success', 'rejected' => 'bg-danger', 'cancelled' => 'bg-secondary'][$r->status] }}">{{ ucfirst($r->status) }}</span></td>
                    <td class="small text-muted">{{ $r->actioner?->name ?? $r->creator?->name }}</td>
                    <td class="text-end text-nowrap">
                        @if ($r->status === 'pending')
                            <form method="POST" action="{{ route('leave-requests.approve', $r) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success" title="Approve"><i class="bi bi-check-lg"></i></button></form>
                            <form method="POST" action="{{ route('leave-requests.reject', $r) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-danger" title="Reject"><i class="bi bi-x-lg"></i></button></form>
                        @elseif ($r->status === 'approved')
                            <form method="POST" action="{{ route('leave-requests.cancel', $r) }}" class="d-inline" data-confirm="Cancel this approved leave?">@csrf<button class="btn btn-sm btn-outline-secondary" title="Cancel"><i class="bi bi-slash-circle"></i></button></form>
                        @endif
                        <a href="{{ route('leave-requests.edit', $r) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('leave-requests.destroy', $r) }}" class="d-inline" data-confirm="Delete this leave record?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No leave requests.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body py-2">{{ $requests->links() }}</div>
</div>
@endsection
