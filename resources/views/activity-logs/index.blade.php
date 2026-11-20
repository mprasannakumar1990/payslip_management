@extends('layouts.app')
@section('title', 'Activity Log')
@section('content')
<div class="card">
    <div class="card-header">
        <form class="d-flex flex-wrap gap-2 auto-filter" method="GET">
            @include('partials.year-select', ['year' => $year])
            <select name="month" class="form-select form-select-sm" style="max-width:140px">
                <option value="">Whole year</option>
                @foreach (range(1, 12) as $m)<option value="{{ $m }}" @selected(request('month') == $m)>{{ month_name($m) }}</option>@endforeach
            </select>
            <select name="user_id" class="form-select form-select-sm" style="max-width:200px">
                <option value="">All users</option>
                @foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>@endforeach
            </select>
            <select name="action" class="form-select form-select-sm" style="max-width:160px">
                <option value="">All modules</option>
                @foreach ($actions as $a)<option @selected(request('action') === $a)>{{ $a }}</option>@endforeach
            </select>
            <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:240px" placeholder="Search text">
            <a href="{{ route('activity-logs.index') }}" class="btn btn-sm btn-link">Clear</a>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 table-stack">
            <thead><tr><th>Date</th><th>User</th><th>Module</th><th>Description</th><th>IP</th></tr></thead>
            <tbody>
            @forelse ($logs as $l)
                <tr>
                    <td class="text-nowrap small">{{ $l->created_at->format('d M Y H:i:s') }}</td>
                    <td>{{ $l->user?->name ?? '—' }}</td>
                    <td><span class="badge bg-light text-dark">{{ $l->action }}</span></td>
                    <td>{{ $l->description }}</td>
                    <td class="small text-muted">{{ $l->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No activity.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body py-2">{{ $logs->links() }}</div>
</div>
@endsection
