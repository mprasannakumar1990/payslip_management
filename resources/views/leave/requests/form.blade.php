@extends('layouts.app')
@section('title', $leave->exists ? 'Edit Leave' : 'New Leave')
@section('back', route('leave-requests.index'))
@section('content')
<div class="card" style="max-width:820px">
    <div class="card-body">
        <form method="POST" action="{{ $leave->exists ? route('leave-requests.update', $leave) : route('leave-requests.store') }}" id="leave-form">
            @csrf @if($leave->exists) @method('PUT') @endif
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label">Employee *</label>
                    <select name="employee_id" class="form-select select-search" required>
                        <option value="">Select employee</option>
                        @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(old('employee_id', $leave->employee_id) == $e->id)>{{ $e->emp_code }} - {{ $e->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Leave type *</label>
                    <select name="leave_type_id" class="form-select" required>
                        @foreach ($types as $t)<option value="{{ $t->id }}" @selected(old('leave_type_id', $leave->leave_type_id) == $t->id)>{{ $t->code }} — {{ $t->name }}{{ $t->is_paid ? '' : ' (unpaid)' }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">From *</label><input type="date" name="from_date" id="from" class="form-control" value="{{ old('from_date', $leave->from_date?->toDateString()) }}" required></div>
                <div class="col-md-4"><label class="form-label">To *</label><input type="date" name="to_date" id="to" class="form-control" value="{{ old('to_date', $leave->to_date?->toDateString()) }}" required></div>
                <div class="col-md-4">
                    <label class="form-label">Days</label>
                    <input type="number" step="0.5" min="0.5" name="days" id="days" class="form-control" value="{{ old('days', $leave->exists ? $leave->days : '') }}" placeholder="auto">
                    <div class="form-text">Auto-calculated excluding weekly off; edit for holidays.</div>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="half_day" value="0">
                        <input class="form-check-input" type="checkbox" name="half_day" value="1" id="half" @checked(old('half_day', $leave->half_day))>
                        <label class="form-check-label" for="half">Half day</label>
                    </div>
                </div>
                <div class="col-12"><label class="form-label">Reason</label><textarea name="reason" rows="2" class="form-control">{{ old('reason', $leave->reason) }}</textarea></div>
                <div class="col-12"><label class="form-label">Admin remarks</label><input name="admin_remarks" class="form-control" value="{{ old('admin_remarks', $leave->admin_remarks) }}"></div>
                @unless ($leave->exists)
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input type="hidden" name="approve_now" value="0">
                            <input class="form-check-input" type="checkbox" name="approve_now" value="1" id="approve_now" @checked(old('approve_now', true))>
                            <label class="form-check-label" for="approve_now">Approve immediately (otherwise saved as <em>pending</em> for approval)</label>
                        </div>
                    </div>
                @else
                    <div class="col-12 small text-muted">Current status: <strong>{{ ucfirst($leave->status) }}</strong>. Use the approve / reject buttons on the list to change it.</div>
                @endunless
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary">Save</button>
                <a href="{{ route('leave-requests.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
(function () {
    const from = document.getElementById('from'), to = document.getElementById('to'), days = document.getElementById('days'), half = document.getElementById('half');
    let touched = days.value !== '';
    days.addEventListener('input', () => touched = days.value !== '');
    function recalc() {
        if (half.checked) { to.value = from.value; }
        if (touched || !from.value || !to.value) return;
        if (to.value < from.value) { to.value = from.value; }
        fetch(`{{ route('leave-requests.calc-days') }}?from=${from.value}&to=${to.value}&half_day=${half.checked ? 1 : 0}`)
            .then(r => r.json()).then(d => { days.placeholder = d.days + ' (auto)'; });
    }
    [from, to, half].forEach(el => el.addEventListener('change', recalc));
    recalc();
})();
</script>
@endpush
