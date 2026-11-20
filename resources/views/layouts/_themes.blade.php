{{--
    Five colour themes. The choice is stored in this browser (localStorage) and applied before the page paints,
    so there is no flash of the wrong colours. Default: Purple & Orange.
--}}
<script>
    (function () {
        var t = 'purple-orange';
        try { t = localStorage.getItem('app-theme') || t; } catch (e) {}
        document.documentElement.setAttribute('data-theme', t);
    })();
</script>
<style>
    /* 1 · Purple & Orange (default): purple menu, warm orange highlights */
    :root, html[data-theme="purple-orange"] {
        --sb-bg: linear-gradient(180deg, #2e1065 0%, #4c1d95 50%, #6d28d9 100%);
        --sb-glow-1: rgba(251,146,60,.22); --sb-glow-2: rgba(236,72,153,.2);
        --sb-text: #f5f3ff; --sb-muted: rgba(237,233,254,.6);
        --marker: #fb923c; --marker-ink: #fff;
        --logo-bg: linear-gradient(135deg, #fb923c, #ea580c);
        --accent: #7c3aed; --accent-dark: #6d28d9; --accent-ink: #5b21b6; --accent-soft: #f3e8ff; --accent-softer: #faf5ff;
        --accent-line: #ddd6fe; --accent-shadow: rgba(124,58,237,.22);
        --accent-2: #f97316; --accent-2-soft: #fff7ed;
        --body-bg: linear-gradient(160deg, #f7f2ff 0%, #fdf8f4 60%, #fff4ea 100%);
        --body-band: linear-gradient(120deg, rgba(124,58,237,.10), rgba(249,115,22,.08));
        --hero: linear-gradient(120deg, #3b0764, #6d28d9 55%, #ea580c);
        --bar: linear-gradient(180deg, #fdba74, #f97316);
    }
    /* 2 · Royal Purple */
    html[data-theme="purple"] {
        --sb-bg: linear-gradient(180deg, #1e1b4b 0%, #3b0764 55%, #581c87 100%);
        --sb-glow-1: rgba(167,139,250,.25); --sb-glow-2: rgba(217,70,239,.18);
        --sb-text: #f5f3ff; --sb-muted: rgba(221,214,254,.6);
        --marker: #c4b5fd; --marker-ink: #3b0764;
        --logo-bg: linear-gradient(135deg, #a78bfa, #7c3aed);
        --accent: #7c3aed; --accent-dark: #6d28d9; --accent-ink: #5b21b6; --accent-soft: #f3e8ff; --accent-softer: #faf5ff;
        --accent-line: #ddd6fe; --accent-shadow: rgba(124,58,237,.22);
        --accent-2: #a855f7; --accent-2-soft: #faf5ff;
        --body-bg: linear-gradient(160deg, #f5f3ff 0%, #faf5ff 100%);
        --body-band: linear-gradient(120deg, rgba(124,58,237,.12), rgba(192,132,252,.08));
        --hero: linear-gradient(120deg, #2e1065, #6d28d9 60%, #a855f7);
        --bar: linear-gradient(180deg, #c4b5fd, #7c3aed);
    }
    /* 3 · Sunset Orange */
    html[data-theme="orange"] {
        --sb-bg: linear-gradient(180deg, #7c2d12 0%, #c2410c 55%, #ea580c 100%);
        --sb-glow-1: rgba(253,224,71,.22); --sb-glow-2: rgba(244,63,94,.2);
        --sb-text: #fff7ed; --sb-muted: rgba(255,237,213,.65);
        --marker: #fde047; --marker-ink: #7c2d12;
        --logo-bg: linear-gradient(135deg, #fde047, #f59e0b);
        --accent: #ea580c; --accent-dark: #c2410c; --accent-ink: #9a3412; --accent-soft: #ffedd5; --accent-softer: #fff7ed;
        --accent-line: #fed7aa; --accent-shadow: rgba(234,88,12,.22);
        --accent-2: #e11d48; --accent-2-soft: #fff1f2;
        --body-bg: linear-gradient(160deg, #fff7ed 0%, #fffbeb 100%);
        --body-band: linear-gradient(120deg, rgba(234,88,12,.12), rgba(245,158,11,.08));
        --hero: linear-gradient(120deg, #7c2d12, #ea580c 60%, #f59e0b);
        --bar: linear-gradient(180deg, #fdba74, #ea580c);
    }
    /* 4 · Ocean Blue */
    html[data-theme="ocean"] {
        --sb-bg: linear-gradient(180deg, #0c1e3e 0%, #0c4a6e 55%, #0369a1 100%);
        --sb-glow-1: rgba(34,211,238,.25); --sb-glow-2: rgba(59,130,246,.2);
        --sb-text: #f0f9ff; --sb-muted: rgba(186,230,253,.65);
        --marker: #22d3ee; --marker-ink: #0c4a6e;
        --logo-bg: linear-gradient(135deg, #22d3ee, #0284c7);
        --accent: #0284c7; --accent-dark: #0369a1; --accent-ink: #075985; --accent-soft: #e0f2fe; --accent-softer: #f0f9ff;
        --accent-line: #bae6fd; --accent-shadow: rgba(2,132,199,.22);
        --accent-2: #06b6d4; --accent-2-soft: #ecfeff;
        --body-bg: linear-gradient(160deg, #f0f9ff 0%, #f8fafc 100%);
        --body-band: linear-gradient(120deg, rgba(2,132,199,.12), rgba(6,182,212,.08));
        --hero: linear-gradient(120deg, #0c1e3e, #0369a1 60%, #06b6d4);
        --bar: linear-gradient(180deg, #7dd3fc, #0284c7);
    }
    /* 5 · Emerald Green */
    html[data-theme="emerald"] {
        --sb-bg: linear-gradient(180deg, #022c22 0%, #064e3b 55%, #047857 100%);
        --sb-glow-1: rgba(163,230,53,.2); --sb-glow-2: rgba(45,212,191,.2);
        --sb-text: #ecfdf5; --sb-muted: rgba(167,243,208,.65);
        --marker: #a3e635; --marker-ink: #064e3b;
        --logo-bg: linear-gradient(135deg, #34d399, #059669);
        --accent: #059669; --accent-dark: #047857; --accent-ink: #065f46; --accent-soft: #d1fae5; --accent-softer: #f0fdf4;
        --accent-line: #a7f3d0; --accent-shadow: rgba(5,150,105,.22);
        --accent-2: #0d9488; --accent-2-soft: #f0fdfa;
        --body-bg: linear-gradient(160deg, #f0fdf4 0%, #f7fee7 100%);
        --body-band: linear-gradient(120deg, rgba(5,150,105,.12), rgba(132,204,22,.08));
        --hero: linear-gradient(120deg, #022c22, #047857 60%, #14b8a6);
        --bar: linear-gradient(180deg, #6ee7b7, #059669);
    }

    /* bootstrap + shared components follow the theme */
    html[data-theme] { --bs-primary: var(--accent); --bs-link-color: var(--accent); --bs-link-hover-color: var(--accent-dark); --indigo: var(--accent); }
    a { color: var(--accent); } a:hover { color: var(--accent-dark); }
    .text-primary { color: var(--accent) !important; }
    .bg-primary:not(.badge) { background-color: var(--accent) !important; }
    .form-check-input:checked { background-color: var(--accent); border-color: var(--accent); }
    .form-control:focus, .form-select:focus { border-color: var(--accent-line); box-shadow: 0 0 0 .2rem var(--accent-shadow); }
    ::selection { background: var(--accent-line); }
    :focus-visible { outline-color: var(--accent-shadow); }
    #topProgress { background: linear-gradient(90deg, var(--accent), var(--accent-2)); }
    .avatar { background: linear-gradient(135deg, var(--accent), var(--accent-2)); }
    .card-header:has(form.auto-filter), .card-body:has(> form.auto-filter) { background: var(--accent-softer); }
    .table thead th { background: var(--accent-softer); color: var(--accent-ink); }
    .ts-wrapper.focus .ts-control { border-color: var(--accent-line); box-shadow: 0 0 0 .2rem var(--accent-shadow); }
    .tst.loading { --tc: var(--accent); --tb: var(--accent-soft); }

    /* dashboard */
    .dash-hero { background: radial-gradient(circle at 85% 20%, rgba(255,255,255,.18), transparent 40%), var(--hero); }
    .bars .bar i { background: var(--bar); }
    .bars .bar.current i { outline-color: var(--accent); }
    .donut { background: conic-gradient(#db2777 0 calc(var(--p2) * 1%), var(--accent) calc(var(--p2) * 1%) calc(var(--p1) * 1%), #e7ebf3 calc(var(--p1) * 1%) 100%); }
    .legend-dot.accent { background: var(--accent) !important; }
    .rank-n { background: var(--accent-2-soft); color: var(--accent-2); }
    .mini-progress > span { background: linear-gradient(90deg, var(--accent-line), var(--accent-2)); }

    /* live IST clock in the header */
    .live-clock { display: flex; align-items: center; gap: .55rem; padding: .3rem .7rem .3rem .35rem; border-radius: 12px; background: var(--sb-bg); color: #fff;
        box-shadow: 0 4px 14px var(--accent-shadow); white-space: nowrap; }
    .live-clock .lc-icon { width: 28px; height: 28px; border-radius: 9px; display: grid; place-items: center; background: rgba(255,255,255,.16); color: var(--marker); }
    .live-clock .lc-time { font-family: 'JetBrains Mono', ui-monospace, 'Cascadia Mono', Consolas, monospace; font-variant-numeric: tabular-nums; font-size: 1.05rem;
        font-weight: 700; letter-spacing: .02em; text-shadow: 0 0 10px rgba(255,255,255,.35); }
    .live-clock .lc-time b { animation: lcBlink 1s steps(1) infinite; color: var(--marker); padding: 0 1px; }
    .live-clock .lc-time small { font-size: .62rem; font-weight: 700; margin-left: .3rem; opacity: .85; letter-spacing: .05em; }
    .live-clock .lc-meta { flex-direction: column; line-height: 1.1; font-size: .66rem; border-left: 1px solid rgba(255,255,255,.22); padding-left: .55rem; opacity: .9; }
    .live-clock .lc-tz { font-weight: 700; color: var(--marker); letter-spacing: .08em; }
    @keyframes lcBlink { 50% { opacity: .15; } }
    @media (max-width: 575.98px) { .live-clock { padding: .25rem .5rem; } .live-clock .lc-icon, .live-clock .lc-time small { display: none; } .live-clock .lc-time { font-size: .9rem; } }
    @media (max-width: 399.98px) { .live-clock .lc-time [data-s], .live-clock .lc-time b:last-of-type { display: none; } }
    @media (prefers-reduced-motion: reduce) { .live-clock .lc-time b { animation: none; } }

    /* theme picker */
    .theme-pick .dropdown-menu { min-width: 230px; }
    .theme-pick .swatch { width: 26px; height: 26px; border-radius: 8px; flex: 0 0 26px; box-shadow: inset 0 0 0 2px rgba(255,255,255,.4); }
    .theme-pick .dropdown-item.active, .theme-pick .dropdown-item:active { background: var(--accent-soft); color: var(--accent-ink); }
    .theme-pick .dropdown-item .bi-check2 { visibility: hidden; color: var(--accent); }
    .theme-pick .dropdown-item.active .bi-check2 { visibility: visible; }
    .btn-theme { width: 38px; height: 38px; border-radius: 11px; border: 1px solid var(--line); background: #fff; display: grid; place-items: center; padding: 0; color: var(--accent); font-size: 1.05rem; }
    .btn-theme:hover { background: var(--accent-soft); }
</style>
