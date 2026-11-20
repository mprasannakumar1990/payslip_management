@extends('layouts.app')
@section('title', $user->exists ? 'Edit Account' : 'New Admin Account')
@section('back', route('users.index'))
@section('content')
<div class="card" style="max-width:720px">
    <div class="card-body">
        <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
            @csrf @if($user->exists) @method('PUT') @endif
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Name *</label><input name="name" class="form-control" value="{{ old('name', $user->name) }}" required></div>
                <div class="col-md-6"><label class="form-label">Email (login) *</label><input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone', $user->phone) }}"></div>
                <div class="col-md-6">
                    <label class="form-label">Role *</label>
                    <select name="role" class="form-select" @disabled($user->id === auth()->id())>
                        <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin — manages employees, leave, OT and payslips</option>
                        <option value="super_admin" @selected(old('role', $user->role) === 'super_admin')>Super Admin — full access incl. accounts &amp; settings</option>
                    </select>
                    @if($user->id === auth()->id())<input type="hidden" name="role" value="{{ $user->role }}">@endif
                </div>
                <div class="col-md-6"><label class="form-label">{{ $user->exists ? 'New password (leave blank to keep)' : 'Password *' }}</label><input type="password" name="password" class="form-control" @required(! $user->exists) autocomplete="new-password"></div>
                <div class="col-md-6"><label class="form-label">Confirm password</label><input type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" @checked(old('is_active', $user->is_active)) @disabled($user->id === auth()->id())>
                        <label class="form-check-label" for="active">Account active (can log in)</label>
                        @if($user->id === auth()->id())<input type="hidden" name="is_active" value="1">@endif
                    </div>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary">Save</button>
                <a href="{{ route('users.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
