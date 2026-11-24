{{-- Salary sheet columns: the register / export / import layout, and which columns the register shows by default --}}
<div class="card mb-3" id="sheetColumns">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        <span><i class="bi bi-layout-three-columns"></i> Salary sheet columns</span>
        <span class="small text-muted ms-auto">Layout of “Salary Workings Oct 2026” — used by the salary register, its Excel export, the payslip and the salary-sheet import</span>
    </div>
    <form method="POST" action="{{ route('settings.register-columns') }}">@csrf @method('PUT')
        <div class="card-body">
            <div class="sheet-strip mb-3">
                @foreach (\App\Support\SalaryLayout::GROUPS as $g => $label)
                    @php($gc = collect($registerCols)->where(1, $g))
                    @if ($gc->isNotEmpty())
                        <div class="sheet-group g-{{ $g }}">
                            <div class="sg-head">
                                <label class="d-flex align-items-center gap-2 m-0"><input type="checkbox" class="form-check-input" data-sg="{{ $g }}"> {{ $label }}</label>
                                <span class="small opacity-75">{{ $gc->count() }}</span>
                            </div>
                            @foreach ($gc as $c)
                                @php($locked = in_array($c[0], \App\Support\SalaryLayout::ALWAYS, true))
                                <label class="sg-col" title="{{ $locked ? 'Always shown' : 'Show by default in the salary register' }}">
                                    <input type="checkbox" class="form-check-input" name="visible[]" value="{{ $c[0] }}" data-sgc="{{ $g }}"
                                        @checked($locked || ! in_array($c[0], $registerHidden, true)) @disabled($locked)>
                                    <span>{{ $c[2] }}</span>
                                    @if (str_starts_with($c[0], 'al:'))<i class="bi bi-tag text-muted small" title="Allowance type"></i>@endif
                                    @if ($locked)<i class="bi bi-lock small text-muted"></i><input type="hidden" name="visible[]" value="{{ $c[0] }}">@endif
                                </label>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
            <div class="small text-muted">Ticked columns are shown when someone opens a payroll for the first time; everybody can still show or hide columns with the <strong>Columns</strong> button on the register (remembered on their computer). Allowance columns (<i class="bi bi-tag"></i>) come from the allowance types below — add a new one (e.g. “Increment 2027”) and it appears here, in the register, the export and the import.</div>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            <button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Save default columns</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-sg-all="1"><i class="bi bi-check2-all"></i> Tick all</button>
        </div>
    </form>
</div>
@push('styles')
<style>
    .sheet-strip { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 6px; }
    .sheet-group { flex: 0 0 auto; min-width: 170px; border: 1px solid var(--line); border-radius: 12px; overflow: hidden; background: #fff; }
    .sg-head { display: flex; justify-content: space-between; align-items: center; padding: .45rem .65rem; font-weight: 700; font-size: .8rem; }
    .sheet-group.g-info .sg-head, .sheet-group.g-basic .sg-head { background: #f1f5f9; color: #334155; }
    .sheet-group.g-add .sg-head { background: #ecfdf5; color: #065f46; } .sheet-group.g-red .sg-head { background: #fef2f2; color: #991b1b; }
    .sheet-group.g-allow .sg-head { background: #f5f3ff; color: #5b21b6; }
    .sheet-group.g-gross .sg-head, .sheet-group.g-net .sg-head, .sheet-group.g-total .sg-head { background: var(--accent-soft); color: var(--accent-ink); }
    .sg-col { display: flex; align-items: center; gap: .45rem; padding: .3rem .65rem; font-size: .82rem; border-top: 1px solid var(--line-2); cursor: pointer; white-space: nowrap; }
    .sg-col:hover { background: var(--accent-softer); }
</style>
@endpush
@push('scripts')
<script>
(function () {
    const box = document.getElementById('sheetColumns'); if (!box) return;
    const sync = () => box.querySelectorAll('[data-sg]').forEach(h => {
        const cols = [...box.querySelectorAll(`[data-sgc="${h.dataset.sg}"]`)], on = cols.filter(c => c.checked).length;
        h.checked = on === cols.length; h.indeterminate = on > 0 && on < cols.length;
    });
    box.addEventListener('change', e => {
        if (e.target.dataset.sg) box.querySelectorAll(`[data-sgc="${e.target.dataset.sg}"]`).forEach(c => { if (!c.disabled) c.checked = e.target.checked; });
        sync();
    });
    box.querySelector('[data-sg-all]').addEventListener('click', () => { box.querySelectorAll('[data-sgc]').forEach(c => c.checked = true); sync(); });
    sync();
})();
</script>
@endpush
