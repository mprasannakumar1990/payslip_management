{{--
    Salary register in the column layout of the monthly salary sheet.
    Every column is rendered; the column picker shows / hides them (remembered in this browser, defaults from Settings).
    The Information and Basic columns stay fixed on the left while the table scrolls sideways.
--}}
@php
    $cols = \App\Support\SalaryLayout::registerColumns($payslips->flatMap(fn ($p) => collect($p->allowances ?? [])->pluck('name'))->unique());
    $groups = \App\Support\SalaryLayout::GROUPS;
    $span = collect($cols)->countBy(fn ($c) => $c[1]);
    $filled = collect($cols)->mapWithKeys(fn ($c) => [$c[0] => in_array($c[4], ['text', 'name'], true)
        ? $payslips->contains(fn ($p) => (string) $c[3]($p) !== '')
        : $payslips->contains(fn ($p) => (float) $c[3]($p) != 0)]);
    $fmt = fn ($c, $v) => match ($c[4]) {
        'text', 'name' => (string) $v,
        'qty' => $v ? num_clean($v) : '',
        'absent' => num_clean($v),
        'inr0', 'inr0-b' => $v ? inr($v, 0) : '',
        default => $v ? inr($v) : '',
    };
    $frozen = ['info', 'basic'];
@endphp
<div class="card reg-card" id="regCard"
     data-default-hidden='@json(\App\Support\SalaryLayout::hiddenByDefault())' data-always='@json(\App\Support\SalaryLayout::ALWAYS)' data-filled='@json($filled)'>
    <div class="card-header d-flex flex-wrap gap-2 align-items-center no-print">
        <span><i class="bi bi-table"></i> Salary register</span>
        <span class="d-flex flex-wrap gap-1 small">
            @foreach (['add' => 'Addition', 'red' => 'Reduction', 'allow' => 'Allowance'] as $g => $l)<span class="reg-key f-{{ $g }}">{{ $l }}</span>@endforeach
        </span>
        <span class="small text-muted ms-auto" id="regShown"></span>

        <div class="dropdown">
            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <i class="bi bi-layout-three-columns"></i> Columns
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0 col-picker">
                <div class="d-flex flex-wrap gap-1 p-2 border-bottom">
                    <button type="button" class="btn btn-sm btn-primary" data-preset="all"><i class="bi bi-check2-all"></i> All</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="filled" title="Hide columns that are empty for every employee this month"><i class="bi bi-funnel"></i> Only filled</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="default" title="The columns chosen in Settings"><i class="bi bi-arrow-counterclockwise"></i> Default</button>
                </div>
                <div class="form-check form-switch px-3 pt-2 ms-2">
                    <input class="form-check-input" type="checkbox" id="regFreeze" checked>
                    <label class="form-check-label small fw-semibold" for="regFreeze">Keep Information &amp; Basic fixed while scrolling</label>
                </div>
                <div class="col-picker-list p-2">
                    @foreach ($groups as $g => $label)
                        @if ($span[$g] ?? 0)
                            <div class="cp-group">
                                <label class="cp-head"><input type="checkbox" class="form-check-input" data-group-toggle="{{ $g }}"> <span class="reg-dot g-{{ $g }}"></span> {{ $label }}</label>
                                @foreach (collect($cols)->where(1, $g) as $c)
                                    <label class="cp-item {{ $filled[$c[0]] ? '' : 'is-empty' }}" title="{{ $filled[$c[0]] ? '' : 'Empty this month' }}">
                                        <input type="checkbox" class="form-check-input" data-col-toggle="{{ $c[0] }}" data-group="{{ $g }}" @disabled(in_array($c[0], \App\Support\SalaryLayout::ALWAYS, true))>
                                        {{ $c[2] }} @unless ($filled[$c[0]])<small class="text-muted">· empty</small>@endunless
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="table-responsive reg-scroll" style="max-height:75vh">
        <table class="table table-sm table-bordered table-hover table-register reg-dyn mb-0" id="regTable">
            <thead class="sticky-top">
                <tr class="reg-groups">
                    @foreach ($groups as $g => $label)
                        @if ($span[$g] ?? 0)<th colspan="{{ $span[$g] }}" class="text-center g-{{ $g }} {{ in_array($g, $frozen, true) ? 'fz' : '' }}" data-group-head="{{ $g }}">{{ $label }}</th>@endif
                    @endforeach
                    <th class="no-print" colspan="2"></th>
                </tr>
                <tr>
                    @foreach ($cols as $c)
                        <th class="{{ in_array($c[4], ['text', 'name'], true) ? '' : 'num' }} g-{{ $c[1] }} {{ in_array($c[1], $frozen, true) ? 'fz' : '' }}" data-c="{{ $c[0] }}">{{ $c[2] }}</th>
                    @endforeach
                    <th class="no-print">Email</th><th class="no-print"></th>
                </tr>
            </thead>
            <tbody>
            @foreach ($payslips as $p)
                <tr>
                    @foreach ($cols as $c)
                        @php($v = $c[3]($p))
                        @php($fz = in_array($c[1], $frozen, true) ? 'fz' : '')
                        @if ($c[4] === 'name')
                            <td class="text-truncate {{ $fz }}" style="max-width:170px" title="{{ $p->designation }}" data-c="{{ $c[0] }}">{{ $p->name }} @if($p->remarks)<i class="bi bi-chat-left-text text-muted" title="{{ $p->remarks }}"></i>@endif</td>
                        @elseif ($c[4] === 'text')
                            <td class="{{ $fz }} {{ $c[0] === 'emp_code' ? 'fw-semibold' : 'small' }}" data-c="{{ $c[0] }}">{{ $v }}</td>
                        @else
                            <td data-c="{{ $c[0] }}" class="num {{ $fz }} {{ str_ends_with($c[4], '-b') ? 'fw-semibold' : '' }} {{ $v && in_array($c[1], ['add', 'red', 'allow'], true) && ! str_ends_with($c[4], '-b') ? 'filled f-'.$c[1] : '' }} {{ $c[4] === 'absent' && $p->lop_days > 0 ? 'text-danger fw-semibold' : '' }} {{ $c[4] === 'ot' && $p->ot_amount_manual !== null ? 'text-info' : '' }}"
                                @if ($c[4] === 'absent') title="LOP {{ num_clean($p->lop_days) }}" @endif>{{ $fmt($c, $v) }}</td>
                        @endif
                    @endforeach
                    <td class="no-print text-nowrap">
                        @if ($p->emailed_at)
                            <span class="text-success" title="Sent to {{ $p->emailed_to }} on {{ $p->emailed_at->format('d M Y H:i') }}"><i class="bi bi-envelope-check"></i> {{ $p->emailed_at->format('d M') }}</span>
                        @elseif ($p->email_error)
                            <span class="text-danger" title="{{ $p->email_error }}"><i class="bi bi-envelope-exclamation"></i> failed</span>
                        @elseif (! $p->employee?->email)
                            <span class="text-muted" title="Add an email address on the employee record"><i class="bi bi-envelope-slash"></i> no email</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                        @if ($run->isFinalized() && $p->employee?->email)
                            <button form="em-{{ $p->id }}" class="btn btn-link btn-sm p-0" title="{{ $p->emailed_at ? 'Resend' : 'Send' }} payslip"><i class="bi bi-send"></i></button>
                        @endif
                    </td>
                    <td class="text-nowrap no-print">
                        <a href="{{ route('payslips.show', $p) }}" class="btn btn-sm btn-link p-0" title="View"><i class="bi bi-eye"></i></a>
                        @unless ($run->isFinalized())<a href="{{ route('payslips.edit', $p) }}" class="btn btn-sm btn-link p-0" title="Edit"><i class="bi bi-pencil"></i></a>@endunless
                        <a href="{{ route('payslips.pdf', $p) }}" class="btn btn-sm btn-link p-0 text-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                    </td>
                </tr>
            @endforeach
            <tr class="total-row">
                @foreach ($cols as $i => $c)
                    @php($fz = in_array($c[1], $frozen, true) ? 'fz' : '')
                    @if ($i === 0)<td class="{{ $fz }}" data-c="{{ $c[0] }}">Total</td>
                    @elseif (! $c[5])<td class="{{ $fz }}" data-c="{{ $c[0] }}"></td>
                    @else<td class="num {{ $fz }}" data-c="{{ $c[0] }}">{{ $fmt($c, $payslips->sum(fn ($p) => (float) $c[3]($p))) }}</td>@endif
                @endforeach
                <td class="no-print"></td><td class="no-print"></td>
            </tr>
            </tbody>
        </table>
    </div>
