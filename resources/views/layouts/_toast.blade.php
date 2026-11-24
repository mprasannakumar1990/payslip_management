{{--
    Top-right toast notifications for every movement in the app.
    JS API:  notify({type, title, message, duration})   type = success | error | warning | info | loading
             returns {close(), update({...})}; duration 0 keeps the toast until closed.
    Server flash messages (success / error / warning / info / status) and validation errors are shown as toasts too.
--}}
@php
    $flashToasts = [];
    foreach (['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info', 'status' => 'success'] as $key => $type) {
        if (session($key)) {
            $flashToasts[] = ['type' => $type, 'message' => (string) session($key)];
        }
    }
    if (isset($errors) && $errors->any()) {
        $flashToasts[] = ['type' => 'error', 'title' => $errors->count() > 1 ? 'Please fix '.$errors->count().' problems' : 'Please fix this', 'list' => $errors->all()];
    }
    if (session('backup_download')) {
        $flashToasts[] = ['type' => 'info', 'title' => 'Download starting', 'message' => 'The backup ZIP is being downloaded to this computer.'];
    }
@endphp
<style>
    .toast-stack { position: fixed; top: 14px; right: 14px; z-index: 3200; display: flex; flex-direction: column; gap: 10px; width: min(380px, calc(100vw - 28px)); pointer-events: none; }
    .tst { pointer-events: auto; position: relative; overflow: hidden; display: flex; gap: .75rem; align-items: flex-start; padding: .8rem .85rem .95rem; border-radius: 14px;
        background: #fff; color: var(--ink); border: 1px solid var(--line); box-shadow: 0 14px 34px rgba(16,24,40,.16), 0 2px 6px rgba(16,24,40,.06);
        animation: tstIn .38s cubic-bezier(.2,.9,.3,1.15) both; }
    .tst.out { animation: tstOut .3s ease forwards; }
    @keyframes tstIn { from { opacity: 0; transform: translateX(110%) scale(.96); } to { opacity: 1; transform: none; } }
    @keyframes tstOut { to { opacity: 0; transform: translateX(110%); margin-top: -60px; } }
    .tst::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--tc); }
    .tst-icon { width: 34px; height: 34px; flex: 0 0 34px; border-radius: 10px; display: grid; place-items: center; font-size: 1.05rem; color: var(--tc); background: var(--tb); }
    .tst-body { flex: 1 1 auto; min-width: 0; padding-top: 1px; }
    .tst-title { font-weight: 700; font-size: .86rem; line-height: 1.3; }
    .tst-msg { font-size: .8rem; color: var(--muted); margin-top: 2px; word-wrap: break-word; }
    .tst-msg ul { margin: .2rem 0 0; padding-left: 1rem; }
    .tst-close { border: 0; background: transparent; color: #94a3b8; padding: 0 .1rem; font-size: 1.1rem; line-height: 1; }
    .tst-close:hover { color: var(--ink); }
    .tst-bar { position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: rgba(0,0,0,.04); }
    .tst-bar > span { display: block; height: 100%; width: 100%; background: var(--tc); transform-origin: left; animation: tstRun linear forwards; }
    .tst:hover .tst-bar > span { animation-play-state: paused; }
    @keyframes tstRun { from { transform: scaleX(1); } to { transform: scaleX(0); } }
    .tst.loading .tst-bar > span { width: 35%; animation: tstSlide 1s ease-in-out infinite !important; }
    @keyframes tstSlide { from { transform: translateX(-100%); } to { transform: translateX(290%); } }
    .tst.loading .tst-icon i { animation: spin .9s linear infinite; display: inline-block; }
    .tst.success { --tc: #059669; --tb: #ecfdf5; } .tst.error { --tc: #dc2626; --tb: #fef2f2; } .tst.warning { --tc: #d97706; --tb: #fffbeb; }
    .tst.info { --tc: #0284c7; --tb: #f0f9ff; } .tst.loading { --tc: #4f46e5; --tb: #eef2ff; }
    @media (max-width: 575.98px) { .toast-stack { top: 8px; right: 8px; width: calc(100vw - 16px); } }
    @media (prefers-reduced-motion: reduce) { .tst, .tst.out { animation: none; } }
    @media print { .toast-stack { display: none !important; } }
</style>
<div class="toast-stack" id="toastStack" aria-live="polite" aria-atomic="false"></div>
<script>
(function () {
    const stack = document.getElementById('toastStack');
    const ICON = {success: 'bi-check-circle-fill', error: 'bi-x-octagon-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill', loading: 'bi-arrow-repeat'};
    const TITLE = {success: 'Done', error: 'Something went wrong', warning: 'Please check', info: 'Note', loading: 'Working…'};
    const LIFE = {success: 4500, error: 9000, warning: 7000, info: 4500, loading: 0};
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    let loadingToast = null;

    function notify(o) {
        o = Object.assign({type: 'info'}, typeof o === 'string' ? {message: o} : o);
        // only one "loading" toast at a time: reuse it
        if (o.type === 'loading' && loadingToast && loadingToast.alive()) { loadingToast.update(o); return loadingToast; }
        const el = document.createElement('div');
        el.setAttribute('role', o.type === 'error' ? 'alert' : 'status');
        stack.prepend(el);
        while (stack.children.length > 5) stack.lastElementChild.remove();
        let timer = null, closed = false;
        const close = () => {
            if (closed) return; closed = true; clearTimeout(timer);
            el.classList.add('out'); setTimeout(() => el.remove(), 320);
        };
        const render = (x) => {
            const life = x.duration ?? LIFE[x.type];
            el.className = 'tst ' + x.type;
            el.innerHTML = `<div class="tst-icon"><i class="bi ${ICON[x.type] || ICON.info}"></i></div>
                <div class="tst-body"><div class="tst-title">${esc(x.title || TITLE[x.type])}</div>
                ${x.message ? `<div class="tst-msg">${esc(x.message)}</div>` : ''}
                ${x.list ? `<div class="tst-msg"><ul>${x.list.map(i => `<li>${esc(i)}</li>`).join('')}</ul></div>` : ''}</div>
                <button type="button" class="tst-close" aria-label="Close">&times;</button>
                <div class="tst-bar"><span style="animation-duration:${life || 1000}ms"></span></div>`;
            el.querySelector('.tst-close').addEventListener('click', close);
            clearTimeout(timer);
            if (life > 0) {
                // the running bar is the timer: closing when it finishes also respects "pause on hover"
                el.querySelector('.tst-bar > span').addEventListener('animationend', close);
            }
        };
        render(o);
        const handle = {close, alive: () => !closed, update: (x) => { if (!closed) render(Object.assign({}, o, x)); }};
        if (o.type === 'loading') loadingToast = handle;
        return handle;
    }
    window.notify = notify;

    // ----- messages from the server (after a save, delete, import …)
    const flash = @json($flashToasts);
    const showFlash = () => flash.forEach((t, i) => setTimeout(() => notify(t), 250 + i * 180));
    if (document.readyState === 'complete') showFlash(); else window.addEventListener('load', showFlash);
    window.addEventListener('pageshow', e => { if (e.persisted && loadingToast) loadingToast.close(); });

    // ----- every movement
    const pageName = (a) => (a.getAttribute('title') || a.querySelector('.nav-label')?.textContent || a.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 60);
    const isDownload = (a) => a.hasAttribute('download') || /\/(export|pdf|download)(\?|$)|\/template\/|[?&]export=1/.test(a.getAttribute('href') || '');

    document.addEventListener('click', e => {
        const a = e.target.closest('a[href]');
        if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) return;
        const href = a.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || a.hasAttribute('data-bs-toggle')) return;
        if (href.startsWith('mailto:') || href.startsWith('tel:')) { notify({type: 'info', title: 'Opening your mail / phone app'}); return; }
        let url; try { url = new URL(a.href); } catch (err) { return; }
        if (url.origin !== location.origin) { notify({type: 'info', title: 'Opening external link', message: url.host}); return; }
        if (isDownload(a)) { notify({type: 'success', title: 'Preparing your file', message: (pageName(a) || 'Download') + ' — it will download in a moment.', duration: 4000}); return; }
        if (a.target === '_blank') { notify({type: 'info', title: 'Opened in a new tab', message: pageName(a)}); return; }
        if (a.id === 'backBtn') { notify({type: 'loading', title: 'Going back…'}); return; }
        if (url.pathname === location.pathname && url.search === location.search) return;
        notify({type: 'loading', title: 'Opening…', message: pageName(a) || url.pathname});
    });

    // filters: say what is being applied
    document.addEventListener('change', e => {
        const el = e.target;
        const form = el.closest('form.auto-filter');
        if (!form || !el.name) return;
        let label = el.getAttribute('aria-label') || el.getAttribute('title') || el.getAttribute('placeholder') || el.name.replace(/_id$/, '').replace(/[_\[\]]+/g, ' ');
        let value = el.tagName === 'SELECT' ? el.options[el.selectedIndex]?.text : (el.type === 'checkbox' ? (el.checked ? 'on' : 'off') : el.value);
        notify({type: 'loading', title: 'Applying filter…', message: `${label.charAt(0).toUpperCase() + label.slice(1)}: ${value || 'all'}`});
    }, true);
    document.addEventListener('input', e => {
        const form = e.target.closest('form.auto-filter');
        if (form && /^(text|search)$/.test(e.target.type || 'text') && e.target.tagName === 'INPUT') {
            clearTimeout(window.__tstSearch);
            window.__tstSearch = setTimeout(() => notify({type: 'loading', title: 'Searching…', message: e.target.value ? `“${e.target.value}”` : 'Showing everything'}), 450);
        }
    }, true);

    // forms: saving / searching / required fields
    document.addEventListener('submit', e => {
        const f = e.target;
        if (e.defaultPrevented) return; // waiting for the confirmation dialog
        const method = (f.getAttribute('method') || 'get').toLowerCase();
        if (f.classList.contains('auto-filter')) return;
        if (f.classList.contains('year-switch')) { notify({type: 'loading', title: 'Switching year…', message: 'Loading ' + f.querySelector('select').value + ' data'}); return; }
        if (method === 'get') {
            if (/export|template/.test(f.getAttribute('action') || '')) notify({type: 'success', title: 'Preparing your file', duration: 4000});
            else notify({type: 'loading', title: 'Loading…'});
            return;
        }
        const btn = e.submitter;
        const what = (f.dataset.busy || btn?.dataset.busy || btn?.getAttribute('title') || btn?.textContent || '').replace(/\s+/g, ' ').trim();
        const del = f.querySelector('input[name=_method]')?.value === 'DELETE';
        notify({type: 'loading', title: del ? 'Deleting…' : 'Saving…', message: what || 'Please wait'});
    });
    document.addEventListener('invalid', e => {
        clearTimeout(window.__tstInvalid);
        const el = e.target;
        const lbl = el.labels?.[0]?.textContent?.replace(/\*/g, '').trim() || el.getAttribute('placeholder') || el.name;
        window.__tstInvalid = setTimeout(() => notify({type: 'warning', title: 'Some fields need attention', message: (lbl ? lbl + ': ' : '') + el.validationMessage}), 30);
    }, true);

    // confirmation dialog: confirmed → saving toast is shown by the submit; closed without confirming → cancelled
    const cm = document.getElementById('confirmModal');
    if (cm) {
        cm.addEventListener('hidden.bs.modal', () => {
            // the wait overlay is shown only when the action was confirmed
            if (document.getElementById('busyOverlay')?.classList.contains('show')) {
                notify({type: 'loading', title: 'Saving…', message: document.getElementById('busyText')?.textContent || 'Please wait'});
            } else {
                notify({type: 'info', title: 'Cancelled', message: 'Nothing was changed.', duration: 3000});
            }
        });
    }
    // top-bar year switcher submits from its own change handler
    document.querySelector('.year-switch select')?.addEventListener('change', e =>
        notify({type: 'loading', title: 'Switching year…', message: 'Loading ' + e.target.value + ' data on every page'}));

    // live IST digital clock — follows the server clock even if this PC's clock or time zone is wrong
    const clock = document.getElementById('liveClock');
    if (clock) {
        const skew = Number(clock.dataset.serverMs) - Date.now();
        const fmt = new Intl.DateTimeFormat('en-IN', {timeZone: 'Asia/Kolkata', hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit',
            weekday: 'short', day: '2-digit', month: 'short', year: 'numeric'});
        const q = s => clock.querySelector(s);
        const tick = () => {
            const p = Object.fromEntries(fmt.formatToParts(new Date(Date.now() + skew)).map(x => [x.type, x.value]));
            q('[data-h]').textContent = p.hour; q('[data-m]').textContent = p.minute; q('[data-s]').textContent = p.second;
            q('[data-ap]').textContent = (p.dayPeriod || '').toUpperCase();
            q('[data-d]').textContent = `${p.weekday}, ${p.day} ${p.month} ${p.year}`;
            setTimeout(tick, 1000 - ((Date.now() + skew) % 1000) + 5);
        };
        tick();
    }

    // side menu: collapse to icons / expand (desktop), remembered in this browser
    const sbIcon = () => { const i = document.querySelector('#sidebarToggle i'); if (i) i.className = 'bi ' + (document.documentElement.classList.contains('sb-mini') ? 'bi-layout-sidebar' : 'bi-layout-sidebar-inset'); };
    sbIcon();
    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        const mini = document.documentElement.classList.toggle('sb-mini');
        sbIcon();
        try { localStorage.setItem('sb-mini', mini ? '1' : '0'); } catch (e) {}
        notify({type: 'info', title: mini ? 'Menu collapsed' : 'Menu expanded', message: mini ? 'Point at the menu to open it, or click the button again.' : '', duration: 2200});
        setTimeout(() => dispatchEvent(new Event('resize')), 280); // frozen register columns re-measure
    });

    // colour themes (top bar palette)
    const markTheme = () => {
        const cur = document.documentElement.getAttribute('data-theme');
        document.querySelectorAll('[data-theme-set]').forEach(b => b.classList.toggle('active', b.dataset.themeSet === cur));
    };
    markTheme();
    document.querySelectorAll('[data-theme-set]').forEach(b => b.addEventListener('click', () => {
        document.documentElement.setAttribute('data-theme', b.dataset.themeSet);
        try { localStorage.setItem('app-theme', b.dataset.themeSet); } catch (e) {}
        markTheme();
        notify({type: 'success', title: 'Theme changed', message: b.textContent.trim() + ' is now used on every page.', duration: 3000});
    }));

    // connection
    window.addEventListener('offline', () => notify({type: 'error', title: 'You are offline', message: 'Check the network connection before saving anything.', duration: 0}));
    window.addEventListener('online', () => notify({type: 'success', title: 'Back online'}));
    // copy
    document.addEventListener('copy', () => notify({type: 'success', title: 'Copied to clipboard', duration: 2000}));
    // print
    window.addEventListener('beforeprint', () => notify({type: 'info', title: 'Preparing to print', duration: 2500}));
})();
</script>
