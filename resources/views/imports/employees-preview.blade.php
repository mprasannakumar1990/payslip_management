@extends('layouts.app')
@section('title', 'Import Employees — verify')
@section('back', route('imports.index'))
@section('content')
@php($ok = collect($rows)->where('errors', []))
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center py-2">
        <span class="badge bg-primary-subtle text-primary-emphasis fs-6"><i class="bi bi-1-circle"></i> Upload</span>
        <i class="bi bi-chevron-right text-muted"></i>
        <span class="badge bg-primary fs-6"><i class="bi bi-2-circle"></i> Verify</span>
        <i class="bi bi-chevron-right text-muted"></i>
        <span class="badge bg-light text-dark fs-6"><i class="bi bi-3-circle"></i> Confirm &amp; import</span>
        <span class="ms-md-auto small"><span class="text-success fw-semibold">{{ $ok->where('action', 'create')->count() }} new</span> · <span class="text-primary fw-semibold">{{ $ok->where('action', 'update')->count() }} updates</span> · {{ $ok->where('action', 'update')->filter(fn ($r) => ! $r['changes'])->count() }} unchanged</span>
    </div>
</div>
@include('imports._summary')

<div class="card mb-3">
    <div class="table-responsive">
        <table class="table table-sm table-register align-middle mb-0">
            <thead><tr><th>Status</th><th>Action</th><th>Emp ID</th><th>Name</th><th>Designation</th><th>Plant</th><th>Joined</th><th class="num">Basic</th><th>Email</th><th>Changes (old → new)</th></tr></thead>
            @foreach ($rows as $r)
                <tr class="{{ $r['errors'] ? 'table-danger' : ($r['warnings'] ? 'table-warning' : '') }}">
                    <td style="min-width:200px; white-space:normal">
                        @if ($r['errors'])<span class="badge bg-danger">Error — skipped</span>@elseif ($r['warnings'])<span class="badge bg-warning text-dark">Check</span>@else<span class="badge bg-success">OK</span>@endif
                        @foreach ($r['errors'] as $m)<div class="issue text-danger"><i class="bi bi-x-circle"></i> {{ $m }}</div>@endforeach
                        @foreach ($r['warnings'] as $m)<div class="issue text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> {{ $m }}</div>@endforeach
                    </td>
                    <td><span class="badge {{ $r['action'] === 'create' ? 'bg-success' : 'bg-primary' }}">{{ $r['action'] === 'create' ? 'new' : 'update' }}</span></td>
                    <td class="fw-semibold">{{ $r['emp_code'] }}</td><td>{{ $r['name'] }}</td><td>{{ $r['designation'] ?? '' }}</td><td>{{ $r['ot_group'] ?? '' }}</td>
                    <td>{{ $r['date_of_joining'] ?? '' }}</td>
                    <td class="num">{{ isset($r['basic_salary']) ? inr($r['basic_salary'], 0) : '' }}</td><td>{{ $r['email'] ?? '' }}</td>
                    <td class="small" style="white-space:normal; min-width:240px">
                        @if ($r['action'] === 'create')<span class="text-success">New employee</span>
                        @elseif ($r['changes']){!! collect($r['changes'])->map(fn ($c) => e($c))->implode('<br>') !!}
                        @else<span class="text-muted">No changes</span>@endif
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
</div>

<form method="POST" action="{{ route('imports.employees.store') }}" id="importForm"
      data-confirm="Import {{ $ok->count() }} employee rows ({{ $ok->where('action', 'create')->count() }} new, {{ $ok->where('action', 'update')->count() }} updates)? Rows with errors are skipped."
      data-confirm-title="Import employees" data-confirm-level="double"
      data-confirm-final="Existing employee records will be overwritten with the values shown in the preview."
      data-busy="Importing employees…">
    @csrf
    <input type="hidden" name="payload" value="{{ $payload }}">
    <div class="card">
        <div class="card-body d-flex flex-wrap gap-3 align-items-center">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="verified" value="1" id="verifiedEmp" onchange="document.getElementById('importEmpBtn').disabled = !this.checked || {{ $ok->isEmpty() ? 'true' : 'false' }}">
                <label class="form-check-label fw-semibold" for="verifiedEmp">I have verified the preview (new employees and changes are correct)</label>
            </div>
            <a href="{{ route('imports.index') }}" class="btn btn-light ms-auto">Cancel</a>
            <button class="btn btn-primary" id="importEmpBtn" disabled><i class="bi bi-cloud-check"></i> Import {{ $ok->count() }} employees</button>
        </div>
    </div>
</form>
@endsection
