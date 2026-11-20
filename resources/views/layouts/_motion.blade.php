{{--
    Speed + motion layer (no change to what any page does):
      • pages are fetched in the background when the pointer rests on a link (Speculation Rules), so clicks open instantly
      • smooth cross-page transitions (View Transitions) with the menu and top bar staying still
      • full-screen loader only on the first page of a visit
      • rows, cards and numbers animate in; buttons ripple; sections reveal while scrolling
    Everything respects "reduce motion".
--}}
<script type="speculationrules">
{
    "prefetch": [{
        "where": {"and": [
            {"href_matches": "/*"},
            {"not": {"href_matches": "/logout"}},
            {"not": {"href_matches": "/*/export*"}},
            {"not": {"href_matches": "/*/pdf*"}},
            {"not": {"href_matches": "/*/download*"}},
            {"not": {"href_matches": "/imports/template/*"}},
            {"not": {"href_matches": "/*\\?*export=*"}},
            {"not": {"selector_matches": "[data-no-prefetch], [download], [target=_blank]"}}
        ]},
        "eagerness": "moderate"
    }]
}
</script>
<style>
    /* ---------- page-to-page transitions ---------- */
    @view-transition { navigation: auto; }
    .sidebar { view-transition-name: sidebar; }
    .topbar { view-transition-name: topbar; }
    .mobile-nav { view-transition-name: mobilenav; }
    ::view-transition-group(sidebar), ::view-transition-group(topbar), ::view-transition-group(mobilenav) { animation: none; }
    ::view-transition-old(sidebar), ::view-transition-new(sidebar), ::view-transition-old(topbar), ::view-transition-new(topbar),
    ::view-transition-old(mobilenav), ::view-transition-new(mobilenav) { animation: none; mix-blend-mode: normal; }
    ::view-transition-old(root) { animation: vtOut .16s ease both; }
    ::view-transition-new(root) { animation: vtIn .26s cubic-bezier(.2,.8,.2,1) both; }
    @keyframes vtOut { to { opacity: 0; transform: translateY(-4px); } }
    @keyframes vtIn { from { opacity: 0; transform: translateY(8px); } }
    #pageLoader.instant { transition: none !important; }

    /* ---------- table rows, cards, reveal ---------- */
    .table > tbody > tr.row-in { animation: rowIn .38s cubic-bezier(.2,.8,.2,1) both; animation-delay: calc(var(--i, 0) * 16ms); }
    @keyframes rowIn { from { opacity: 0; transform: translateX(-6px); } }
    .reveal { opacity: 0; transform: translateY(14px); transition: opacity .5s ease, transform .5s cubic-bezier(.2,.8,.2,1); }
    .reveal.shown { opacity: 1; transform: none; }
    .card { transition: box-shadow .2s ease, transform .2s ease, border-color .2s; }
    .card:hover { box-shadow: 0 6px 22px rgba(16,24,40,.08); }
    .kpi:hover, .stat-card:hover, .cel-row:hover { transform: translateY(-2px); }
    .nav-item .nav-icon { transition: transform .2s cubic-bezier(.2,.9,.3,1.4); }
    .nav-item:hover .nav-icon { transform: scale(1.12) rotate(-4deg); }
    .btn .bi { transition: transform .2s; } .btn:hover .bi { transform: scale(1.12); }

    /* ---------- button ripple ---------- */
    .btn { position: relative; overflow: hidden; }
    .ripple { position: absolute; border-radius: 50%; pointer-events: none; transform: scale(0); background: currentColor; opacity: .25; animation: ripple .55s ease-out forwards; }
    @keyframes ripple { to { transform: scale(2.6); opacity: 0; } }

    /* ---------- number count-up highlight ---------- */
    .counting { color: var(--accent); }

    @media (prefers-reduced-motion: reduce) {
        @view-transition { navigation: none; }
        .table > tbody > tr.row-in, .reveal { animation: none !important; opacity: 1 !important; transform: none !important; transition: none !important; }
        .ripple { display: none; }
    }
    @media print { .reveal { opacity: 1 !important; transform: none !important; } }
</style>
<script>
(function () {
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

    // rows slide in (first 40 rows of each table; long tables stay fast)
    if (!reduce) {
        document.querySelectorAll('.content .table > tbody').forEach(tb => {
            [...tb.rows].slice(0, 40).forEach((tr, i) => { tr.style.setProperty('--i', i); tr.classList.add('row-in'); });
        });
    }

    // cards below the fold fade in as they scroll into view
    if (!reduce && 'IntersectionObserver' in window) {
        const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('shown'); io.unobserve(e.target); } }), {rootMargin: '0px 0px -40px 0px'});
        document.querySelectorAll('.content .card, .content .help-sec').forEach(c => {
            if (c.getBoundingClientRect().top > innerHeight) { c.classList.add('reveal'); io.observe(c); }
        });
    }

    // numbers count up (tiles and KPI values)
    if (!reduce) {
        document.querySelectorAll('.kpi-value, .stat-card .fs-3, .stat-card .fs-4, [data-count]').forEach(el => {
            if (el.children.length) return;
            const m = el.textContent.trim().match(/^([^\d-]*)(-?[\d,]+(?:\.\d+)?)(.*)$/);
            if (!m) return;
            const target = parseFloat(m[2].replace(/,/g, '')), dec = (m[2].split('.')[1] || '').length, commas = m[2].includes(',');
            if (!isFinite(target) || target === 0) return;
            const fmt = v => { const s = v.toFixed(dec); return commas ? Number(s).toLocaleString('en-IN', {minimumFractionDigits: dec, maximumFractionDigits: dec}) : s; };
            const t0 = performance.now(), dur = 700;
            el.classList.add('counting');
            const step = now => {
                const k = Math.min(1, (now - t0) / dur), e = 1 - Math.pow(1 - k, 3);
                el.textContent = m[1] + fmt(target * e) + m[3];
                if (k < 1) requestAnimationFrame(step); else { el.textContent = m[1] + m[2] + m[3]; el.classList.remove('counting'); }
            };
            requestAnimationFrame(step);
        });
    }

    // ripple on buttons
    document.addEventListener('pointerdown', e => {
        const b = e.target.closest('.btn');
        if (!b || reduce) return;
        const r = b.getBoundingClientRect(), s = Math.max(r.width, r.height);
        const span = document.createElement('span');
        span.className = 'ripple';
        span.style.cssText = `width:${s}px;height:${s}px;left:${e.clientX - r.left - s / 2}px;top:${e.clientY - r.top - s / 2}px`;
        b.appendChild(span);
        setTimeout(() => span.remove(), 600);
    });
})();
</script>
