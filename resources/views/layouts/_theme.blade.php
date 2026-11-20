<style>
    :root {
        --sidebar-w: 272px;
        --topbar-h: 64px;
        --mnav-h: 64px;
        --ink: #1e2a44;
        --muted: #6b7690;
        --line: #e7ebf3;
        --bg: #f3f5fb;
        --brand1: #4f46e5; --brand2: #7c3aed; --brand3: #06b6d4;
        --indigo: #4f46e5; --violet: #7c3aed; --blue: #2563eb; --cyan: #0891b2; --green: #059669;
        --orange: #ea580c; --pink: #db2777; --red: #dc2626; --amber: #d97706;
        --radius: 16px;
        --shadow-sm: 0 1px 2px rgba(16,24,40,.05);
        --shadow: 0 1px 2px rgba(16,24,40,.04), 0 6px 20px rgba(16,24,40,.06);
        --shadow-lg: 0 12px 32px rgba(16,24,40,.14);
        --bs-body-font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
        --bs-primary: #4f46e5; --bs-primary-rgb: 79, 70, 229;
        --bs-link-color: #4f46e5; --bs-link-color-rgb: 79, 70, 229; --bs-link-hover-color: #3730a3;
    }
    html, body { overflow-x: hidden; }
    html { scroll-behavior: smooth; }
    body { background: var(--bg); color: var(--ink); font-size: .9rem; -webkit-font-smoothing: antialiased; }
    a { text-decoration: none; }
    .min-w-0, .main, .content, .content > *, .card, .card-body { min-width: 0; }
    ::selection { background: #c7d2fe; }
    :focus-visible { outline: 3px solid rgba(99,102,241,.45); outline-offset: 2px; }
    * { scrollbar-width: thin; scrollbar-color: #c7cfe0 transparent; }
    ::-webkit-scrollbar { width: 9px; height: 9px; } ::-webkit-scrollbar-thumb { background: #c7cfe0; border-radius: 9px; }

    /* ================= page loader ================= */
    #pageLoader { position: fixed; inset: 0; z-index: 3000; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 22px;
        background: radial-gradient(circle at 30% 20%, #4c1d95 0%, transparent 55%), radial-gradient(circle at 80% 80%, #0e7490 0%, transparent 50%), #1e1b4b;
        transition: opacity .45s ease, visibility .45s ease; animation: loaderFailsafe 0s linear 6s forwards; }
    #pageLoader.hide { opacity: 0; visibility: hidden; }
    @keyframes loaderFailsafe { to { opacity: 0; visibility: hidden; } }
    .loader-mark { position: relative; width: 96px; height: 96px; }
    .loader-ring { position: absolute; inset: 0; border-radius: 50%; padding: 4px;
        background: conic-gradient(from 0deg, #22d3ee, #a855f7, #ec4899, #f59e0b, #22d3ee);
        -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 5px), #000 calc(100% - 4px)); mask: radial-gradient(farthest-side, transparent calc(100% - 5px), #000 calc(100% - 4px));
        animation: spin 1.1s linear infinite; }
    .loader-ring.r2 { inset: 14px; animation-duration: 1.6s; animation-direction: reverse; opacity: .7; }
    .loader-core { position: absolute; inset: 26px; border-radius: 14px; display: grid; place-items: center; color: #fff; font-size: 1.5rem;
        background: linear-gradient(135deg, #22d3ee, #a855f7); box-shadow: 0 0 30px rgba(168,85,247,.6); animation: pulse 1.4s ease-in-out infinite; }
    .loader-title { color: #fff; font-weight: 700; letter-spacing: .02em; text-align: center; }
    .loader-title small { display: block; font-weight: 400; opacity: .6; font-size: .78rem; margin-top: 2px; }
    .loader-bar { width: 180px; height: 4px; border-radius: 4px; background: rgba(255,255,255,.15); overflow: hidden; }
    .loader-bar span { display: block; height: 100%; width: 40%; border-radius: 4px; background: linear-gradient(90deg, #22d3ee, #ec4899); animation: slide 1.1s ease-in-out infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(.88); } }
    @keyframes slide { 0% { transform: translateX(-110%); } 100% { transform: translateX(260%); } }

    /* top progress bar during navigation */
    #topProgress { position: fixed; top: 0; left: 0; height: 3px; width: 0; z-index: 3001; opacity: 0;
        background: linear-gradient(90deg, #22d3ee, #6366f1, #ec4899); box-shadow: 0 0 10px rgba(99,102,241,.7); transition: width .3s ease, opacity .3s; }
    #topProgress.run { opacity: 1; width: 82%; transition: width 6s cubic-bezier(.1,.6,.2,1), opacity .2s; }
    #topProgress.done { opacity: 0; width: 100%; transition: width .2s, opacity .4s .2s; }

    /* content entrance */
    .content > * { animation: rise .45s cubic-bezier(.2,.8,.2,1) both; }
    .content > *:nth-child(2) { animation-delay: .04s; } .content > *:nth-child(3) { animation-delay: .08s; }
    .content > *:nth-child(4) { animation-delay: .12s; } .content > *:nth-child(n+5) { animation-delay: .16s; }
    @keyframes rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }

    /* ================= sidebar ================= */
    .sidebar { position: fixed; inset: 0 auto 0 0; width: var(--sidebar-w); z-index: 1045; display: flex; flex-direction: column;
        background: linear-gradient(180deg, #1e1b4b 0%, #312e81 48%, #4c1d95 100%); color: #fff; transition: transform .28s cubic-bezier(.2,.8,.2,1); box-shadow: 4px 0 28px rgba(30,27,75,.2); }
    .sidebar::before { content: ''; position: absolute; inset: 0; pointer-events: none; background: radial-gradient(circle at 0% 0%, rgba(34,211,238,.18), transparent 40%), radial-gradient(circle at 100% 100%, rgba(236,72,153,.18), transparent 45%); }
    .sidebar > * { position: relative; }
    .sidebar .brand { display: flex; align-items: center; gap: .75rem; padding: 1.05rem 1.1rem; min-height: var(--topbar-h); border-bottom: 1px solid rgba(255,255,255,.1); }
    .brand-logo { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; font-size: 1.3rem; flex: 0 0 42px;
        background: linear-gradient(135deg, #22d3ee, #a855f7); box-shadow: 0 6px 16px rgba(168,85,247,.45); }
    .brand-text { min-width: 0; }
    .brand-name { font-weight: 700; font-size: .86rem; line-height: 1.2; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .brand-sub { font-size: .72rem; opacity: .65; }
    .sidebar-nav { flex: 1 1 auto; overflow-y: auto; padding: .3rem .65rem 1rem; scrollbar-color: rgba(255,255,255,.25) transparent; }
    .nav-section { font-size: .65rem; letter-spacing: .1em; text-transform: uppercase; color: rgba(255,255,255,.45); padding: 1rem .65rem .35rem; font-weight: 700; }
    .nav-item { display: flex; align-items: center; gap: .7rem; padding: .5rem .6rem; margin: 2px 0; border-radius: 11px; color: rgba(255,255,255,.82);
        font-weight: 500; font-size: .86rem; transition: background .15s, color .15s, transform .15s; }
    .nav-item:hover { background: rgba(255,255,255,.08); color: #fff; transform: translateX(3px); }
    .nav-item.active { background: rgba(255,255,255,.16); color: #fff; box-shadow: inset 3px 0 0 #fbbf24, 0 4px 14px rgba(0,0,0,.12); }
    .nav-icon { width: 32px; height: 32px; flex: 0 0 32px; border-radius: 9px; display: grid; place-items: center; font-size: .95rem; color: #fff; box-shadow: 0 3px 8px rgba(0,0,0,.18); }
    .nav-label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .c-indigo { background: linear-gradient(135deg, #818cf8, #4f46e5); } .c-violet { background: linear-gradient(135deg, #c4b5fd, #7c3aed); }
    .c-cyan { background: linear-gradient(135deg, #67e8f9, #0891b2); } .c-blue { background: linear-gradient(135deg, #93c5fd, #2563eb); }
    .c-green { background: linear-gradient(135deg, #6ee7b7, #059669); } .c-orange { background: linear-gradient(135deg, #fdba74, #ea580c); }
    .c-pink { background: linear-gradient(135deg, #f9a8d4, #db2777); } .c-red { background: linear-gradient(135deg, #fca5a5, #dc2626); }
    .sidebar-foot { display: flex; align-items: center; gap: .65rem; padding: .85rem 1.1rem; border-top: 1px solid rgba(255,255,255,.1); font-size: .8rem; background: rgba(0,0,0,.14); }
    .sidebar-foot .who { min-width: 0; }
    .sidebar-backdrop { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.55); z-index: 1040; backdrop-filter: blur(3px); animation: fade .2s; }
    @keyframes fade { from { opacity: 0; } }

    /* ================= main / topbar ================= */
    .main { margin-left: var(--sidebar-w); min-height: 100vh; display: flex; flex-direction: column; position: relative; }
    .main::before { content: ''; position: absolute; left: 0; right: 0; top: 0; height: 230px; z-index: 0; pointer-events: none;
        background: linear-gradient(135deg, rgba(99,102,241,.10), rgba(236,72,153,.07) 55%, rgba(6,182,212,.07)); }
    .main > * { position: relative; z-index: 1; }
    .topbar { position: sticky; top: 0; z-index: 1030; display: flex; align-items: center; gap: .75rem; min-height: var(--topbar-h); padding: .6rem 1.5rem;
        background: rgba(255,255,255,.82); backdrop-filter: saturate(170%) blur(12px); -webkit-backdrop-filter: saturate(170%) blur(12px); border-bottom: 1px solid var(--line); }
    .page-icon { width: 40px; height: 40px; flex: 0 0 40px; border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 1.1rem; box-shadow: 0 6px 14px rgba(79,70,229,.25); }
    .page-title { font-size: 1.12rem; font-weight: 700; margin: 0; color: var(--ink); line-height: 1.25; }
    .page-crumb { font-size: .72rem; color: var(--muted); }
    .btn-icon { width: 40px; height: 40px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; font-size: 1.25rem; display: grid; place-items: center; padding: 0; }
    .btn-back { display: inline-flex; align-items: center; gap: .35rem; border-radius: 12px; border: 1px solid #c7d2fe; background: #eef2ff; color: var(--indigo); font-weight: 600; padding: .45rem .85rem; white-space: nowrap; }
    .btn-back:hover { background: var(--indigo); color: #fff; border-color: var(--indigo); }
    .btn-user { display: flex; align-items: center; gap: .5rem; border-radius: 999px; padding: .25rem .8rem .25rem .25rem; background: #fff; border: 1px solid #e2e8f0; font-weight: 500; }
    .avatar { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-weight: 700; flex: 0 0 34px; background: linear-gradient(135deg, var(--brand1), var(--pink)); }
    .content { padding: 1.5rem; flex: 1 1 auto; }
    .app-footer { text-align: center; font-size: .75rem; color: var(--muted); padding: 1rem; }
    .dropdown-menu { border: 0; border-radius: 14px; box-shadow: var(--shadow-lg); padding: .4rem; }
    .dropdown-item { border-radius: 9px; padding: .5rem .75rem; }

    /* ================= components ================= */
    .card { border: 1px solid rgba(231,235,243,.7); border-radius: var(--radius); box-shadow: var(--shadow); background: #fff; }
    .card-header { background: #fff; font-weight: 600; border-bottom: 1px solid #eef1f7; border-radius: var(--radius) var(--radius) 0 0 !important; padding: .85rem 1.15rem; position: relative; }
    .card > .card-header:first-child::before { content: ''; position: absolute; left: 0; top: 14px; bottom: 14px; width: 4px; border-radius: 0 4px 4px 0; background: linear-gradient(180deg, var(--brand1), var(--brand3)); }
    .card-footer { border-radius: 0 0 var(--radius) var(--radius) !important; border-top: 1px solid #eef1f7; }
    .btn { border-radius: 10px; font-weight: 500; transition: transform .12s, box-shadow .15s, background .15s; }
    .btn:active { transform: scale(.97); }
    .btn-primary { background: linear-gradient(135deg, #6366f1, #4f46e5); border: 0; box-shadow: 0 4px 12px rgba(79,70,229,.25); }
    .btn-primary:hover, .btn-primary:focus { background: linear-gradient(135deg, #4f46e5, #4338ca); box-shadow: 0 6px 16px rgba(79,70,229,.35); }
    .btn-success { background: linear-gradient(135deg, #10b981, #059669); border: 0; box-shadow: 0 4px 12px rgba(5,150,105,.25); }
    .btn-danger { background: linear-gradient(135deg, #f43f5e, #dc2626); border: 0; }
    .btn-warning { background: linear-gradient(135deg, #fbbf24, #f59e0b); border: 0; }
    .form-control, .form-select { border-radius: 10px; border-color: #dbe1ec; }
    .form-control:focus, .form-select:focus { border-color: #818cf8; box-shadow: 0 0 0 .22rem rgba(99,102,241,.15); }
    .form-label { font-weight: 600; font-size: .8rem; margin-bottom: .3rem; color: #374151; }
    .form-check-input:checked { background-color: var(--indigo); border-color: var(--indigo); }
    .badge { font-weight: 600; }
    .table { --bs-table-hover-bg: #f5f7ff; margin-bottom: 0; }
    .table > :not(caption) > * > * { border-bottom-color: #eef1f6; }
    .table thead th { background: #f4f6fb; color: #475569; font-weight: 600; white-space: nowrap; font-size: .74rem; text-transform: uppercase; letter-spacing: .03em; border-bottom: 0; }
    .table-sm td, .table-sm th { padding: .45rem .55rem; }
    .table-register td, .table-register th { white-space: nowrap; font-size: .8rem; }
    .num { text-align: right; font-variant-numeric: tabular-nums; }
    .group-row td { background: linear-gradient(90deg, #e0e7ff, #f5f3ff) !important; font-weight: 700; color: #3730a3; }
    .total-row td { background: #f8fafc !important; font-weight: 700; }
    .ot-input { width: 80px; text-align: right; }
    .nav-pills { gap: .35rem; }
    .nav-pills .nav-link { border-radius: 999px; color: var(--ink); background: #fff; border: 1px solid var(--line); }
    .nav-pills .nav-link.active { background: linear-gradient(135deg, #6366f1, #7c3aed); border-color: transparent; }
    .alert { border: 0; border-radius: 14px; border-left: 4px solid; box-shadow: var(--shadow-sm); }
    .alert-success { border-left-color: #059669; } .alert-danger { border-left-color: #dc2626; } .alert-warning { border-left-color: #d97706; } .alert-info { border-left-color: #0891b2; }
    .pagination { margin: 0; flex-wrap: wrap; gap: .25rem; } .page-link { border-radius: 9px !important; border: 1px solid var(--line); }
    .ts-control { border-radius: 10px !important; }
    .list-group-item { border-color: #eef1f6; }
    .step-pill { white-space: normal; text-align: left; line-height: 1.3; }
    .table-responsive { -webkit-overflow-scrolling: touch; }

    /* workflow steps */
    .wf-step { border-left: 4px solid #cbd5e1 !important; transition: background .2s; }
    .wf-step.s-done { border-left-color: #10b981 !important; background: linear-gradient(90deg, #ecfdf5, #fff 45%); }
    .wf-step.s-attention { border-left-color: #f59e0b !important; background: linear-gradient(90deg, #fffbeb, #fff 45%); }
    .wf-step.s-todo { border-left-color: #a5b4fc !important; }

    /* stat tiles */
    .stat-card { color: #fff; overflow: hidden; position: relative; border: 0; transition: transform .18s, box-shadow .18s; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
    .stat-card::after { content: ''; position: absolute; right: -30px; top: -30px; width: 120px; height: 120px; border-radius: 50%; background: rgba(255,255,255,.14); }
    .stat-card::before { content: ''; position: absolute; left: -20px; bottom: -40px; width: 90px; height: 90px; border-radius: 50%; background: rgba(255,255,255,.08); }
    .stat-card .icon { width: 46px; height: 46px; flex: 0 0 46px; border-radius: 12px; display: grid; place-items: center; font-size: 1.3rem; background: rgba(255,255,255,.22); }
    .grad-1 { background: linear-gradient(135deg, #6366f1, #8b5cf6); } .grad-2 { background: linear-gradient(135deg, #f59e0b, #f97316); }
    .grad-3 { background: linear-gradient(135deg, #06b6d4, #3b82f6); } .grad-4 { background: linear-gradient(135deg, #f43f5e, #ec4899); }
    .grad-5 { background: linear-gradient(135deg, #10b981, #14b8a6); }

    /* confirm dialog + busy overlay */
    .confirm-modal { border: 0; border-radius: 22px; }
    .confirm-icon { width: 72px; height: 72px; border-radius: 50%; margin: 0 auto; display: grid; place-items: center; font-size: 2rem; color: #fff; animation: pop .35s cubic-bezier(.2,.9,.3,1.3); }
    @keyframes pop { from { transform: scale(.6); opacity: 0; } }
    .confirm-icon.v-primary { background: linear-gradient(135deg, #6366f1, #7c3aed); box-shadow: 0 10px 24px rgba(99,102,241,.35); }
    .confirm-icon.v-success { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 10px 24px rgba(16,185,129,.35); }
    .confirm-icon.v-danger { background: linear-gradient(135deg, #f43f5e, #dc2626); box-shadow: 0 10px 24px rgba(244,63,94,.35); }
    .confirm-icon.v-warning { background: linear-gradient(135deg, #fbbf24, #f59e0b); box-shadow: 0 10px 24px rgba(245,158,11,.35); }
    .busy-overlay { position: fixed; inset: 0; z-index: 2900; display: none; flex-direction: column; align-items: center; justify-content: center; background: rgba(30,27,75,.6); backdrop-filter: blur(4px); }
    .busy-overlay.show { display: flex; animation: fade .2s; }
    .busy-overlay .loader-mark { width: 74px; height: 74px; }
    .busy-overlay .loader-core { inset: 20px; font-size: 1.1rem; }

    /* scroll to top */
    #toTop { position: fixed; right: 18px; bottom: 22px; z-index: 1020; width: 44px; height: 44px; border-radius: 14px; border: 0; color: #fff; font-size: 1.2rem;
        background: linear-gradient(135deg, #6366f1, #8b5cf6); box-shadow: var(--shadow-lg); opacity: 0; transform: translateY(12px); pointer-events: none; transition: .25s; }
    #toTop.show { opacity: 1; transform: none; pointer-events: auto; }

    /* ================= mobile bottom navigation ================= */
    .mobile-nav { display: none; }

    /* ================= responsive ================= */
    @media (max-width: 991.98px) {
        .sidebar { transform: translateX(-102%); width: min(86vw, 300px); }
        body.nav-open .sidebar { transform: none; }
        body.nav-open .sidebar-backdrop { display: block; }
        body.nav-open { overflow: hidden; }
        .main { margin-left: 0; padding-bottom: calc(var(--mnav-h) + env(safe-area-inset-bottom)); }
        .topbar { padding: .5rem .75rem; gap: .5rem; min-height: 58px; }
        .page-icon { display: none !important; }
        .content { padding: 1rem .8rem; }
        .app-footer { display: none; }
        #toTop { bottom: calc(var(--mnav-h) + 16px + env(safe-area-inset-bottom)); }

        .mobile-nav { display: flex; position: fixed; left: 0; right: 0; bottom: 0; z-index: 1035; height: calc(var(--mnav-h) + env(safe-area-inset-bottom));
            padding-bottom: env(safe-area-inset-bottom); background: rgba(255,255,255,.94); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border-top: 1px solid var(--line); box-shadow: 0 -6px 20px rgba(16,24,40,.06); }
        .mobile-nav a, .mobile-nav button { flex: 1 1 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px;
            border: 0; background: none; color: #64748b; font-size: .66rem; font-weight: 600; position: relative; padding: 0; }
        .mobile-nav i { font-size: 1.25rem; line-height: 1; transition: transform .15s; }
        .mobile-nav .active { color: var(--indigo); }
        .mobile-nav .active i { transform: translateY(-2px); }
        .mobile-nav .active::before { content: ''; position: absolute; top: 0; width: 28px; height: 3px; border-radius: 0 0 4px 4px; background: linear-gradient(90deg, #6366f1, #ec4899); }
        .mobile-nav .mn-main i { width: 46px; height: 46px; margin-top: -22px; border-radius: 16px; display: grid; place-items: center; color: #fff;
            background: linear-gradient(135deg, #6366f1, #ec4899); box-shadow: 0 8px 18px rgba(99,102,241,.4); font-size: 1.3rem; }
    }
    @media (max-width: 767.98px) {
        body { font-size: .88rem; }
        /* list tables become cards */
        .table-stack thead { display: none; }
        .table-stack, .table-stack tbody, .table-stack tr, .table-stack td { display: block; width: 100%; }
        .table-stack tr { margin: .65rem .65rem; width: auto; border: 1px solid var(--line); border-radius: 14px; padding: .4rem .2rem; background: #fff; box-shadow: var(--shadow-sm); }
        .table-stack td { display: flex; justify-content: space-between; align-items: center; gap: 1rem; border: 0 !important; padding: .32rem .75rem !important; text-align: right; white-space: normal !important; }
        .table-stack td::before { content: attr(data-label); font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: var(--muted); text-align: left; flex: 0 0 auto; }
        .table-stack td[data-label=""]::before, .table-stack td:not([data-label])::before { content: none; }
        .table-stack td.text-end, .table-stack td:last-child { justify-content: flex-end; }
        .table-stack td:first-child { font-weight: 600; }
        .table-stack tr:hover td { background: transparent !important; }
        /* wide registers: keep scrolling, pin the first column */
        .table-register th:first-child, .table-register td:first-child { position: sticky; left: 0; z-index: 2; background: #fff; box-shadow: 1px 0 0 var(--line); }
        .table-register thead th:first-child { background: #f4f6fb; }
    }
    @media (max-width: 575.98px) {
        .page-title { font-size: .98rem; }
        .page-crumb { display: none; }
        .btn-back { padding: .4rem .6rem; }
        .auto-filter { width: 100%; }
        .auto-filter > select, .auto-filter > input, .auto-filter > .ts-wrapper, .auto-filter > a { max-width: 100% !important; width: 100% !important; min-width: 0 !important; flex: 1 1 100%; }
        .card-header.d-flex > .btn, .card-header.d-flex > a.btn, .card-footer > .btn, .card-footer > a.btn { flex: 1 1 auto; }
        .card-header { padding: .75rem .9rem; }
        .card-body { padding: .9rem; }
        .form-control, .form-select, .ts-control input { font-size: 16px !important; } /* no zoom on iOS */
        .form-control-sm, .form-select-sm { min-height: 38px; }
        .btn { min-height: 40px; } .btn-sm { min-height: 34px; }
        .ot-input { width: 66px; }
        .nav-pills { flex-wrap: nowrap; overflow-x: auto; padding-bottom: .25rem; }
        .nav-pills .nav-link { white-space: nowrap; }
        .stat-card .fs-3, .stat-card .fs-4 { font-size: 1.35rem !important; }
        .stat-card .icon { width: 38px; height: 38px; flex-basis: 38px; font-size: 1.05rem; }
        .modal-dialog { margin: 0; position: fixed; left: 0; right: 0; bottom: 0; max-width: 100%; }
        .modal.fade .modal-dialog { transform: translateY(100%); }
        .modal.show .modal-dialog { transform: none; }
        .confirm-modal { border-radius: 22px 22px 0 0; padding-bottom: env(safe-area-inset-bottom); }
        .confirm-modal .btn { flex: 1 1 0; }
    }
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
    }
    @media print {
        #pageLoader, #topProgress, #toTop, .mobile-nav, .sidebar, .topbar, .no-print, .app-footer, .sidebar-backdrop { display: none !important; }
        .main { margin: 0; padding: 0; } .main::before { display: none; } .content { padding: 0; } .card { box-shadow: none; border: 0; } body { background: #fff; }
        .content > * { animation: none; }
    }
</style>
