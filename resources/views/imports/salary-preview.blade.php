@extends('layouts.app')
@section('title', 'Import salary sheet — verify '.$label)
@section('back', route('imports.index'))
@section('content')
@php($period = sprintf('%04d-%02d', $year, $month))
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center py-2">
        <span class="badge bg-primary-subtle text-primary-emphasis fs-6"><i class="bi bi-1-circle"></i> Upload</span>
        <i class="bi bi-chevron-right text-muted"></i>
        <span class="badge bg-primary fs-6"><i class="bi bi-2-circle"></i> Verify</span>
        <i class="bi bi-chevron-right text-muted"></i>
        <span class="badge bg-light text-dark fs-6"><i class="bi bi-3-circle"></i> Choose &amp; import</span>
        <div class="ms-md-auto">@include('imports._sheet-switch', ['route' => route('imports.salary.preview'), 'params' => ['token' => $token, 'period' => $period]])</div>
    </div>
</div>

@if ($fatal)
    <div class="alert alert-danger"><strong>The sheet could not be read.</strong> {{ $fatal }} <a href="{{ route('imports.index') }}">Upload another file</a> or choose a different sheet above.</div>
@else
    @include('imports._summary')

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-columns-gap"></i> Columns found in “{{ $sheet }}”</div>
                <div class="card-body d-flex flex-wrap gap-1">
                    @foreach ($columns as $f => $h)<span class="badge bg-light" title="{{ $f }}">{{ $h }}</span>@endforeach
                    @foreach ($allowanceCols as $h)<span class="badge bg-primary" title="allowance column">{{ $h }}</span>@endforeach
                    <div class="w-100 small text-muted mt-1">Columns are recognised by their headings, so a new allowance column (for example “Increment 2027”) is picked up automatically and added to the system.</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-person-dash"></i> Active employees not in this sheet <span class="badge bg-warning">{{ $missing->count() }}</span></div>
                <div class="card-body small">
                    @forelse ($missing as $e)
                        <div><a href="{{ route('employees.edit', $e) }}">{{ $e->emp_code }} · {{ $e->name }}</a></div>
                    @empty
                        <span class="text-muted">Everybody on the rolls is in the sheet.</span>
                    @endforelse
                    @if ($missing->isNotEmpty())<div class="text-muted mt-2">If they have left, enter their exit date on the employee record. They are not changed by this import.</div>@endif
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <span><i class="bi bi-table"></i> {{ count($rows) }} employees in the sheet</span>
            <span class="small text-muted ms-auto">Grey = unchanged · each change shows old → new</span>
        </div>
        <div class="table-responsive" style="max-height:70vh">
            <table class="table table-sm align-middle mb-0">
                <thead class="sticky-top"><tr>
                    <th>Status</th><th>Emp ID</th><th>Name</th><th>In the system</th>
                    <th class="num">Basic</th><th class="num">Present</th><th class="num">Gross</th><th class="num">Deductions</th><th class="num">Net</th><th class="num">Allowances</th><th class="num">Total amount</th>
                    <th style="min-width:280px">Master data changes</th>
                </tr></thead>
                <tbody>
                @foreach ($rows as $r)
                    @php($err = (bool) $r['errors'])
                    <tr data-row data-error="{{ $err ? 1 : 0 }}" class="{{ $err ? 'table-danger' : ($r['warnings'] ? 'table-warning' : '') }}">
                        <td style="min-width:200px">
                            @if ($err)<span class="badge bg-danger">Error</span>@elseif ($r['warnings'])<span class="badge bg-warning">Check</span>@else<span class="badge bg-success">OK</span>@endif
                            @if ($r['action'] === 'create')<span class="badge bg-primary">New employee</span>@endif
                            @foreach ($r['errors'] as $m)<div class="issue text-danger"><i class="bi bi-x-circle"></i> {{ $m }}</div>@endforeach
                            @foreach ($r['warnings'] as $m)<div class="issue text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> {{ $m }}</div>@endforeach
                        </td>
                        <td class="fw-semibold">{{ $r['emp_code'] ?: '—' }}</td>
                        <td>{{ $r['name'] }}<div class="small text-muted">{{ $r['designation'] ?? '' }}</div></td>
                        <td class="small">{{ $r['current'] ?? '—' }}</td>
                        <td class="num">{{ inr($r['basic_salary'], 0) }}</td>
                        <td class="num">{{ num_clean($r['present_days']) }}{{ ($r['absent_days'] ?? 0) ? ' / '.num_clean($r['absent_days']).' abs' : '' }}</td>
                        <td class="num">{{ inr($r['gross_salary']) }}</td>
                        <td class="num">{{ inr($r['total_deduction'] ?? 0) }}</td>
                        <td class="num">{{ inr($r['net_salary']) }}</td>
                        <td class="num">{{ inr($r['total_allowance'] ?? 0) }}</td>
                        <td class="num fw-bold">{{ inr($r['total_amount'] ?? 0) }}</td>
                        <td class="small">
                            @if ($r['action'] === 'create')
                                <span class="text-primary">Will be registered (joined {{ $r['doj'] ? \Illuminate\Support\Carbon::parse($r['doj'])->format('d-m-Y') : '—' }})</span>
                            @elseif ($r['changes'])
                                @foreach ($r['changes'] as $c)<div>{{ $c }}</div>@endforeach
                            @else
                                <span class="text-muted">No change</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @php($ok = collect($rows)->where('errors', []))
                <tr class="total-row">
                    <td colspan="4">Total ({{ $ok->count() }} importable rows)</td>
                    <td class="num">{{ inr($ok->sum('basic_salary'), 0) }}</td><td></td>
                    <td class="num">{{ inr($ok->sum('gross_salary')) }}</td><td class="num">{{ inr($ok->sum('total_deduction')) }}</td>
                    <td class="num">{{ inr($ok->sum('net_salary')) }}</td><td class="num">{{ inr($ok->sum('total_allowance')) }}</td>
                    <td class="num">{{ inr($ok->sum('total_amount')) }}</td><td></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <form method="POST" action="{{ route('imports.salary.store') }}" id="salaryForm"
          data-confirm="Import the {{ $label }} salary sheet with the options you ticked? Rows with errors are skipped."
          data-confirm-title="Import salary sheet" data-confirm-level="double"
          data-confirm-final="Employee salary details are overwritten with the sheet values{{ $run ? ' and the existing '.$label.' payslips are replaced' : '' }}."
          data-busy="Importing the salary sheet…">
        @csrf
        <input type="hidden" name="period" value="{{ $period }}">
        <input type="hidden" name="payload" value="{{ $payload }}">
        <div class="card">
            <div class="card-header"><i class="bi bi-ui-checks"></i> What should be imported?</div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <div class="form-check"><input type="hidden" name="update_master" value="0"><input class="form-check-input" type="checkbox" name="update_master" value="1" id="o1" checked>
                        <label class="form-check-label fw-semibold" for="o1">Update employee salary details</label></div>
                    <div class="small text-muted">Basic, specials, other / general allowance, tax, PF / ESI, allowances, designation, grade, EPF / ESIC numbers. Used for future payroll.</div>
                </div>
                <div class="col-md-4">
                    <div class="form-check"><input type="hidden" name="create_new" value="0"><input class="form-check-input" type="checkbox" name="create_new" value="1" id="o2" checked>
                        <label class="form-check-label fw-semibold" for="o2">Register new employees ({{ collect($rows)->where('action', 'create')->where('errors', [])->count() }})</label></div>
                    <div class="small text-muted">Employees in the sheet that are not in the system yet.</div>
                </div>
                <div class="col-md-4">
                    <div class="form-check"><input type="hidden" name="save_payroll" value="0"><input class="form-check-input" type="checkbox" name="save_payroll" value="1" id="o3" checked @disabled($run?->isFinalized())>
                        <label class="form-check-label fw-semibold" for="o3">Save {{ $label }} payslips exactly as in the sheet</label></div>
                    <div class="small text-muted">
                        @if ($run?->isFinalized())<span class="text-danger">Payroll for {{ $label }} is finalized — re-open it first to replace it.</span>
                        @elseif ($run)A draft payroll for {{ $label }} exists ({{ $run->payslips_count }} payslips); the payslips of these employees are replaced.
                        @else Creates the {{ $label }} payroll as a draft: review it, then finalize and email.@endif
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex flex-wrap gap-3 align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="verified" value="1" id="verified" onchange="document.getElementById('importBtn').disabled = !this.checked">
                    <label class="form-check-label fw-semibold" for="verified">I have verified the preview (employees and figures are correct)</label>
                </div>
                <a href="{{ route('imports.index') }}" class="btn btn-light ms-auto">Cancel</a>
                <button class="btn btn-primary" id="importBtn" disabled><i class="bi bi-cloud-check"></i> Import {{ $summary['importable'] }} rows</button>
            </div>
        </div>
    </form>
@endif
@endsection
@push('scripts')
    @include('imports._colorize')
@endpush
@push('styles')
<style>.issue { font-size: .74rem; line-height: 1.25; }</style>
@endpush
