{{-- Shared client-side checks for import previews: include toggles, duplicate employees, verified tick. --}}
<script>
(function () {
    const form = document.getElementById('importForm');
    if (!form) return;
    const btn = document.getElementById('importBtn');
    const verified = document.getElementById('verified');
    const counter = document.getElementById('importCount');
    const dupBox = document.getElementById('dupWarning');

    function refresh() {
        const chosen = {};
        let count = 0;
        form.querySelectorAll('tr[data-row]').forEach(tr => {
            const sel = tr.querySelector('select[data-emp]');
            const inc = tr.querySelector('input[data-include]');
            if (sel && inc && !inc.disabled) {
                if (!sel.value) { inc.checked = false; }
                inc.disabled = tr.dataset.error === '1';
            }
            tr.classList.toggle('opacity-50', !(inc && inc.checked));
            if (inc && inc.checked && sel && sel.value) { (chosen[sel.value] ??= []).push(tr); count++; }
            tr.classList.remove('table-danger-dup');
        });
        const dups = Object.values(chosen).filter(list => list.length > 1);
        dups.flat().forEach(tr => tr.classList.add('table-danger-dup'));
        if (dupBox) dupBox.classList.toggle('d-none', dups.length === 0);
        if (counter) counter.textContent = count;
        btn.disabled = !verified.checked || count === 0 || dups.length > 0;
    }
    form.addEventListener('change', e => {
        const tr = e.target.closest('tr[data-row]');
        if (tr && e.target.matches('select[data-emp]') && e.target.value) {
            const inc = tr.querySelector('input[data-include]');
            if (inc && tr.dataset.error !== '1') inc.checked = true;
        }
        refresh();
    });
    document.getElementById('toggleAll')?.addEventListener('change', e => {
        form.querySelectorAll('tr[data-row]').forEach(tr => {
            const inc = tr.querySelector('input[data-include]'), sel = tr.querySelector('select[data-emp]');
            if (inc && !inc.disabled && (!sel || sel.value)) inc.checked = e.target.checked;
        });
        refresh();
    });
    refresh();
})();
</script>
<style>
    .table-danger-dup td { background: #fee2e2 !important; }
    .issue { font-size: .74rem; line-height: 1.25; }
    .cell-error { background: #fee2e2 !important; border-color: #ef4444 !important; }
    .cell-warning { background: #fef3c7 !important; }
    .cell-locked { background: #e5e7eb !important; }
</style>
