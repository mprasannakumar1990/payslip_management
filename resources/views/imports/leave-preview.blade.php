@extends('layouts.app')
@section('title', "Import Leave Register — verify $year")
@section('back', route('imports.index'))
@section('content')
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center py-2">
        <span class="badge bg-primary-subtle text-primary-emphasis fs-6"><i class="bi bi-1-circle"></i> Upload</span>
        <i class="bi bi-chevron-right text-muted"></i>
        <span class="badge bg-primary fs-6"><i class="bi bi-2-circle"></i> Verify</span>
        <i class="bi bi-chevron-right text-muted"></i>
        <span class="badge bg-light text-dark fs-6"><i class="bi bi-3-circle"></i> Confirm &amp; import</span>
        <div class="ms-md-auto">@include('imports._sheet-switch', ['route' => route('imports.leave.preview'), 'params' => ['token' => $token, 'year' => $year]])</div>
    </div>
</div>

@if ($fatal)
    <div class="alert alert-danger"><strong>The file could not be imported.</strong> {{ $fatal }} <a href="{{ route('imports.index') }}">Upload another file</a> or choose a different sheet above.</div>
@else
    @include('imports._summary')
    <div class="alert alert-danger d-none" id="dupWarning"><i class="bi bi-people"></i> The same employee is selected on more than one row (highlighted in red). Change or untick one of them.</div>

    <form method="POST" action="{{ route('imports.leave.store') }}" id="importForm"
          data-confirm="Import the {{ $year }} leave register for the selected employees? Previously imported monthly values will be replaced."
          data-confirm-title="Import leave register" data-confirm-level="double"
          data-confirm-final="Leave days affect leave balances, Loss of Pay and earned leave. Please confirm the preview is correct."
          data-busy="Importing leave…">
        @csrf
        <input type="hidden" name="year" value="{{ $year }}">
        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                <span><i class="bi bi-table"></i> Sheet “{{ $sheet }}” → leave days in {{ $year }}</span>
                <span class="small text-muted ms-auto">
                    <span class="badge cell-error text-danger">invalid</span> <span class="badge cell-warning text-dark">check</span> <span class="badge cell-locked text-dark">finalized month (skipped)</span>
                    · empty = unchanged, 0 = clear
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-register align-middle mb-0">
                    <thead><tr>
                        <th><input type="checkbox" class="form-check-input" id="toggleAll" checked title="Select all"></th>
                        <th>Status</th><th>Name in sheet</th><th style="min-width:220px">Employee</th>
                        @foreach (range(1, 12) as $m)<th class="num">{{ month_name($m, true) }}</th>@endforeach
                        <th class="num">Total</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($rows as $i => $r)
                        @php($err = (bool) $r['errors'])
                        <tr data-row data-error="{{ $err ? 1 : 0 }}" class="{{ $err ? 'table-danger' : ($r['warnings'] ? 'table-warning' : '') }}">
                            <td>
                                <input type="hidden" name="rows[{{ $i }}][include]" value="0">
                                <input type="checkbox" class="form-check-input" name="rows[{{ $i }}][include]" value="1" data-include @checked(! $err && $r['employee_id']) @disabled($err)>
                            </td>
                            <td style="min-width:200px; white-space:normal">
                                @if ($err)<span class="badge bg-danger">Error</span>@elseif ($r['warnings'])<span class="badge bg-warning text-dark">Check</span>@else<span class="badge bg-success">OK</span>@endif
                                @foreach ($r['errors'] as $m)<div class="issue text-danger"><i class="bi bi-x-circle"></i> {{ $m }}</div>@endforeach
                                @foreach ($r['warnings'] as $m)<div class="issue text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> {{ $m }}</div>@endforeach
                            </td>
                            <td>{{ $r['name'] }}
                                <input type="hidden" name="rows[{{ $i }}][sheet_name]" value="{{ $r['name'] }}">
                                <input type="hidden" name="rows[{{ $i }}][how]" value="{{ $r['how'] }}">
                            </td>
                            <td>
                                <select name="rows[{{ $i }}][employee_id]" class="form-select form-select-sm" data-emp>
                                    <option value="">— skip —</option>
                                    @foreach ($employees as $e)<option value="{{ $e->id }}" @selected($r['employee_id'] == $e->id)>{{ $e->emp_code }} - {{ $e->name }}</option>@endforeach
                                </select>
                            </td>
                            @foreach ($r['months'] as $m => $v)
                                @php($state = $r['cell'][$m] ?? null)
                                <td><input type="number" step="0.5" min="0" max="31" name="rows[{{ $i }}][m][{{ $m }}]"
                                           value="{{ $v === null ? (isset($r['invalid'][$m]) ? '' : '') : num_clean($v) }}"
                                           title="{{ isset($r['invalid'][$m]) ? 'In file: '.$r['invalid'][$m] : '' }}"
                                           class="form-control form-control-sm text-end {{ $state ? 'cell-'.$state : '' }}" style="width:62px" @disabled($state === 'locked')></td>
                            @endforeach
                            <td class="num fw-semibold">{{ num_clean(array_sum(array_filter($r['months']))) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-3 align-items-center">
                <div class="form-check">
                    <input type="hidden" name="skip_with_requests" value="0">
                    <input class="form-check-input" type="checkbox" name="skip_with_requests" value="1" id="skipreq" checked>
                    <label class="form-check-label" for="skipreq">Skip months where the employee already has approved leave requests (avoids counting leave twice)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="verified" value="1" id="verified">
                    <label class="form-check-label fw-semibold" for="verified">I have verified the preview</label>
                </div>
                <a href="{{ route('imports.index') }}" class="btn btn-light ms-auto">Cancel</a>
                <button class="btn btn-primary" id="importBtn" disabled><i class="bi bi-cloud-check"></i> Import leave register</button>
            </div>
        </div>
    </form>
@endif
@endsection
@push('scripts')
    @include('imports._verify')
@endpush
