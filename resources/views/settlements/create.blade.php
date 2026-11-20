@extends('layouts.app')
@section('title', 'Earn Leave & Gratuity Final Settlement')
@section('back', route('settlements.index'))
@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end auto-filter">
            <div class="col-md-6">
                <label class="form-label">Employee</label>
                <select name="employee_id" class="form-select select-search" required>
                    <option value="">Select employee</option>
                    @foreach ($employees as $e)<option value="{{ $e->id }}" @selected($employee?->id === $e->id)>{{ $e->emp_code }} - {{ $e->name }}{{ $e->status !== 'active' ? ' ('.$e->status.')' : '' }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Date of exit</label><input type="date" name="date_of_exit" class="form-control" value="{{ request('date_of_exit', $prefill['date_of_exit'] ?? '') }}"></div>
            
        </form>
    </div>
</div>

@if ($employee && $prefill)
<form method="POST" action="{{ route('settlements.store') }}" id="st-form" data-confirm="Save the EL & gratuity settlement for {{ $employee?->name }}?" data-confirm-title="Save settlement" data-confirm-level="double">
    @csrf
    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">Year-wise EL — {{ $employee->name }} (joined {{ $employee->date_of_joining?->format('d M Y') }})</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" id="el-rows">
                        <thead><tr><th>Year</th><th>Period</th><th class="num" style="width:130px">EL days balance</th><th class="num">Per day</th><th class="num">Amount</th></tr></thead>
                        <tbody>
                        @foreach ($prefill['rows'] as $i => $r)
                            <tr>
                                <td><input type="hidden" name="rows[{{ $i }}][year]" value="{{ $r['year'] }}">{{ $r['year'] }}</td>
                                <td><input name="rows[{{ $i }}][period]" value="{{ $r['period'] }}" class="form-control form-control-sm" style="width:120px"></td>
                                <td><input type="number" step="0.01" name="rows[{{ $i }}][days]" value="{{ $r['days'] }}" class="form-control form-control-sm text-end el-days"></td>
                                <td class="num per-day"></td>
                                <td class="num row-amt"></td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot><tr class="total-row"><td colspan="2">EL Total</td><td class="num" id="el-days-total"></td><td></td><td class="num" id="el-amt-total"></td></tr></tfoot>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">Settlement</div>
                <div class="card-body row g-2">
                    <div class="col-6"><label class="form-label">Date of exit</label><input type="date" name="date_of_exit" class="form-control" value="{{ $prefill['date_of_exit'] }}"></div>
                    <div class="col-6"><label class="form-label">Settlement date *</label><input type="date" name="settlement_date" class="form-control" value="{{ now()->toDateString() }}" required></div>
                    <div class="col-6"><label class="form-label">Basic salary</label><input type="number" step="0.01" name="basic_salary" id="basic" class="form-control" value="{{ $employee->basic_salary }}"></div>
                    <div class="col-6"><label class="form-label">EL per day rate</label><input type="number" step="0.01" name="per_day_rate" id="per-day" class="form-control" value="{{ $prefill['per_day'] }}"><div class="form-text">Basic ÷ {{ num_clean($s['gratuity_divisor']) }}</div></div>
                    <div class="col-6"><label class="form-label">Years of service</label><input type="number" step="0.1" name="gratuity_years" id="years" class="form-control" value="{{ $prefill['years'] }}"></div>
                    <div class="col-6"><label class="form-label">Gratuity</label><input type="number" step="0.01" name="gratuity_amount" id="gratuity" class="form-control" value="{{ $prefill['gratuity'] }}"><div class="form-text">Basic ÷ {{ num_clean($s['gratuity_divisor']) }} × {{ num_clean($s['gratuity_days']) }} × years</div></div>
                    @unless ($prefill['eligible'])
                        <div class="col-12"><div class="alert alert-warning py-1 small mb-0">Service is below {{ num_clean($s['gratuity_min_years']) }} years — gratuity is normally not payable. Set it to 0 if not applicable.</div></div>
                    @endunless
                    <div class="col-6"><label class="form-label">Bonus</label><input type="number" step="0.01" name="bonus_amount" id="bonus" class="form-control" value="0"></div>
                    <div class="col-6"><label class="form-label">Other additions</label><input type="number" step="0.01" name="other_amount" id="other" class="form-control" value="0"></div>
                    <div class="col-6"><label class="form-label">Deductions</label><input type="number" step="0.01" name="deduction_amount" id="deduct" class="form-control" value="0"></div>
                    <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" rows="2" class="form-control"></textarea></div>
                    <div class="col-12">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="mark_exited" value="1" id="mark" @checked($employee->status === 'active')><label class="form-check-label" for="mark">Mark employee as exited on the exit date</label></div>
                    </div>
                    <div class="col-12">
                        <table class="table table-sm mb-0 mt-2">
                            <tr><td>EL amount</td><td class="num" id="sum-el"></td></tr>
                            <tr><td>Gratuity</td><td class="num" id="sum-gr"></td></tr>
                            <tr><td>Bonus + other − deductions</td><td class="num" id="sum-other"></td></tr>
                            <tr class="total-row"><td>Total (rounded)</td><td class="num fs-5" id="sum-total"></td></tr>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white"><button class="btn btn-primary w-100"><i class="bi bi-save"></i> Save settlement</button></div>
            </div>
        </div>
    </div>
</form>
@endif
@endsection
@push('scripts')
<script>
(function () {
    const form = document.getElementById('st-form');
    if (!form) return;
    const v = id => parseFloat(document.getElementById(id).value) || 0;
    const fmt = n => n.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const divisor = {{ (float) $s['gratuity_divisor'] ?: 26 }}, gDays = {{ (float) $s['gratuity_days'] }};
    let gratuityTouched = false;
    document.getElementById('gratuity').addEventListener('input', () => gratuityTouched = true);
    function recalc(e) {
        if (e && (e.target.id === 'basic' || e.target.id === 'years') && !gratuityTouched) {
            document.getElementById('gratuity').value = Math.round(v('basic') / divisor * gDays * v('years'));
        }
        if (e && e.target.id === 'basic') { document.getElementById('per-day').value = Math.round(v('basic') / divisor); }
        const pd = v('per-day'); let days = 0, amt = 0;
        form.querySelectorAll('#el-rows tbody tr').forEach(tr => {
            const d = parseFloat(tr.querySelector('.el-days').value) || 0;
            days += d; amt += d * pd;
            tr.querySelector('.per-day').textContent = fmt(pd);
            tr.querySelector('.row-amt').textContent = fmt(d * pd);
        });
        document.getElementById('el-days-total').textContent = Math.round(days * 100) / 100;
        document.getElementById('el-amt-total').textContent = fmt(amt);
        const other = v('bonus') + v('other') - v('deduct');
        document.getElementById('sum-el').textContent = fmt(amt);
        document.getElementById('sum-gr').textContent = fmt(v('gratuity'));
        document.getElementById('sum-other').textContent = fmt(other);
        document.getElementById('sum-total').textContent = '₹ ' + Math.round(amt + v('gratuity') + other).toLocaleString('en-IN');
    }
    form.addEventListener('input', recalc);
    recalc();
})();
</script>
@endpush