</div>
@push('styles')
<style>
    .col-picker { width: min(340px, 92vw); }
    .col-picker-list { max-height: 55vh; overflow-y: auto; }
    .cp-group { margin-bottom: .5rem; }
    .cp-head { display: flex; align-items: center; gap: .45rem; font-weight: 700; font-size: .8rem; padding: .3rem .35rem; border-radius: 8px; background: var(--accent-softer); cursor: pointer; }
    .cp-item { display: flex; align-items: center; gap: .45rem; font-size: .82rem; padding: .22rem .35rem .22rem 1.4rem; border-radius: 7px; cursor: pointer; }
    .cp-item:hover { background: #f5f6fa; }
    .cp-item.is-empty { color: var(--muted); }
    .reg-dot { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }
    .reg-dot.g-info, .reg-dot.g-basic { background: #cbd5e1; } .reg-dot.g-add { background: #34d399; } .reg-dot.g-red { background: #f87171; }
    .reg-dot.g-allow { background: #a78bfa; } .reg-dot.g-gross, .reg-dot.g-net, .reg-dot.g-total { background: var(--accent); }
    /* frozen Information & Basic columns */
    .reg-dyn.freeze .fz { position: sticky; z-index: 2; background: #fff; }
    .reg-dyn.freeze thead .fz { z-index: 4; background: var(--accent-softer); }
    .reg-dyn.freeze .total-row .fz { background: #f9fafb; }
    .reg-dyn.freeze tbody tr:hover .fz { background: var(--accent-softer); }
    .reg-dyn.freeze .fz-last { box-shadow: 4px 0 6px -3px rgba(16,24,40,.18); }
    .reg-dyn th.g-info, .reg-dyn th.g-basic { background: #f1f5f9 !important; color: #334155; }
    .reg-scroll { scroll-behavior: smooth; }
</style>
@endpush
@push('scripts')
<script>
(function () {
    const card = document.getElementById('regCard'), table = document.getElementById('regTable');
    if (!card) return;
    const KEY = 'reg-cols-v1', FKEY = 'reg-freeze';
    const always = JSON.parse(card.dataset.always), filled = JSON.parse(card.dataset.filled), defHidden = JSON.parse(card.dataset.defaultHidden);
    const allKeys = [...card.querySelectorAll('[data-col-toggle]')].map(i => i.dataset.colToggle);
    const store = {get: k => { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} }};
    let hidden = new Set((() => { try { return JSON.parse(store.get(KEY)); } catch (e) { return null; } })() ?? defHidden);
    always.forEach(k => hidden.delete(k));
    const freeze = card.querySelector('#regFreeze');
    freeze.checked = store.get(FKEY) !== '0';
    const style = document.createElement('style');
    document.head.appendChild(style);

    function apply(save) {
        style.textContent = [...hidden].map(k => `#regTable [data-c="${CSS.escape(k)}"]{display:none}`).join('');
        card.querySelectorAll('[data-col-toggle]').forEach(i => { i.checked = !hidden.has(i.dataset.colToggle); });
        card.querySelectorAll('[data-group-toggle]').forEach(g => {
            const items = [...card.querySelectorAll(`[data-group="${g.dataset.groupToggle}"]`)];
            const on = items.filter(i => i.checked).length;
            g.checked = on === items.length; g.indeterminate = on > 0 && on < items.length;
            const head = table.querySelector(`[data-group-head="${g.dataset.groupToggle}"]`);
            if (head) { head.colSpan = Math.max(1, on); head.style.display = on ? '' : 'none'; }
        });
        const shown = allKeys.length - [...hidden].filter(k => allKeys.includes(k)).length;
        document.getElementById('regShown').textContent = `${shown} of ${allKeys.length} columns shown`;
        if (save) store.set(KEY, JSON.stringify([...hidden]));
        layoutFreeze();
    }
    // left offsets for the frozen columns (recomputed whenever columns change or the window resizes)
    function layoutFreeze() {
        table.classList.toggle('freeze', freeze.checked);
        table.querySelectorAll('.fz').forEach(c => { c.style.left = ''; c.classList.remove('fz-last'); });
        if (!freeze.checked) return;
        const headRow = table.tHead.rows[1];
        let left = 0, lastKey = null;
        const lefts = {};
        [...headRow.cells].filter(th => th.classList.contains('fz') && th.offsetParent !== null).forEach(th => {
            lefts[th.dataset.c] = left; left += th.getBoundingClientRect().width; lastKey = th.dataset.c;
        });
        table.querySelectorAll('[data-c].fz').forEach(c => {
            if (c.dataset.c in lefts) { c.style.left = lefts[c.dataset.c] + 'px'; if (c.dataset.c === lastKey) c.classList.add('fz-last'); }
        });
        let gLeft = 0;
        table.querySelectorAll('.reg-groups .fz').forEach(th => { if (th.style.display !== 'none') { th.style.left = gLeft + 'px'; gLeft += th.getBoundingClientRect().width; } });
    }

    card.addEventListener('change', e => {
        const t = e.target;
        if (t.dataset.colToggle) { t.checked ? hidden.delete(t.dataset.colToggle) : hidden.add(t.dataset.colToggle); }
        else if (t.dataset.groupToggle) {
            card.querySelectorAll(`[data-group="${t.dataset.groupToggle}"]`).forEach(i => { if (!i.disabled) t.checked ? hidden.delete(i.dataset.colToggle) : hidden.add(i.dataset.colToggle); });
        } else if (t === freeze) { store.set(FKEY, freeze.checked ? '1' : '0'); layoutFreeze();
            window.notify?.({type: 'info', title: freeze.checked ? 'Information & Basic fixed' : 'Columns scroll freely', duration: 2000}); return; }
        else return;
        apply(true);
    });
    card.addEventListener('click', e => {
        const b = e.target.closest('[data-preset]'); if (!b) return;
        if (b.dataset.preset === 'all') hidden = new Set();
        if (b.dataset.preset === 'filled') hidden = new Set(allKeys.filter(k => !filled[k] && !always.includes(k)));
        if (b.dataset.preset === 'default') hidden = new Set(defHidden.filter(k => !always.includes(k)));
        apply(true);
        window.notify?.({type: 'success', title: {all: 'All columns shown', filled: 'Empty columns hidden', default: 'Default columns'}[b.dataset.preset], duration: 2200});
    });
    addEventListener('resize', () => { clearTimeout(window.__rf); window.__rf = setTimeout(layoutFreeze, 150); });
    apply(false);
    // fonts can change the widths after load
    document.fonts?.ready.then(layoutFreeze);
})();
</script>
@endpush
