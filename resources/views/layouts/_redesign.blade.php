{{-- "Clean" design layer applied on top of _theme to every page: light sidebar, calm surfaces, soft badges, toolbar filters. --}}
<style>
    :root {
        --bg: #f5f6fa; --surface: #fff; --line: #e6e9f0; --line-2: #eef0f5; --ink: #111827; --muted: #6b7280;
        --radius: 14px; --shadow: 0 1px 2px rgba(16,24,40,.04), 0 1px 3px rgba(16,24,40,.06); --shadow-lg: 0 16px 40px rgba(16,24,40,.12);
        --accent: #4f46e5; --accent-soft: #eef2ff;
    }
    body { background: var(--body-bg, var(--bg)); background-attachment: fixed; color: var(--ink); font-size: .885rem; }
    .main::after { content: ''; position: absolute; left: 0; right: 0; top: 0; height: 280px; z-index: 0; pointer-events: none; background: var(--body-band); }
    

    /* ---------- sidebar: coloured by the theme (see _themes) ---------- */
    .sidebar { background: var(--sb-bg); color: var(--sb-text); border-right: 0; box-shadow: 4px 0 24px rgba(0,0,0,.12); }
    .sidebar::before { display: block; background: radial-gradient(circle at 0% 0%, var(--sb-glow-1), transparent 45%), radial-gradient(circle at 100% 100%, var(--sb-glow-2), transparent 50%); }
    .sidebar .brand { border-bottom: 1px solid rgba(255,255,255,.12); }
    .brand-logo { background: var(--logo-bg); box-shadow: 0 6px 16px rgba(0,0,0,.25); color: #fff; }
    .brand-sub { color: var(--sb-muted); opacity: 1; }
    .sidebar-nav { scrollbar-color: rgba(255,255,255,.25) transparent; }
    .nav-section { color: var(--sb-muted); letter-spacing: .08em; padding: 1.1rem .7rem .4rem; }
    .nav-item { color: var(--sb-text); font-weight: 500; padding: .48rem .6rem; border-radius: 10px; position: relative; opacity: .9; }
    .nav-item:hover { background: rgba(255,255,255,.1); color: #fff; transform: translateX(2px); opacity: 1; }
    .nav-item.active { background: rgba(255,255,255,.18); color: #fff; font-weight: 600; opacity: 1; box-shadow: 0 4px 14px rgba(0,0,0,.12); }
    .nav-item.active::before { content: ''; position: absolute; left: -10px; top: 8px; bottom: 8px; width: 4px; border-radius: 0 4px 4px 0; background: var(--marker); }
    .sidebar .nav-icon { width: 30px; height: 30px; flex-basis: 30px; border-radius: 9px; box-shadow: none; font-size: .9rem; background: rgba(255,255,255,.12) !important; color: #fff; }
    .nav-item.active .nav-icon { background: var(--marker) !important; color: var(--marker-ink) !important; }
    .page-icon.c-indigo { background: #eef2ff; color: #4f46e5; } .page-icon.c-violet { background: #f5f3ff; color: #7c3aed; }
    .page-icon.c-cyan { background: #ecfeff; color: #0891b2; } .page-icon.c-blue { background: #eff6ff; color: #2563eb; }
    .page-icon.c-green { background: #ecfdf5; color: #059669; } .page-icon.c-orange { background: #fff7ed; color: #ea580c; }
    .page-icon.c-pink { background: #fdf2f8; color: #db2777; } .page-icon.c-red { background: #fef2f2; color: #dc2626; }
    .page-icon { background: var(--accent-soft) !important; color: var(--accent) !important; }
    .sidebar-foot { background: rgba(0,0,0,.16); border-top: 1px solid rgba(255,255,255,.1); color: #fff; }
    .sidebar-foot .text-white-50 { color: rgba(255,255,255,.65) !important; }

    /* ---------- top bar & page header ---------- */
    .main::before { display: none; }
    .topbar { background: rgba(255,255,255,.9); border-bottom: 1px solid var(--line); padding: .55rem 1.5rem; }
    .page-icon { box-shadow: none; border-radius: 11px; }
    .page-title { font-size: 1.15rem; font-weight: 700; letter-spacing: -.01em; }
    .btn-back { background: #fff; border: 1px solid var(--line); color: #374151; }
    .btn-back:hover { background: var(--accent-soft); color: var(--accent); border-color: var(--accent-line); }
    .btn-user { border-color: var(--line); }
    .content { padding: 1.5rem 1.75rem; max-width: 1680px; width: 100%; }
    .content > * { animation-duration: .35s; }

    /* ---------- cards ---------- */
    .card { border: 1px solid var(--line); border-radius: var(--radius); box-shadow: var(--shadow); }
    .card-header { background: #fff; border-bottom: 1px solid var(--line-2); padding: .8rem 1.1rem; font-weight: 600; color: var(--ink); }
    .card > .card-header:first-child::before { display: none; }
    .card-header > span > .bi:first-child, .card-header > .bi:first-child { color: var(--accent); margin-right: .2rem; }
    /* filter toolbars */
    .card-header:has(form.auto-filter), .card-body:has(> form.auto-filter) { background: #fafbfd; }
    form.auto-filter .form-select-sm, form.auto-filter .form-control-sm, form.auto-filter .ts-control { background-color: #fff; border-color: var(--line); min-height: 34px; }
    form.auto-filter .btn-link { color: var(--muted); text-decoration: none; }
    form.auto-filter .btn-link:hover { color: var(--accent); }
    .card-footer { background: #fafbfd; border-top: 1px solid var(--line-2); }

    /* ---------- buttons ---------- */
    .btn { border-radius: 9px; font-weight: 500; box-shadow: none; }
    .btn-sm { padding: .3rem .7rem; }
    .btn-primary { background: var(--accent); border: 1px solid var(--accent); box-shadow: 0 1px 2px var(--accent-shadow); }
    .btn-primary:hover, .btn-primary:focus { background: var(--accent-dark); border-color: var(--accent-dark); box-shadow: 0 4px 12px var(--accent-shadow); }
    .btn-success { background: #059669; border: 1px solid #059669; box-shadow: none; } .btn-success:hover { background: #047857; border-color: #047857; }
    .btn-danger { background: #dc2626; border: 1px solid #dc2626; } .btn-danger:hover { background: #b91c1c; border-color: #b91c1c; }
    .btn-warning { background: #f59e0b; border: 1px solid #f59e0b; }
    .btn-light { background: #f3f4f6; border-color: #f3f4f6; } .btn-light:hover { background: #e5e7eb; }
    [class*="btn-outline-"] { background: #fff; }
    .btn-outline-primary { color: var(--accent); border-color: var(--accent-line); } .btn-outline-primary:hover { background: var(--accent-soft); color: var(--accent-dark); border-color: var(--accent-line); }
    .btn-outline-success { color: #047857; border-color: #a7f3d0; } .btn-outline-success:hover { background: #ecfdf5; color: #065f46; border-color: #6ee7b7; }
    .btn-outline-danger { color: #b91c1c; border-color: #fecaca; } .btn-outline-danger:hover { background: #fef2f2; color: #991b1b; border-color: #fca5a5; }
    .btn-outline-secondary { color: #374151; border-color: var(--line); } .btn-outline-secondary:hover { background: #f3f4f6; color: var(--ink); border-color: #d1d5db; }

    /* ---------- forms ---------- */
    .form-control, .form-select { border-radius: 9px; border-color: #dfe3eb; }
    .form-control:focus, .form-select:focus { border-color: var(--accent-line); box-shadow: 0 0 0 .2rem var(--accent-shadow); }
    .form-label { color: #374151; font-weight: 600; }
    .input-group-text { background: #f9fafb; border-color: #dfe3eb; }

    /* ---------- tables ---------- */
    .table { --bs-table-hover-bg: var(--accent-softer); --bs-table-striped-bg: #fafbfd; }
    .table thead th { background: #f9fafb; color: #6b7280; font-size: .7rem; letter-spacing: .05em; font-weight: 600; border-bottom: 1px solid var(--line) !important; padding-top: .65rem; padding-bottom: .65rem; }
    .table > :not(caption) > * > * { border-bottom-color: var(--line-2); }
    .table td:first-child, .table th:first-child { padding-left: 1.1rem; } .table td:last-child, .table th:last-child { padding-right: 1.1rem; }
    .table tbody tr { transition: background .12s; }
    .table-responsive > .table thead th { position: sticky; top: 0; z-index: 1; }
    .group-row td { background: var(--accent-softer) !important; color: var(--accent-ink); }
    .total-row td { background: #f9fafb !important; }

    /* ---------- soft badges ---------- */
    .badge { font-weight: 600; border-radius: 7px; padding: .35em .6em; }
    .badge.bg-success { background: #dcfce7 !important; color: #166534 !important; }
    .badge.bg-danger { background: #fee2e2 !important; color: #991b1b !important; }
    .badge.bg-warning { background: #fef3c7 !important; color: #92400e !important; }
    .badge.bg-info { background: #e0f2fe !important; color: #075985 !important; }
    .badge.bg-primary { background: #e0e7ff !important; color: var(--accent-ink) !important; }
    .badge.bg-secondary { background: #f3f4f6 !important; color: #374151 !important; }
    .badge.bg-dark { background: #e5e7eb !important; color: #111827 !important; }
    .badge.bg-light { background: #f3f4f6 !important; color: #374151 !important; border: 1px solid var(--line); }
    .dash-hero .badge.bg-success, .dash-hero .badge.bg-danger { background: rgba(255,255,255,.95) !important; }

    /* ---------- pills, alerts, pagination, lists ---------- */
    .nav-pills { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 4px; display: inline-flex; flex-wrap: wrap; gap: 2px; }
    .nav-pills .nav-link { border: 0; background: transparent; color: #4b5563; border-radius: 9px; padding: .4rem .85rem; font-weight: 500; }
    .nav-pills .nav-link:hover { background: #f5f6fa; }
    .nav-pills .nav-link.active { background: var(--accent); color: #fff; box-shadow: 0 1px 3px var(--accent-shadow); }
    .nav-pills .nav-link.active .badge { background: rgba(255,255,255,.25) !important; color: #fff !important; border: 0; }
    .alert { border: 1px solid; border-left-width: 4px; border-radius: 12px; box-shadow: none; }
    .alert-success { background: #f0fdf4; border-color: #bbf7d0; border-left-color: #16a34a; color: #14532d; }
    .alert-danger { background: #fef2f2; border-color: #fecaca; border-left-color: #dc2626; color: #7f1d1d; }
    .alert-warning { background: #fffbeb; border-color: #fde68a; border-left-color: #d97706; color: #78350f; }
    .alert-info { background: #f0f9ff; border-color: #bae6fd; border-left-color: #0284c7; color: #0c4a6e; }
    .pagination { gap: 4px; }
    .page-link { border: 1px solid var(--line); color: #374151; border-radius: 8px !important; min-width: 34px; text-align: center; }
    .page-item.active .page-link { background: var(--accent); border-color: var(--accent); }
    .list-group-item { border-color: var(--line-2); }
    .list-group-item-action:hover { background: var(--accent-softer); }
    .modal-content { border: 0; border-radius: 18px; box-shadow: var(--shadow-lg); }
    .dropdown-menu { border: 1px solid var(--line); box-shadow: var(--shadow-lg); }

    /* ---------- tiles used on other pages (backups, import summary) ---------- */
    .stat-card { background: #fff !important; color: var(--ink); border: 1px solid var(--line); box-shadow: var(--shadow); }
    .stat-card::before, .stat-card::after { display: none; }
    .stat-card .opacity-75 { color: var(--muted); opacity: 1 !important; }
    .stat-card .icon { background: var(--accent-soft); color: var(--accent); }
    .stat-card.grad-2 .icon { background: #fff7ed; color: #ea580c; } .stat-card.grad-3 .icon { background: #ecfeff; color: #0891b2; }
    .stat-card.grad-4 .icon { background: #fdf2f8; color: #db2777; } .stat-card.grad-5 .icon { background: #ecfdf5; color: #059669; }

    /* KPI tiles: soft icons instead of gradients */
    .kpi { box-shadow: var(--shadow); }
    .kpi-icon { box-shadow: none; }
    .kpi-icon.c-indigo { background: #eef2ff; color: #4f46e5; } .kpi-icon.c-violet { background: #f5f3ff; color: #7c3aed; }
    .kpi-icon.c-blue { background: #eff6ff; color: #2563eb; } .kpi-icon.c-green { background: #ecfdf5; color: #059669; }
    .kpi-icon.c-orange { background: #fff7ed; color: #ea580c; } .kpi-icon.c-pink { background: #fdf2f8; color: #db2777; }
    .kpi-icon.c-cyan { background: #ecfeff; color: #0891b2; } .kpi-icon.c-red { background: #fef2f2; color: #dc2626; }

    /* workflow steps */
    .wf-step.s-done { background: linear-gradient(90deg, #f0fdf4, #fff 40%); }
    .wf-step.s-attention { background: linear-gradient(90deg, #fffbeb, #fff 40%); }

    /* top bar year switcher & mobile */
    .year-switch { background: #fff; border-color: var(--line); color: #374151; }
    .year-switch .bi { color: var(--accent); }
    .year-switch select { background-color: var(--accent-soft); color: var(--accent-ink); }
    .year-switch.is-past { background: #fff7ed; border-color: #fed7aa; }
    .year-switch.is-past select { background-color: #fff; color: #9a3412; }
    .mobile-nav { background: rgba(255,255,255,.96); }
    #toTop { background: var(--accent); border-radius: 12px; }

    @media (max-width: 991.98px) {
        .sidebar { box-shadow: var(--shadow-lg); }
        .content { padding: 1rem; }
    }
    @media (max-width: 575.98px) {
        .nav-pills { display: flex; }
        .table td:first-child, .table th:first-child { padding-left: .6rem; } .table td:last-child, .table th:last-child { padding-right: .6rem; }
    }
</style>
