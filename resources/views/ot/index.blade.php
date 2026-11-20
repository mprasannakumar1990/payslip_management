@extends('layouts.app')
@section('title', "OT & Allowance Entry — ".month_name($month).' '.$year)
@section('content')
@php($period = sprintf('%04d-%02d', $year, $month))
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center py-2">
        <form class="d-flex gap-2 auto-filter" method="GET">
            @include('partials.period-select', ['p' => \Illuminate\Support\Carbon::create($year, $month, 1)])
            
        </form>
        <span class="ms-auto small text-muted">2nd / 3rd shift = hours (₹{{ num_clean(setting('second_shift_rate')) }} / ₹{{ num_clean(setting('third_shift_rate')) }} per hour) · OT = hours paid at per-day ÷ {{ num_clean(setting('ot_hours_per_day')) }} × multiplier</span>
        <a href="{{ route('ot.report', ['period' => $period]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-bar-chart"></i> Report</a>
        @unless ($locked)
            <form method="POST" action="{{ route('ot.approve-month') }}" data-confirm="Approve all pending OT entries for this month? Approved OT is paid in payroll." data-confirm-title="Approve all OT" data-confirm-level="double">@csrf
                <input type="hidden" name="period" value="{{ $period }}">
                <button class="btn btn-sm btn-outline-success"><i class="bi bi-check2-all"></i> Approve all pending</button>
            </form>
        @endunless
    </div>
</div>
@if ($locked)
    <div class="alert alert-info">Payroll for this month is finalized, so OT is read-only.</div>
@endif

<form method="POST" action="{{ route('ot.save') }}">
    @csrf
    <input type="hidden" name="period" value="{{ $period }}">
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="ot-table">
                <thead><tr><th>Sl</th><th>Emp ID</th><th>Name</th><th class="num">2nd Shift</th><th class="num">3rd Shift</th><th class="num">Normal OT</th><th class="num">Spl OT</th><th class="num">Total OT</th><th>Remarks</th><th class="num">Leave</th><th>Status</th></tr></thead>
                <tbody>
                @php($sl = 1)
                @forelse ($groups as $group => $emps)
                    <tr class="group-row"><td colspan="11">{{ $group }} OT Calculation</td></tr>
                    @foreach ($emps as $e)
                        @php($en = $entries[$e->id] ?? null)
                        <tr data-group="{{ $group }}">
                            <td>{{ $sl++ }}</td>
                            <td>{{ $e->emp_code }}</td>
                            <td>{{ $e->name }}</td>
                            @foreach (['second' => 'second_shift_hours', 'third' => 'third_shift_hours', 'normal' => 'normal_ot_hours', 'spl' => 'spl_ot_hours'] as $k => $f)
                                <td class="num"><input type="number" step="0.5" min="0" name="rows[{{ $e->id }}][{{ $k }}]" value="{{ old("rows.$e->id.$k", $en ? num_clean($en->$f) : '') }}" class="form-control form-control-sm ot-input d-inline-block" data-col="{{ $k }}" @disabled($locked)></td>
                            @endforeach
                            <td class="num fw-semibold row-total">{{ $en ? num_clean($en->totalOt()) : '' }}</td>
                            <td><input name="rows[{{ $e->id }}][remarks]" value="{{ old("rows.$e->id.remarks", $en?->remarks) }}" class="form-control form-control-sm" style="min-width:140px" @disabled($locked)></td>
                            <td class="num">{{ $leaveDays[$e->id] ? num_clean($leaveDays[$e->id]) : '' }}</td>
                            <td class="text-nowrap">
                                @if ($en)
                                    <span class="badge {{ ['pending' => 'bg-warning text-dark', 'approved' => 'bg-success', 'rejected' => 'bg-danger'][$en->status] }}">{{ $en->status }}</span>
                                    @if (! $locked && $en->status !== 'approved')
                                        <button form="st-{{ $en->id }}-a" class="btn btn-link btn-sm p-0 text-success" title="Approve"><i class="bi bi-check-circle"></i></button>
                                    @endif
                                    @if (! $locked && $en->status !== 'rejected')
                                        <button form="st-{{ $en->id }}-r" class="btn btn-link btn-sm p-0 text-danger" title="Reject"><i class="bi bi-x-circle"></i></button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    <tr class="total-row group-total" data-group="{{ $group }}">
                        <td colspan="3">Total</td><td class="num" data-sum="second"></td><td class="num" data-sum="third"></td><td class="num" data-sum="normal"></td><td class="num" data-sum="spl"></td><td class="num" data-sum="total"></td><td colspan="3"></td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center text-muted py-4">No OT-eligible employees for this month.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @unless ($locked)
            <div class="card-footer bg-white d-flex gap-2">
                <button name="action" value="save" class="btn btn-outline-primary"><i class="bi bi-save"></i> Save (pending approval)</button>
                <button name="action" value="approve" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Save &amp; approve</button>
                <span class="small text-muted align-self-center">Only approved OT is included in payroll. Changing an approved row moves it back to pending unless you use "Save &amp; approve".</span>
            </div>
        @endunless
    </div>
</form>
@foreach ($entries as $en)
    <form id="st-{{ $en->id }}-a" method="POST" action="{{ route('ot.status', $en) }}" class="d-none">@csrf<input type="hidden" name="status" value="approved"></form>
    <form id="st-{{ $en->id }}-r" method="POST" action="{{ route('ot.status', $en) }}" class="d-none">@csrf<input type="hidden" name="status" value="rejected"></form>
@endforeach
@endsection
@push('scripts')
<script>
(function () {
    const fmt = n => (Math.round(n * 100) / 100).toString();
    function recalc() {
        const sums = {};
        document.querySelectorAll('#ot-table tbody tr[data-group]:not(.group-total)').forEach(tr => {
            const g = tr.dataset.group; sums[g] ??= {second: 0, third: 0, normal: 0, spl: 0, total: 0};
            let rowTotal = 0;
            tr.querySelectorAll('input[data-col]').forEach(i => {
                const v = parseFloat(i.value) || 0; sums[g][i.dataset.col] += v;
                if (i.dataset.col === 'normal' || i.dataset.col === 'spl') rowTotal += v;
            });
            sums[g].total += rowTotal;
            tr.querySelector('.row-total').textContent = rowTotal ? fmt(rowTotal) : '';
        });
        document.querySelectorAll('#ot-table tr.group-total').forEach(tr => {
            const s = sums[tr.dataset.group] || {};
            tr.querySelectorAll('[data-sum]').forEach(td => td.textContent = fmt(s[td.dataset.sum] || 0));
        });
    }
    document.getElementById('ot-table').addEventListener('input', recalc);
    recalc();
})();
</script>
@endpush
