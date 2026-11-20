@extends('layouts.app')
@section('title', 'Employee Registration')
@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        <form class="d-flex flex-wrap gap-2 flex-grow-1 auto-filter" method="GET">
            @include('partials.year-select', ['year' => $year])
            <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:240px" placeholder="Search ID / name / designation">
            <select name="group" class="form-select form-select-sm" style="max-width:170px">
                <option value="">All plants</option>
                @foreach ($groups as $g)<option @selected(request('group') === $g)>{{ $g }}</option>@endforeach
            </select>
            <select name="status" class="form-select form-select-sm" style="max-width:140px">
                @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'exited' => 'Exited', 'all' => 'All'] as $k => $v)
                    <option value="{{ $k }}" @selected(request('status', $defaultStatus) === $k)>{{ $v }}</option>
                @endforeach
            </select>
            <select name="email" class="form-select form-select-sm" style="max-width:160px">
                <option value="">Any email</option>
                <option value="missing" @selected(request('email') === 'missing')>Missing email</option>
                <option value="has" @selected(request('email') === 'has')>Has email</option>
            </select>
            <a href="{{ route('employees.index') }}" class="btn btn-sm btn-link">Clear</a>
        </form>
        <a href="{{ route('employees.export', request()->query()) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Export</a>
        <a href="{{ route('imports.index') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-cloud-upload"></i> Import</a>
        <a href="{{ route('employees.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-person-plus"></i> Register employee</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-stack">
            <thead><tr><th>Emp ID</th><th>Name</th><th>Designation</th><th>Grade</th><th>Plant / Group</th><th>Joined</th><th class="num">Basic</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse ($employees as $e)
                <tr>
                    <td><a href="{{ route('employees.show', $e) }}" class="fw-semibold">{{ $e->emp_code }}</a></td>
                    <td>{{ $e->name }} @unless($e->email)<i class="bi bi-envelope-slash text-warning" title="No email address — payslip cannot be emailed"></i>@endunless</td>
                    <td class="small">{{ $e->designation }}</td>
                    <td>{{ $e->grade }}</td>
                    <td class="small">{{ $e->ot_group }}</td>
                    <td class="small">{{ $e->date_of_joining?->format('d-m-Y') }}</td>
                    <td class="num">{{ inr($e->basic_salary, 0) }}</td>
                    <td><span class="badge {{ ['active' => 'bg-success', 'inactive' => 'bg-secondary', 'exited' => 'bg-dark'][$e->status] ?? 'bg-light' }}">{{ ucfirst($e->status) }}</span></td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('employees.show', $e) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('employees.edit', $e) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No employees found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body py-2">{{ $employees->links() }}</div>
</div>
@endsection
