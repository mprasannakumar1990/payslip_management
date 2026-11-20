<script>
(function () {
    /* ---------- sidebar (mobile off-canvas) ---------- */
    const body = document.body;
    const open = () => body.classList.add('nav-open'), close = () => body.classList.remove('nav-open');
    document.getElementById('sidebarOpen')?.addEventListener('click', open);
    document.getElementById('sidebarClose')?.addEventListener('click', close);
    document.getElementById('sidebarBackdrop')?.addEventListener('click', close);
    document.getElementById('mobileMenu')?.addEventListener('click', open);

    /* ---------- page loader & navigation progress ---------- */
    const loader = document.getElementById('pageLoader');
    const progress = document.getElementById('topProgress');
    const hideLoader = () => loader && loader.classList.add('hide');
    if (document.readyState === 'complete') hideLoader(); else window.addEventListener('load', hideLoader);
    setTimeout(hideLoader, 2500); // never block the page for long (slow CDN etc.)
    const startProgress = () => { progress.classList.remove('done'); void progress.offsetWidth; progress.classList.add('run'); };
    const stopProgress = () => { progress.classList.remove('run'); progress.classList.add('done'); };
    window.addEventListener('pageshow', e => { hideLoader(); stopProgress(); });
    // links that download a file must not show the progress bar forever
    const isDownload = (a) => a.hasAttribute('download') || /\/(export|pdf)(\?|$)|\/template\/|[?&]export=1/.test(a.getAttribute('href') || '');
    document.addEventListener('click', e => {
        const a = e.target.closest('a[href]');
        if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || a.target === '_blank') return;
        const href = a.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || isDownload(a)) return;
        try { if (new URL(a.href).origin !== location.origin) return; } catch (err) { return; }
        startProgress();
    });
    document.addEventListener('submit', e => {
        const f = e.target;
        if ((f.getAttribute('method') || 'get').toLowerCase() === 'get' && !/\/template\/|export/.test(f.getAttribute('action') || '')) startProgress();
    });

    /* ---------- scroll to top ---------- */
    const toTop = document.getElementById('toTop');
    window.addEventListener('scroll', () => toTop.classList.toggle('show', window.scrollY > 400), {passive: true});
    toTop.addEventListener('click', () => window.scrollTo({top: 0, behavior: 'smooth'}));

    /* ---------- list tables become cards on phones: label each cell with its column title ---------- */
    document.querySelectorAll('table.table-stack').forEach(t => {
        const heads = [...t.querySelectorAll('thead th')].map(th => th.textContent.replace(/\s+/g, ' ').trim());
        t.querySelectorAll('tbody tr').forEach(tr => {
            [...tr.children].forEach((td, i) => { if (!td.hasAttribute('colspan') && !td.hasAttribute('data-label')) td.setAttribute('data-label', heads[i] || ''); });
        });
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
    document.querySelector('.sidebar .nav-item.active')?.scrollIntoView({block: 'center'});

    /* ---------- back button: previous page in this app, otherwise the section's list page ---------- */
    document.getElementById('backBtn')?.addEventListener('click', function (e) {
        try {
            const ref = document.referrer ? new URL(document.referrer) : null;
            if (ref && ref.origin === location.origin && ref.href !== location.href && history.length > 1 && !/\/login$/.test(ref.pathname)) {
                e.preventDefault();
                history.back();
            }
        } catch (err) {}
    });

    /* ---------- searchable selects ---------- */
    document.querySelectorAll('select.select-search').forEach(el => new TomSelect(el, {maxOptions: 500, allowEmptyOption: true}));

    /* ---------- busy overlay ---------- */
    const busy = document.getElementById('busyOverlay');
    const showBusy = (text) => { document.getElementById('busyText').textContent = text || 'Please wait…'; busy.classList.add('show'); };
    window.addEventListener('pageshow', () => busy.classList.remove('show'));

    /* ---------- confirmation for every action that changes data ----------
       data-confirm="message"            custom message (form or button)
       data-confirm-title="title"        custom title
       data-confirm-level="double"       two steps: confirm, then tick "I have checked" and confirm again
       data-confirm-final="message"      message for the second step
       data-confirm-variant="danger|success|warning|primary"
       data-no-confirm                   skip (filters, uploads to preview…)
       data-busy="text"                  text on the wait overlay after confirming */
    const modalEl = document.getElementById('confirmModal');
    const modal = new bootstrap.Modal(modalEl);
    const $ = sel => modalEl.querySelector(sel);
    const icons = {danger: 'bi-exclamation-triangle', success: 'bi-check2-circle', warning: 'bi-exclamation-circle', primary: 'bi-question-lg'};
    let pending = null;

    function attr(name, form, btn) {
        return (btn && btn.dataset[name]) || form.dataset[name] || '';
    }
    function variantOf(form, btn) {
        const v = attr('confirmVariant', form, btn);
        if (v) return v;
        const cls = (btn && btn.className) || '';
        if (/danger/.test(cls) || /delete|remove|reject/i.test(btn?.textContent || '') || (form.querySelector('input[name=_method]')?.value === 'DELETE')) return 'danger';
        if (/success/.test(cls)) return 'success';
        if (/warning/.test(cls)) return 'warning';
        return 'primary';
    }
    function label(btn) {
        const t = (btn?.getAttribute('title') || btn?.textContent || '').replace(/\s+/g, ' ').trim();
        return t || 'Save changes';
    }
    function showStep(step) {
        const {form, btn, double, variant} = pending;
        const title = attr('confirmTitle', form, btn) || label(btn);
        const msg = attr('confirm', form, btn) || `Do you want to continue with “${label(btn)}”?`;
        $('.confirm-icon').className = 'confirm-icon mb-3 v-' + variant;
        $('.confirm-icon').innerHTML = `<i class="bi ${step === 2 ? 'bi-shield-check' : icons[variant]}"></i>`;
        $('.confirm-step').textContent = double ? `Step ${step} of 2` : '';
        $('.confirm-step').className = 'confirm-step badge rounded-pill mb-2 ' + (double ? 'bg-light text-dark' : 'd-none');
        $('.confirm-title').textContent = step === 2 ? 'Final confirmation' : title;
        $('.confirm-message').textContent = step === 2
            ? (attr('confirmFinal', form, btn) || `Please confirm once more: “${title}”. This cannot be undone easily.`)
            : msg;
        const check = $('#confirmCheck');
        check.checked = false;
        $('.confirm-check').classList.toggle('d-none', step !== 2);
        const ok = $('.confirm-ok');
        ok.className = 'btn px-4 confirm-ok btn-' + variant;
        ok.textContent = step === 2 ? 'Yes, proceed' : (double ? 'Continue' : 'Confirm');
        ok.disabled = step === 2;
        pending.step = step;
    }
    $('#confirmCheck').addEventListener('change', e => { $('.confirm-ok').disabled = !e.target.checked; });
    $('.confirm-ok').addEventListener('click', () => {
        if (!pending) return;
        if (pending.double && pending.step === 1) { showStep(2); return; }
        const {form, btn} = pending;
        pending = null;
        modal.hide();
        form.dataset.confirmed = '1';
        showBusy(attr('busy', form, btn));
        if (btn && btn.name) {
            const h = document.createElement('input');
            h.type = 'hidden'; h.name = btn.name; h.value = btn.value;
            form.appendChild(h);
        }
        HTMLFormElement.prototype.submit.call(form);
    });

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
        const btn = e.submitter || null;
        if (form.dataset.confirmed === '1' || form.hasAttribute('data-no-confirm') || btn?.hasAttribute('data-no-confirm')) {
            if (form.hasAttribute('data-busy') || btn?.dataset.busy) showBusy(attr('busy', form, btn));
            return;
        }
        if (!form.checkValidity()) return;
        e.preventDefault();
        pending = {form, btn, double: attr('confirmLevel', form, btn) === 'double', variant: variantOf(form, btn)};
        showStep(1);
        modal.show();
    }, true);

    /* ---------- filters apply immediately ---------- */
    document.querySelectorAll('form.auto-filter').forEach(form => {
        const go = (el) => {
            try { sessionStorage.setItem('af-focus', el.name || ''); } catch (e) {}
            form.querySelectorAll('[name="page"]').forEach(p => p.remove());
            form.submit();
        };
        let timer;
        form.querySelectorAll('select, input[type=month], input[type=date], input[type=number], input[type=checkbox], input[type=radio]')
            .forEach(el => el.addEventListener('change', () => go(el)));
        form.querySelectorAll('input[type=text], input[type=search], input:not([type])')
            .forEach(el => el.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => go(el), 500); }));
    });
    try {
        const n = sessionStorage.getItem('af-focus');
        sessionStorage.removeItem('af-focus');
        const el = n && document.querySelector('form.auto-filter [name="' + CSS.escape(n) + '"]');
        if (el && (el.type === 'text' || el.type === 'search' || !el.getAttribute('type'))) { el.focus(); const v = el.value; el.value = ''; el.value = v; }
    } catch (e) {}
})();
</script>
