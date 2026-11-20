<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ setting('company_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    @include('layouts._theme')
    @include('layouts._redesign')
    @include('layouts._themes')
    @stack('styles')
</head>
<body>
@php
$u = auth()->user();
$nav = [
    ['Overview', [
        ['dashboard', 'Dashboard', 'bi-speedometer2', 'c-indigo', ['dashboard']],
        ['workflow.index', 'Monthly Workflow', 'bi-list-check', 'c-violet', ['workflow.*']],
        ['imports.index', 'Import Data (Excel)', 'bi-cloud-arrow-up', 'c-cyan', ['imports.*']],
    ]],
    ['Users', [
        ['employees.index', 'Employee Registration', 'bi-people', 'c-blue', ['employees.*']],
    ]],
    ['Leave Management', [
        ['leave.allocations', 'Yearly Leave Allocation', 'bi-calendar2-plus', 'c-green', ['leave.allocations']],
        ['leave-requests.index', 'Leave Requests', 'bi-calendar2-check', 'c-green', ['leave-requests.*']],
        ['leave.balances', 'Leave Balances', 'bi-pie-chart', 'c-green', ['leave.balances']],
        ['leave.register', 'Leave Register', 'bi-table', 'c-green', ['leave.register']],
        ['leave.el-summary', 'EL Summary', 'bi-piggy-bank', 'c-green', ['leave.el-summary']],
        ['leave.adjustments', 'Monthly Leave Entry', 'bi-pencil-square', 'c-green', ['leave.adjustments']],
    ]],
    ['OT Management', [
        ['ot.index', 'OT Entry', 'bi-clock-history', 'c-orange', ['ot.index']],
        ['ot.report', 'OT Report', 'bi-bar-chart', 'c-orange', ['ot.report']],
        ['ot.history', 'OT History', 'bi-calendar3', 'c-orange', ['ot.history']],
    ]],
    ['Payslip Management', [
        ['payroll.index', 'Payroll & Payslips', 'bi-cash-stack', 'c-pink', ['payroll.*', 'payslips.*']],
        ['settlements.index', 'EL & Gratuity Settlement', 'bi-briefcase', 'c-pink', ['settlements.*']],
    ]],
];
if ($u->isSuperAdmin()) {
    $nav[] = ['Administration', [
        ['users.index', 'Admin Accounts', 'bi-shield-lock', 'c-red', ['users.*']],
        ['settings.edit', 'Settings', 'bi-gear', 'c-red', ['settings.*']],
        ['activity-logs.index', 'Activity Log', 'bi-journal-text', 'c-red', ['activity-logs.*']],
        ['backups.index', 'Database Backups', 'bi-database-check', 'c-red', ['backups.*']],
    ]];
}
// icon + colour of the current section, shown next to the page title
$current = ['bi-person-gear', 'c-indigo'];
foreach ($nav as [, $items]) {
    foreach ($items as $it) {
        if (request()->routeIs(...$it[4])) { $current = [$it[2], $it[3]]; break 2; }
    }
}
@endphp

{{-- animated page loader (hidden as soon as the page has loaded) --}}
<div id="pageLoader" role="status" aria-label="Loading">
    <div class="loader-mark">
        <div class="loader-ring"></div><div class="loader-ring r2"></div>
        <div class="loader-core"><i class="bi bi-receipt-cutoff"></i></div>
    </div>
    <div class="loader-title">{{ setting('company_name') }}<small>Payslip &amp; HR Suite</small></div>
    <div class="loader-bar"><span></span></div>
</div>
<div id="topProgress"></div>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="brand">
        <div class="brand-logo"><i class="bi bi-receipt-cutoff"></i></div>
        <div class="brand-text">
            <div class="brand-name" title="{{ setting('company_name') }}">{{ setting('company_name') }}</div>
            <div class="brand-sub">Payslip &amp; HR Suite</div>
        </div>
        <button class="btn-close btn-close-white d-lg-none ms-auto" id="sidebarClose" aria-label="Close menu"></button>
    </div>
    <nav class="sidebar-nav">
        @foreach ($nav as [$section, $items])
            <div class="nav-section">{{ $section }}</div>
            @foreach ($items as [$route, $label, $icon, $color, $active])
                <a class="nav-item {{ request()->routeIs(...$active) ? 'active' : '' }}" href="{{ route($route) }}">
                    <span class="nav-icon {{ $color }}"><i class="bi {{ $icon }}"></i></span>
                    <span class="nav-label">{{ $label }}</span>
                </a>
            @endforeach
        @endforeach
    </nav>
    <div class="sidebar-foot">
        <span class="avatar">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
        <div class="who flex-grow-1">
            <div class="fw-semibold text-truncate">{{ $u->name }}</div>
            <span class="badge {{ $u->isSuperAdmin() ? 'bg-danger' : 'bg-info' }}">{{ $u->roleLabel() }}</span>
        </div>
        <a href="{{ route('profile.edit') }}" class="text-white-50 fs-5" title="My profile"><i class="bi bi-gear"></i></a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <button class="btn btn-icon d-lg-none" id="sidebarOpen" aria-label="Open menu"><i class="bi bi-list"></i></button>
        @unless (request()->routeIs('dashboard'))
            <a href="@yield('back', route('dashboard'))" class="btn btn-back" id="backBtn" title="Back"><i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline"> Back</span></a>
        @endunless
        <span class="page-icon {{ $current[1] }} d-none d-md-grid"><i class="bi {{ $current[0] }}"></i></span>
        <div class="flex-grow-1 min-w-0">
            <h1 class="page-title text-truncate">@yield('title', 'Dashboard')</h1>
            <div class="page-crumb d-none d-md-block">{{ setting('company_name') }} · {{ now()->format('l, d M Y') }}</div>
        </div>
        {{-- live digital clock, India Standard Time (synced to the server clock) --}}
        <div class="live-clock" id="liveClock" data-server-ms="{{ now()->getTimestampMs() }}" title="India Standard Time (IST, UTC+05:30)">
            <span class="lc-icon"><i class="bi bi-clock"></i></span>
            <span class="lc-time"><span data-h>--</span><b>:</b><span data-m>--</span><b>:</b><span data-s>--</span><small data-ap></small></span>
            <span class="lc-meta d-none d-xl-flex"><span data-d></span><span class="lc-tz">IST</span></span>
        </div>
        {{-- the year every page shows --}}
        <form method="POST" action="{{ route('year.switch') }}" class="year-switch {{ active_year() != now()->year ? 'is-past' : '' }}" title="Year shown on every page">@csrf
            <i class="bi bi-calendar3"></i>
            <span class="d-none d-sm-inline">Year</span>
            <select name="year" aria-label="Year shown on every page" onchange="this.form.submit()">
                @foreach (year_options() as $y)<option value="{{ $y }}" @selected($y == active_year())>{{ $y }}</option>@endforeach
            </select>
        </form>
        <div class="dropdown theme-pick">
            <button type="button" class="btn-theme" data-bs-toggle="dropdown" aria-label="Colour theme" title="Colour theme"><i class="bi bi-palette2"></i></button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li class="px-3 pt-1 pb-2 small text-muted fw-semibold">Colour theme</li>
                @foreach ([
                    'purple-orange' => ['Purple & Orange', 'linear-gradient(135deg,#4c1d95 50%,#f97316 50%)'],
                    'purple' => ['Royal Purple', 'linear-gradient(135deg,#3b0764,#a855f7)'],
                    'orange' => ['Sunset Orange', 'linear-gradient(135deg,#c2410c,#f59e0b)'],
                    'ocean' => ['Ocean Blue', 'linear-gradient(135deg,#0c4a6e,#06b6d4)'],
                    'emerald' => ['Emerald Green', 'linear-gradient(135deg,#064e3b,#34d399)'],
                ] as $key => [$name, $swatch])
                    <li><button type="button" class="dropdown-item d-flex align-items-center gap-2" data-theme-set="{{ $key }}">
                        <span class="swatch" style="background:{{ $swatch }}"></span><span class="flex-grow-1">{{ $name }}</span><i class="bi bi-check2"></i>
                    </button></li>
                @endforeach
            </ul>
        </div>
        @if ($u->isSuperAdmin())
            <button type="button" class="btn btn-success btn-sm d-flex align-items-center gap-1 px-2 px-md-3" data-backup-now title="Back up the database now (ZIP)">
                <i class="bi bi-database-down fs-6"></i><span class="d-none d-md-inline">Backup</span>
            </button>
        @endif
        <div class="dropdown">
            <button class="btn btn-user dropdown-toggle" data-bs-toggle="dropdown">
                <span class="avatar">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                <span class="d-none d-md-inline">{{ $u->name }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li class="px-3 py-2 small text-muted">{{ $u->email }}<br><span class="badge {{ $u->isSuperAdmin() ? 'bg-danger' : 'bg-info' }}">{{ $u->roleLabel() }}</span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person-gear"></i> My Profile</a></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}" data-confirm="Do you want to sign out?" data-confirm-title="Sign out" data-confirm-variant="danger">@csrf
                        <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i> Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </header>
    <main class="content">
        @include('partials.flash')
        @yield('content')
    </main>
    <footer class="app-footer no-print">© {{ now()->year }} {{ setting('company_name') }} · Payslip &amp; HR Suite</footer>
</div>

{{-- bottom navigation on phones / tablets --}}
<nav class="mobile-nav no-print" aria-label="Quick navigation">
    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="bi bi-house-door"></i>Home</a>
    <a href="{{ route('employees.index') }}" class="{{ request()->routeIs('employees.*') ? 'active' : '' }}"><i class="bi bi-people"></i>Employees</a>
    <a href="{{ route('workflow.index') }}" class="mn-main {{ request()->routeIs('workflow.*') ? 'active' : '' }}"><i class="bi bi-list-check"></i>Workflow</a>
    <a href="{{ route('payroll.index') }}" class="{{ request()->routeIs('payroll.*', 'payslips.*') ? 'active' : '' }}"><i class="bi bi-cash-stack"></i>Payroll</a>
    <button type="button" id="mobileMenu"><i class="bi bi-grid"></i>Menu</button>
</nav>
<button type="button" id="toTop" class="no-print" aria-label="Back to top"><i class="bi bi-arrow-up"></i></button>

{{-- Shared confirmation dialog (single step, or two steps for critical actions) --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content confirm-modal">
            <div class="modal-body text-center p-4">
                <div class="confirm-icon mb-3"><i class="bi bi-question-lg"></i></div>
                <div class="confirm-step badge rounded-pill mb-2"></div>
                <h5 class="confirm-title mb-2"></h5>
                <p class="confirm-message text-muted mb-3"></p>
                <div class="confirm-check form-check text-start d-inline-block mb-3">
                    <input class="form-check-input" type="checkbox" id="confirmCheck">
                    <label class="form-check-label small" for="confirmCheck">I have checked the details and want to proceed.</label>
                </div>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn px-4 confirm-ok">Confirm</button>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="busy-overlay" id="busyOverlay" role="status">
    <div class="loader-mark"><div class="loader-ring"></div><div class="loader-ring r2"></div><div class="loader-core"><i class="bi bi-hourglass-split"></i></div></div>
    <div class="mt-3 text-white fw-semibold" id="busyText">Please wait…</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
@include('layouts._scripts')
@include('layouts._toast')
@if ($u->isSuperAdmin())
    @include('backups._reminder')
@endif
@if (session('backup_download'))
    <script>window.addEventListener('load', () => setTimeout(() => { window.location.href = @json(session('backup_download')); }, 700));</script>
@endif
@stack('scripts')
</body>
</html>
