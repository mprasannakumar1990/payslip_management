@extends('layouts.app')
@section('title', 'My Profile')
@section('content')
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Profile details</div>
            <div class="card-body">
                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf @method('PUT')
                    <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $user->name) }}" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required></div>
                    <div class="mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone', $user->phone) }}"></div>
                    <div class="mb-3 text-muted small">Role: <strong>{{ $user->roleLabel() }}</strong> · Last login: {{ $user->last_login_at?->format('d M Y H:i') ?? '—' }}</div>
                    <button class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Change password</div>
            <div class="card-body">
                <form method="POST" action="{{ route('profile.password') }}">
                    @csrf @method('PUT')
                    <div class="mb-3"><label class="form-label">Current password</label><input type="password" name="current_password" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">New password</label><input type="password" name="password" class="form-control" required><div class="form-text">At least 8 characters with letters and numbers.</div></div>
                    <div class="mb-3"><label class="form-label">Confirm new password</label><input type="password" name="password_confirmation" class="form-control" required></div>
                    <button class="btn btn-primary">Change password</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
