@extends('layouts.app')
@section('title', 'Import OT — verify '.month_name($month).' '.$year)
@section('back', route('imports.index'))
@section('content')
@php($period = sprintf('%04d-%02d', $year, $month))
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center py-2">
        <span class="badge bg-primary-subtle text-primary-emphasis fs-6"><i class="bi bi-1-circle"></i> Upload</span>
        <i class="bi bi-chevron-right text-muted"></i>
        <span class="badge bg-primary fs-6"><i class="bi bi-2-circle"></i> Verify</span>
        <i class="bi bi-chevron-right text-muted"></i>
        <span class="badge bg-light text-dark fs-6"><i class="bi bi-3-circle"></i> Confirm &amp; import</span>
        <div class="ms-md-auto">@include('imports._sheet-switch', ['route' => route('imports.ot.preview'), 'params' => ['token' => $token, 'period' => $period]])</div>
    </div>
</div>

@if ($fatal)
    <div class="alert alert-danger"><strong>The file could not be imported.</strong> {{ $fatal }} <a href="{{ route('imports.index') }}">Upload another file</a> or choose a different sheet above.</div>
@else
    @include('imports._summary')
    @if ($locked)
        <div class="alert alert-danger">Payroll for {{ month_name($month) }} {{ $year }} is finalized; OT for that month cannot be imported.</div>
    @endif
    <div class="alert alert-danger d-none" id="dupWarning"><i class="bi bi-people"></i> The same employee is selected on more than one row (highlighted in red). Change or untick one of them.</div>

    <form method="POST" action="{{ route('imports.ot.store') }}" id="importForm"
          data-confirm="Import OT hours for {{ month_name($month) }} {{ $year }}? Existing OT of the selected employees for this month will be replaced."
          data-confirm-title="Import OT" data-confirm-level="double"
          data-confirm-final="Please confirm that the hours in the preview are correct. They will be used to calculate {{ month_name($month) }} {{ $year }} salaries."
          data-busy="Importing OT…">
        @csrf
        <input type="hidden" name="period" value="{{ $period }}">
        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                <span><i class="bi bi-table"></i> Sheet “{{ $sheet }}” → OT for {{ month_name($month) }} {{ $year }}</span>
                <span class="small text-muted ms-auto">Hours must be numbers from 0 to {{ $maxHours }}. Rows with errors cannot be imported.</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr>
                        <th><input type="checkbox" class="form-check-input" id="toggleAll" checked title="Select all"></th>
                        <th>Status</th><th>Name in sheet</th><th style="min-width:230px">Employee</th>
                        <th class="num">2nd</th><th class="num">3rd</th><th class="num">Normal OT</th><th class="num">Spl OT</th><th>Remarks</th><th>Currently saved</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($rows as $i => $r)
                        @php($err = (bool) $r['errors'])
                        <tr data-row data-error="{{ $err ? 1 : 0 }}" class="{{ $err ? 'table-danger' : ($r['warnings'] ? 'table-warning' : '') }}">
                            <td>
                                <input type="hidden" name="rows[{{ $i }}][include]" value="0">
                                <input type="checkbox" class="form-check-input" name="rows[{{ $i }}][include]" value="1" data-include @checked(! $err && $r['employee_id']) @disabled($err)>
                            </td>
                            <td style="min-width:190px">
                                @if ($err)<span class="badge bg-danger">Error</span>@elseif ($r['warnings'])<span class="badge bg-warning text-dark">Check</span>@else<span class="badge bg-success">OK</span>@endif
                                @foreach ($r['errors'] as $m)<div class="issue text-danger"><i class="bi bi-x-circle"></i> {{ $m }}</div>@endforeach
                                @foreach ($r['warnings'] as $m)<div class="issue text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> {{ $m }}</div>@endforeach
                            </td>
                            <td>{{ $r['name'] }} {{ $r['code'] ? '('.$r['code'].')' : '' }}
                                <input type="hidden" name="rows[{{ $i }}][sheet_name]" value="{{ $r['name'] }}">
                                <input type="hidden" name="rows[{{ $i }}][how]" value="{{ $r['how'] }}">
                            </td>
                            <td>
                                <select name="rows[{{ $i }}][employee_id]" class="form-select form-select-sm" data-emp>
                                    <option value="">— skip —</option>
                                    @foreach ($employees as $e)<option value="{{ $e->id }}" @selected($r['employee_id'] == $e->id)>{{ $e->emp_code }} - {{ $e->name }}</option>@endforeach
                                </select>
                            </td>
                            @foreach (['second', 'third', 'normal', 'spl'] as $k)
                                <td><input type="number" step="0.5" min="0" max="{{ $maxHours }}" name="rows[{{ $i }}][{{ $k }}]" value="{{ num_clean($r[$k]) }}" class="form-control form-control-sm ot-input ms-auto"></td>
                            @endforeach
                            <td><input name="rows[{{ $i }}][remarks]" value="{{ $r['remarks'] }}" class="form-control form-control-sm" style="min-width:120px" maxlength="200"></td>
                            <td class="small text-muted text-nowrap">@isset($r['old']){{ implode(' / ', array_slice($r['old'], 0, 4)) }} <span class="badge bg-secondary">{{ $r['old'][4] }}</span><div class="issue">will be replaced</div>@else — @endisset</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-3 align-items-center">
                <select name="status" class="form-select" style="max-width:240px">
                    <option value="approved">Save as approved (paid in payroll)</option>
                    <option value="pending">Save as pending approval</option>
                </select>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="verified" value="1" id="verified">
                    <label class="form-check-label fw-semibold" for="verified">I have verified the preview (employees and hours are correct)</label>
                </div>
                <a href="{{ route('imports.index') }}" class="btn btn-light ms-auto">Cancel</a>
                <button class="btn btn-primary" id="importBtn" disabled @disabled($locked)><i class="bi bi-cloud-check"></i> Import OT</button>
            </div>
        </div>
    </form>
@endif
@endsection
@push('scripts')
    @include('imports._verify')
@endpush
