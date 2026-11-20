{{-- Colour coding: each row shows its status with a coloured edge, filled cells are tinted, and chips filter the rows. --}}
<script>
(function () {
    const table = document.querySelector('#importForm table') || document.querySelector('.content .card table');
    if (!table) return;
    const rows = [...table.querySelectorAll('tbody tr[data-row], tbody tr')].filter(tr => tr.querySelector('td'));
    const stateOf = tr => tr.classList.contains('table-danger') || tr.dataset.error === '1' ? 'error' : (tr.classList.contains('table-warning') ? 'warning' : 'ok');
    rows.forEach(tr => tr.classList.add('st-' + stateOf(tr)));

    // tint every filled value (inputs and plain numeric cells), live while typing
    const tint = el => {
        const v = el.tagName === 'INPUT' ? el.value : el.textContent.trim();
        el.closest('td')?.classList.toggle('cell-filled', v !== '' && v !== '0' && v !== '—' && !isNaN(parseFloat(v)) && parseFloat(v) !== 0);
    };
    table.querySelectorAll('tbody input[type=number]').forEach(el => { tint(el); el.addEventListener('input', () => tint(el)); });
    table.querySelectorAll('tbody td.num, tbody td.cell-num').forEach(tint);

    // filter chips
    const counts = {all: rows.length, ok: 0, warning: 0, error: 0};
    rows.forEach(tr => counts[stateOf(tr)]++);
    const bar = document.createElement('div');
    bar.className = 'imp-chips';
    bar.innerHTML = [['all', 'All rows', 'bi-list'], ['ok', 'Ready', 'bi-check-circle-fill'], ['warning', 'Check', 'bi-exclamation-triangle-fill'], ['error', 'Errors', 'bi-x-octagon-fill']]
        .map(([k, l, i]) => `<button type="button" class="imp-chip c-${k} ${k === 'all' ? 'on' : ''}" data-f="${k}"><i class="bi ${i}"></i> ${l} <b>${counts[k]}</b></button>`).join('');
    const card = table.closest('.card');
    (card?.querySelector('.card-header') || table.parentElement).after(bar);
    bar.addEventListener('click', e => {
        const b = e.target.closest('[data-f]'); if (!b) return;
        bar.querySelectorAll('.imp-chip').forEach(x => x.classList.toggle('on', x === b));
        rows.forEach(tr => { tr.style.display = b.dataset.f === 'all' || tr.classList.contains('st-' + b.dataset.f) ? '' : 'none'; });
        window.notify?.({type: 'info', title: 'Showing ' + b.textContent.trim().replace(/\s+\d+$/, '').toLowerCase(), message: counts[b.dataset.f] + ' row(s)', duration: 2000});
    });
    if (counts.error || counts.warning) {
        setTimeout(() => window.notify?.({type: counts.error ? 'warning' : 'info', title: 'File checked',
            message: `${counts.ok} ready, ${counts.warning} to check, ${counts.error} with errors. Use the coloured chips to filter.`, duration: 6000}), 500);
    } else if (rows.length) {
        setTimeout(() => window.notify?.({type: 'success', title: 'File checked', message: `All ${rows.length} rows are ready to import.`}), 500);
    }
})();
</script>
<style>
    .imp-chips { display: flex; flex-wrap: wrap; gap: .4rem; padding: .6rem 1.1rem; background: var(--accent-softer); border-bottom: 1px solid var(--line-2); }
    .imp-chip { border: 1px solid var(--line); background: #fff; border-radius: 999px; padding: .25rem .75rem; font-size: .8rem; font-weight: 600; display: inline-flex; gap: .35rem; align-items: center; transition: transform .15s; }
    .imp-chip:hover { transform: translateY(-1px); }
    .imp-chip b { background: #f3f4f6; border-radius: 999px; padding: 0 .45rem; }
    .imp-chip.c-ok i { color: #16a34a; } .imp-chip.c-warning i { color: #d97706; } .imp-chip.c-error i { color: #dc2626; } .imp-chip.c-all i { color: var(--accent); }
    .imp-chip.on { color: #fff; border-color: transparent; }
    .imp-chip.on i { color: #fff !important; } .imp-chip.on b { background: rgba(255,255,255,.25); }
    .imp-chip.c-all.on { background: var(--accent); } .imp-chip.c-ok.on { background: #16a34a; } .imp-chip.c-warning.on { background: #d97706; } .imp-chip.c-error.on { background: #dc2626; }
    tr.st-ok > td:first-child { box-shadow: inset 4px 0 0 #22c55e; }
    tr.st-warning > td:first-child { box-shadow: inset 4px 0 0 #f59e0b; }
    tr.st-error > td:first-child { box-shadow: inset 4px 0 0 #ef4444; }
    td.cell-filled { background: #ecfdf5 !important; }
    td.cell-filled input { border-color: #86efac; background: #f0fdf4; font-weight: 600; }
    tr.st-error td.cell-filled { background: #fee2e2 !important; }
</style>
