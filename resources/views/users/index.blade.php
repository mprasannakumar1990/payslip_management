@extends('layouts.app')
@section('title', 'Admin Accounts')
@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        <form class="d-flex gap-2 flex-grow-1 auto-filter" method="GET">
            <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:240px" placeholder="Search name / email">
            <select name="role" class="form-select form-select-sm" style="max-width:160px">
                <option value="">All roles</option>
                <option value="super_admin" @selected(request('role') === 'super_admin')>Super Admin</option>
                <option value="admin" @selected(request('role') === 'admin')>Admin</option>
            </select>
            
        </form>
        <a href="{{ route('users.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> New account</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-stack">
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Last login</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->name }} @if($user->id === auth()->id())<span class="badge bg-light text-dark">You</span>@endif</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->phone }}</td>
                    <td><span class="badge {{ $user->isSuperAdmin() ? 'bg-danger' : 'bg-primary' }}">{{ $user->roleLabel() }}</span></td>
                    <td><span class="badge {{ $user->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="small text-muted">{{ $user->last_login_at?->format('d M Y H:i') ?? '—' }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        @if($user->id !== auth()->id())
                            <form method="POST" action="{{ route('users.toggle', $user) }}" class="d-inline">@csrf
                                <button class="btn btn-sm btn-outline-warning" title="{{ $user->is_active ? 'Deactivate' : 'Activate' }}"><i class="bi {{ $user->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i></button>
                            </form>
                            <form method="POST" action="{{ route('users.destroy', $user) }}" class="d-inline" data-confirm="Delete this account?">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted text-center py-4">No accounts found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body py-2">{{ $users->links() }}</div>
</div>
@endsection
