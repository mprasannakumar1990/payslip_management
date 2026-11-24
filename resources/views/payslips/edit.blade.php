@extends('layouts.app')
@section('title', 'Edit Payslip — '.$p->name.' — '.$p->run->periodLabel())
@section('back', route('payroll.show', $p->payroll_run_id))
@section('content')
@php($f = fn ($k) => old($k, num_clean($p->$k)))
<form method="POST" action="{{ route('payslips.update', $p) }}">
    @csrf @method('PUT')
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Attendance &amp; basic</div>
                <div class="card-body row g-2">
                    <div class="col-12"><label class="form-label">Basic salary</label><input type="number" step="0.01" name="basic_salary" class="form-control" value="{{ $f('basic_salary') }}"></div>
                    <div class="col-4"><label class="form-label">Present</label><input type="number" step="0.5" name="present_days" class="form-control" value="{{ $f('present_days') }}"></div>
                    <div class="col-4"><label class="form-label">Absent</label><input type="number" step="0.5" name="absent_days" class="form-control" value="{{ $f('absent_days') }}"></div>
                    <div class="col-4"><label class="form-label">LOP</label><input type="number" step="0.5" name="lop_days" class="form-control" value="{{ $f('lop_days') }}"></div>
                    <div class="col-12 form-text">Working days: {{ num_clean($p->working_days) }}. Basic earned = Basic ÷ working days × present days.</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Additions</div>
                <div class="card-body row g-2">
                    <div class="col-6"><label class="form-label">2nd shift hrs</label><input type="number" step="0.5" name="second_shift_hours" class="form-control" value="{{ $f('second_shift_hours') }}"></div>
                    <div class="col-6"><label class="form-label">3rd shift hrs</label><input type="number" step="0.5" name="third_shift_hours" class="form-control" value="{{ $f('third_shift_hours') }}"></div>
                    <div class="col-6"><label class="form-label">OT hours</label><input type="number" step="0.5" name="ot_hours" class="form-control" value="{{ $f('ot_hours') }}"></div>
                    <div class="col-6"><label class="form-label">OT multiplier</label><input type="number" step="0.01" name="ot_multiplier" class="form-control" value="{{ $f('ot_multiplier') }}"></div>
                    <div class="col-12"><label class="form-label">OT amount override</label><input type="number" step="0.01" name="ot_amount_manual" class="form-control" value="{{ old('ot_amount_manual', $p->ot_amount_manual) }}" placeholder="calculated: {{ inr($p->ot_amount_manual === null ? $p->ot_amount : $p->per_day / (float) setting('ot_hours_per_day', 8) * $p->ot_multiplier * $p->ot_hours) }}"><div class="form-text">Leave blank to use the calculated amount.</div></div>
                    <div class="col-6"><label class="form-label">Spl OT amount</label><input type="number" step="0.01" name="spl_ot_amount" class="form-control" value="{{ $f('spl_ot_amount') }}"></div>
                    <div class="col-6"><label class="form-label">Manager special</label><input type="number" step="0.01" name="manager_special" class="form-control" value="{{ $f('manager_special') }}"></div>
                    <div class="col-6"><label class="form-label">Factory special</label><input type="number" step="0.01" name="factory_special" class="form-control" value="{{ $f('factory_special') }}"></div>
                    <div class="col-6"><label class="form-label">Other allowance</label><input type="number" step="0.01" min="0" name="other_allowance" class="form-control" value="{{ $f('other_allowance') }}"></div>
                    <div class="col-6"><label class="form-label">General allowances</label><input type="number" step="0.01" min="0" name="general_allowance" class="form-control" value="{{ $f('general_allowance') }}"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Deductions</div>
                <div class="card-body row g-2">
                    <div class="col-6"><label class="form-label">TAX</label><input type="number" step="0.01" name="tax" class="form-control" value="{{ $f('tax') }}"></div>
                    <div class="col-6"><label class="form-label">Food coupon</label><input type="number" step="0.01" min="0" name="food_coupon" class="form-control" value="{{ $f('food_coupon') }}"></div>
                    <div class="col-6"><label class="form-label">Advance</label><input type="number" step="0.01" name="advance" class="form-control" value="{{ $f('advance') }}"></div>
                    <div class="col-6"><label class="form-label">Other / misc</label><input type="number" step="0.01" name="other_deduction" class="form-control" value="{{ $f('other_deduction') }}"></div>
                    <div class="col-12">
                        <div class="form-check"><input type="hidden" name="pf_applicable" value="0"><input class="form-check-input" type="checkbox" name="pf_applicable" value="1" id="pf" @checked(old('pf_applicable', $p->pf > 0 || $p->employee?->pf_applicable))><label class="form-check-label" for="pf">Deduct PF ({{ num_clean(setting('pf_percent')) }}%, max ₹{{ num_clean(setting('pf_cap')) }})</label></div>
                        <div class="form-check"><input type="hidden" name="esi_applicable" value="0"><input class="form-check-input" type="checkbox" name="esi_applicable" value="1" id="esi" @checked(old('esi_applicable', $p->esi > 0 || $p->employee?->esi_applicable))><label class="form-check-label" for="esi">Deduct ESI ({{ num_clean(setting('esi_percent')) }}% of gross)</label></div>
                    </div>
                    <div class="col-12"><label class="form-label">Remarks</label><input name="remarks" class="form-control" value="{{ old('remarks', $p->remarks) }}"></div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-header">Allowances</div>
                <div class="card-body row g-2" id="allowances">
                    @foreach (old('allowances', $p->allowances ?? []) as $i => $a)
                        <div class="col-6 col-md-3 col-xl-2">
                            <input type="hidden" name="allowances[{{ $i }}][name]" value="{{ $a['name'] }}">
                            <label class="form-label small">{{ $a['name'] }}</label>
                            <input type="number" step="0.01" name="allowances[{{ $i }}][amount]" class="form-control form-control-sm" value="{{ $a['amount'] }}">
                        </div>
                    @endforeach
                    <div class="col-6 col-md-3 col-xl-2"><label class="form-label small">Attendance bonus</label><input type="number" step="0.01" name="attendance_bonus" class="form-control form-control-sm" value="{{ $f('attendance_bonus') }}"></div>
                    <div class="col-6 col-md-3 col-xl-2"><label class="form-label small">Birthday welfare</label><input type="number" step="0.01" name="birthday_welfare" class="form-control form-control-sm" value="{{ $f('birthday_welfare') }}"></div>
                    <div class="col-12 d-flex gap-2 align-items-end mt-2">
                        <input id="new-allow-name" class="form-control form-control-sm" style="max-width:220px" placeholder="Add one-time allowance (name)">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="add-allow"><i class="bi bi-plus"></i> Add</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-calculator"></i> Recalculate &amp; save</button>
        <a href="{{ route('payroll.show', $p->payroll_run_id) }}" class="btn btn-light">Cancel</a>
    </div>
</form>
@endsection
@push('scripts')
<script>
document.getElementById('add-allow').addEventListener('click', () => {
    const name = document.getElementById('new-allow-name').value.trim();
    if (!name) return;
    const i = 'n' + Date.now();
    const col = document.createElement('div');
    col.className = 'col-6 col-md-3 col-xl-2';
    col.innerHTML = `<input type="hidden" name="allowances[${i}][name]"><label class="form-label small"></label><input type="number" step="0.01" name="allowances[${i}][amount]" class="form-control form-control-sm">`;
    col.querySelector('input[type=hidden]').value = name;
    col.querySelector('label').textContent = name;
    const box = document.getElementById('allowances');
    box.insertBefore(col, box.lastElementChild);
    document.getElementById('new-allow-name').value = '';
});
</script>
@endpush
