@extends('layouts.app')
@section('title', 'Help & Guide')
@php
    $deadline = (int) (setting('payroll_deadline_day', 10) ?: 10);
    $types = \App\Models\LeaveType::ordered()->get();
    $paid = $types->where('is_paid', true);
    $isSuper = auth()->user()->isSuperAdmin();
    $toc = [
        ['start', 'Getting started', 'bi-rocket-takeoff'],
        ['monthly', 'Monthly payslip workflow', 'bi-list-check'],
        ['yearly', 'Yearly cycle', 'bi-calendar3'],
        ['employees', 'Employees', 'bi-people'],
        ['celebrations', 'Photos & celebrations', 'bi-balloon-heart'],
        ['leave', 'Leave management', 'bi-calendar2-check'],
        ['ot', 'OT & shift hours', 'bi-clock-history'],
        ['payroll', 'Payroll & payslips', 'bi-cash-stack'],
        ['settlement', 'EL & gratuity settlement', 'bi-briefcase'],
        ['imports', 'Importing from Excel', 'bi-cloud-arrow-up'],
        ['admin', 'Backups & administration', 'bi-shield-lock'],
        ['faq', 'Questions & fixes', 'bi-question-circle'],
    ];
@endphp
@push('styles')
<style>
    .help-toc { position: sticky; top: calc(var(--topbar-h) + 16px); }
    .help-toc a { display: flex; align-items: center; gap: .55rem; padding: .45rem .7rem; border-radius: 9px; color: #374151; font-weight: 500; font-size: .84rem; }
    .help-toc a:hover { background: var(--accent-softer); color: var(--accent); }
    .help-toc a.active { background: var(--accent-soft); color: var(--accent-ink); font-weight: 600; }
    .help-toc a i { color: var(--accent); }
    .help-sec { scroll-margin-top: calc(var(--topbar-h) + 16px); }
    .help-sec .card-header { font-size: 1rem; display: flex; align-items: center; gap: .55rem; }
    .help-sec .card-header .h-ico { width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; background: var(--accent-soft); color: var(--accent); }
    .help-sec h6 { font-weight: 700; margin-top: 1.1rem; color: var(--accent-ink); }
    .help-sec p, .help-sec li { line-height: 1.6; }
    .flow { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; counter-reset: step; }
    .flow .st { position: relative; border: 1px solid var(--line); border-radius: 12px; padding: .8rem .8rem .75rem; background: #fff; }
    .flow .st::before { counter-increment: step; content: counter(step); position: absolute; top: -10px; left: 12px; width: 24px; height: 24px; border-radius: 50%;
        display: grid; place-items: center; font-size: .72rem; font-weight: 700; color: #fff; background: var(--accent); box-shadow: 0 3px 8px var(--accent-shadow); }
    .flow .st i { font-size: 1.3rem; color: var(--accent-2); }
    .flow .st b { display: block; font-size: .84rem; margin-top: .25rem; }
    .flow .st span { font-size: .75rem; color: var(--muted); }
    .timeline { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 0; border-radius: 12px; overflow: hidden; border: 1px solid var(--line); }
    .timeline > div { padding: .75rem .85rem; background: #fff; border-right: 1px solid var(--line); }
    .timeline > div:last-child { border-right: 0; }
    .timeline .when { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--accent); }
    .formula { background: var(--accent-softer); border: 1px dashed var(--accent-line); border-radius: 10px; padding: .7rem .9rem; font-size: .82rem; }
    .formula td { padding: .15rem .6rem .15rem 0; vertical-align: top; } .formula td:first-child { font-weight: 600; white-space: nowrap; }
    .tip { border-left: 4px solid var(--accent-2); background: var(--accent-2-soft); border-radius: 10px; padding: .6rem .85rem; font-size: .83rem; }
    .warn { border-left: 4px solid #dc2626; background: #fef2f2; border-radius: 10px; padding: .6rem .85rem; font-size: .83rem; }
    .kbd-chip { display: inline-flex; align-items: center; gap: .25rem; padding: .05rem .45rem; border-radius: 6px; background: #f3f4f6; border: 1px solid var(--line); font-size: .78rem; font-weight: 600; white-space: nowrap; }
    .help-hit { background: #fef08a; border-radius: 3px; }
    @media print {
        .help-toc-col, .help-tools { display: none !important; }
        .help-sec { break-inside: avoid-page; box-shadow: none !important; }
        .col-lg-9 { width: 100% !important; }
    }
</style>
@endpush
@section('content')
<div class="row g-3">
    {{-- contents --}}
    <div class="col-lg-3 help-toc-col">
        <div class="card help-toc">
            <div class="card-body p-2">
                <input type="search" id="helpSearch" class="form-control form-control-sm mb-2" placeholder="Search the guide…" aria-label="Search the guide">
                <nav id="helpToc">
                    @foreach ($toc as [$id, $label, $icon])<a href="#{{ $id }}" data-no-toast><i class="bi {{ $icon }}"></i>{{ $label }}</a>@endforeach
                </nav>
                <hr class="my-2">
                <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="window.print()"><i class="bi bi-printer"></i> Print / save as PDF</button>
            </div>
        </div>
    </div>

    <div class="col-lg-9" id="helpBody">
        {{-- intro --}}
        <div class="dash-hero mb-3">
            <div class="d-flex flex-wrap gap-3 align-items-center">
                <div class="flex-grow-1">
                    <div class="hero-sub small">{{ setting('company_name') }} · Payslip &amp; HR Suite</div>
                    <div class="fs-3 fw-bold">How the system works</div>
                    <div class="hero-sub">Everything an admin needs: the monthly payslip routine, the yearly leave cycle and what every page does.</div>
                </div>
                <a href="{{ route('workflow.index') }}" class="btn btn-light fw-semibold"><i class="bi bi-list-check"></i> Open this month's workflow</a>
            </div>
        </div>

        {{-- 1 getting started --}}
        <div class="card help-sec mb-3" id="start">
            <div class="card-header"><span class="h-ico"><i class="bi bi-rocket-takeoff"></i></span> Getting started</div>
            <div class="card-body">
                <h6 class="mt-0">Who can do what</h6>
                <table class="table table-sm mb-2">
                    <thead><tr><th>Role</th><th>Can use</th></tr></thead>
                    <tr><td><span class="badge bg-danger">Super Admin</span></td><td>Everything, plus admin accounts, Settings, Activity Log, Database Backups, re-opening and deleting a finalized payroll.</td></tr>
                    <tr><td><span class="badge bg-info">Admin</span></td><td>Employees, leave, OT, imports, payroll &amp; payslips, settlements.</td></tr>
                </table>
                <p class="small text-muted mb-0">Employees do not log in. Admins record everything on their behalf and send payslips by email.</p>

                <h6>The top bar</h6>
                <ul class="mb-2">
                    <li><strong>Digital clock</strong>: the current India Standard Time (IST). Every record is saved with IST date and time.</li>
                    <li><strong>Year</strong>: chooses the year shown on <em>every</em> page (lists, counts, totals, dashboard). A past year is shown in orange so you notice it. Choosing a year inside a page's own filter switches the whole app too.</li>
                    <li><i class="bi bi-palette2"></i> <strong>Colour theme</strong>: 5 colour sets (Purple &amp; Orange, Royal Purple, Sunset Orange, Ocean Blue, Emerald Green). Remembered on this computer.</li>
                    @if ($isSuper)<li><i class="bi bi-database-down"></i> <strong>Backup</strong>: takes a full database backup and downloads the ZIP.</li>@endif
                </ul>

                <h6>Messages (toasts)</h6>
                <p class="mb-2">Every action shows a small message in the <strong>top-right corner</strong>: what is loading, what was saved, and any problem. The coloured bar along the bottom counts down until the message closes; hover over it to keep it open, or click <span class="kbd-chip">×</span>.</p>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="kbd-chip"><i class="bi bi-check-circle-fill text-success"></i> Done</span>
                    <span class="kbd-chip"><i class="bi bi-arrow-repeat" style="color:var(--accent)"></i> Working…</span>
                    <span class="kbd-chip"><i class="bi bi-exclamation-triangle-fill text-warning"></i> Please check</span>
                    <span class="kbd-chip"><i class="bi bi-x-octagon-fill text-danger"></i> Error</span>
                    <span class="kbd-chip"><i class="bi bi-info-circle-fill text-info"></i> Note</span>
                </div>
                <h6>Fast pages &amp; animations</h6>
                <p class="mb-2">When you rest the pointer on a link, the page is fetched in the background, so it opens almost instantly when you click. Pages slide smoothly into each other while the menu stays in place, table rows and figures animate in, and buttons ripple when pressed. The full-screen loading animation only appears on the first page after opening the app. If your computer is set to <em>reduce motion</em>, animations are switched off.</p>
                <h6>Confirmations</h6>
                <p class="mb-0">Anything that changes data asks you to confirm first. Important actions (generating or finalizing payroll, approving all OT…) ask <strong>twice</strong>: the second step needs the tick box <em>“I have checked the details”</em>. Closing the dialog cancels and nothing changes. Filters apply as soon as you change them; there is no Search button.</p>
            </div>
        </div>

        {{-- 2 monthly workflow --}}
        <div class="card help-sec mb-3" id="monthly">
            <div class="card-header"><span class="h-ico"><i class="bi bi-list-check"></i></span> Monthly payslip workflow</div>
            <div class="card-body">
                <p>Payslips for a month are prepared <strong>between the 1st and the {{ $deadline }}th of the following month</strong>. Example: November payslips are made 1–{{ $deadline }} December. Until the {{ $deadline }}th, the app treats the previous month as the <em>working month</em>; the dashboard and <a href="{{ route('workflow.index') }}">Monthly Workflow</a> page open it automatically.</p>
                <div class="timeline mb-3">
                    <div><div class="when">During the month</div>Record leave as it happens; enter new joiners and exits.</div>
                    <div><div class="when">1st – {{ $deadline - 1 }}th next month</div>Finish leave and OT, generate and check payslips.</div>
                    <div><div class="when">By the {{ $deadline }}th</div>Finalize the month and email the payslips.</div>
                    <div><div class="when">After</div>The month is locked; corrections need a Super Admin to re-open it.</div>
                </div>

                <div class="flow mb-3">
                    <div class="st"><i class="bi bi-people"></i><b>Employee master</b><span>Joiners, exits, salary changes</span></div>
                    <div class="st"><i class="bi bi-calendar2-check"></i><b>Leave</b><span>Recorded and approved</span></div>
                    <div class="st"><i class="bi bi-clock-history"></i><b>OT &amp; shifts</b><span>Entered and approved</span></div>
                    <div class="st"><i class="bi bi-gear-wide-connected"></i><b>Generate</b><span>Payslips calculated</span></div>
                    <div class="st"><i class="bi bi-lock"></i><b>Finalize</b><span>Review, then lock</span></div>
                    <div class="st"><i class="bi bi-envelope-paper"></i><b>Email</b><span>Send to each employee</span></div>
                </div>

                <h6>Step by step</h6>
                <ol>
                    <li><strong>Employee master up to date.</strong> Add new joiners, enter the <em>date of exit</em> for anyone who left, update basic salary, specials, allowances, tax and email. <a href="{{ route('employees.index') }}">Employees</a></li>
                    <li><strong>Leave recorded &amp; approved.</strong> Approve or reject every pending request; <em>pending leave is not counted</em> in payroll. Leave without dates can be typed in <a href="{{ route('leave.adjustments') }}">Monthly Leave Entry</a> or imported from the Excel register.</li>
                    <li><strong>OT &amp; shift hours.</strong> Enter 2nd / 3rd shift hours, normal OT and special OT in <a href="{{ route('ot.index') }}">OT Entry</a> (or import the OT sheet), then approve them. <em>Only approved OT is paid.</em></li>
                    <li><strong>Generate payslips.</strong> In <a href="{{ route('payroll.index') }}">Payroll &amp; Payslips</a> pick the month and working days and click <span class="kbd-chip">Generate payslips</span>. If leave, OT or employee data changes afterwards, the workflow shows <em>“data changed”</em>; click <span class="kbd-chip">Recalculate</span>.</li>
                    <li><strong>Review &amp; finalize.</strong> Open payslips to add advances, birthday welfare, other deductions or an OT override. Check the salary register (Excel export available), then <span class="kbd-chip">Finalize</span>. This <strong>locks the month</strong>: leave, OT and payslips for it can no longer change.</li>
                    <li><strong>Email payslips.</strong> Click <span class="kbd-chip">Email payslips</span> to send each employee a PDF. The page shows how many were sent, which failed and who has no email address.</li>
                </ol>
                <div class="tip"><strong>Tip:</strong> the dashboard header shows the current cycle, its deadline and a bar for the six steps (green done, yellow needs attention). Click <em>Open workflow</em> for links to each step.</div>
            </div>
        </div>

        {{-- 3 yearly --}}
        <div class="card help-sec mb-3" id="yearly">
            <div class="card-header"><span class="h-ico"><i class="bi bi-calendar3"></i></span> Yearly cycle</div>
            <div class="card-body">
                <div class="timeline mb-3">
                    <div><div class="when">1 January</div>Leave for the new year is generated automatically for every employee.</div>
                    <div><div class="when">January 1st – {{ $deadline }}th</div>Working month is still December; finish December payslips.</div>
                    <div><div class="when">During the year</div>New joiners get pro-rated leave as soon as they are registered.</div>
                    <div><div class="when">December</div>Close the leave year: unused leave is credited as EL.</div>
                </div>
                <h6>Leave is generated every year</h6>
                <p>The first time anyone opens the app in a new year, each employee on the rolls receives {{ $paid->map(fn ($t) => $t->code.' '.num_clean($t->yearly_quota))->implode(' + ') }} = <strong>{{ num_clean($paid->sum('yearly_quota')) }} days</strong>. Employees who join or leave during the year get a share by month (e.g. joining in July → 6 of 12 months → 14 days). You can review it, change one employee's figures, or press <span class="kbd-chip">Generate / Refresh</span> on <a href="{{ route('leave.allocations') }}">Yearly Leave Allocation</a>. Figures you changed by hand are marked <span class="badge bg-info">edited</span> and are never overwritten.</p>
                <h6>Closing the leave year</h6>
                <p>When December is the working month, the workflow page offers <span class="kbd-chip">Close leave year</span>. It saves each employee's EL credit for the year (unused leave, capped at {{ num_clean(setting('el_max_per_year')) }} days) into the EL ledger, which is used for final settlement.</p>
                <h6>Looking at another year</h6>
                <p class="mb-0">Use the <strong>Year</strong> selector in the top bar. Every page then shows only that year's data. Switch back to the current year when you are done.</p>
            </div>
        </div>

        {{-- 4 employees --}}
        <div class="card help-sec mb-3" id="employees">
            <div class="card-header"><span class="h-ico"><i class="bi bi-people"></i></span> Employees</div>
            <div class="card-body">
                <ul class="mb-2">
                    <li><strong>Register</strong> with Emp ID, name, joining date, basic salary, specials, monthly tax, OT multiplier (1.5; driver 1.2), PF / ESI / OT / attendance-bonus flags, allowances and email.</li>
                    <li>The list shows staff on the rolls in the selected year. For the current year it defaults to <em>Active</em>; for past years to <em>All</em> (some have left since).</li>
                    <li><strong>Exit:</strong> enter the date of exit and set status to <em>Exited</em>; leave for that year is reduced automatically. An employee with payslips is never deleted, only marked inactive.</li>
                    <li>The employee page shows that year's leave balance, month-by-month usage, EL ledger, leave requests, OT, payslips and settlements.</li>
                </ul>
                <div class="tip">Many employees at once? Use <a href="{{ route('imports.index') }}">Import Data</a> → Employees.</div>
            </div>
        </div>

        {{-- 4b photos & celebrations --}}
        <div class="card help-sec mb-3" id="celebrations">
            <div class="card-header"><span class="h-ico"><i class="bi bi-balloon-heart"></i></span> Photos &amp; celebrations</div>
            <div class="card-body">
                <h6 class="mt-0">Employee photo</h6>
                <p>On <em>Register / Edit employee</em>, click the round photo box (or drag a picture onto it). JPG, PNG or WebP up to 3 MB; you see a preview straight away and the photo is saved with the employee. Tick <em>Remove photo</em> to delete it. Without a photo, coloured initials are shown. Photos appear on the employee list, the employee page, the dashboard and the birthday wishes. They are stored privately and only signed-in admins can see them.</p>
                <h6>On the dashboard</h6>
                <ul class="mb-2">
                    <li><strong>Birthday today:</strong> a celebration banner with the employee's photo, age and confetti, plus a wish in the top-right messages. Press <span class="kbd-chip"><i class="bi bi-stars"></i> Celebrate</span> for more confetti.</li>
                    <li><strong>Birthdays · next 30 days:</strong> who has a birthday soon and in how many days.</li>
                    <li><strong>Work anniversaries · next 30 days:</strong> employees completing another year of service (from the joining date).</li>
                    <li><strong>Joined &amp; left:</strong> new joiners and exits of the selected year, with photos.</li>
                </ul>
                <div class="tip mb-0">Birthdays need the <em>Date of birth</em> on the employee record. A 🎂 badge also shows next to the name on the employee list on the day.</div>
            </div>
        </div>

        {{-- 5 leave --}}
        <div class="card help-sec mb-3" id="leave">
            <div class="card-header"><span class="h-ico"><i class="bi bi-calendar2-check"></i></span> Leave management</div>
            <div class="card-body">
                <h6 class="mt-0">Rules</h6>
                <ul>
                    <li>Yearly pool: {{ $paid->map(fn ($t) => $t->code.' '.num_clean($t->yearly_quota))->implode(' + ') }} = {{ num_clean($paid->sum('yearly_quota')) }} days (from the Yearly Leave Allocation).</li>
                    <li>Leave beyond the pool, and leave of an unpaid type, is <strong>Loss of Pay (LOP)</strong> and reduces present days in payroll.</li>
                    <li>Leave entered without a type (monthly register) is charged <strong>SL → CL → EL</strong>.</li>
                    <li>The weekly off is not counted when days are calculated from dates.</li>
                    <li>EL credit for a year = min({{ num_clean(setting('el_max_per_year')) }}, pool − leave used).</li>
                </ul>
                <h6>Pages</h6>
                <table class="table table-sm">
                    <tr><td class="fw-semibold"><a href="{{ route('leave.allocations') }}">Yearly Leave Allocation</a></td><td>Leave granted to each employee for the year; generate, edit, reset, export.</td></tr>
                    <tr><td class="fw-semibold"><a href="{{ route('leave-requests.index') }}">Leave Requests</a></td><td>Leave with dates. New → Pending → Approve / Reject; approved leave can be cancelled. Filter by year, month, employee, type.</td></tr>
                    <tr><td class="fw-semibold"><a href="{{ route('leave.adjustments') }}">Monthly Leave Entry</a></td><td>Days taken in a month without exact dates (e.g. from the paper register).</td></tr>
                    <tr><td class="fw-semibold"><a href="{{ route('leave.balances') }}">Leave Balances</a></td><td>Quota, used and balance per type up to a chosen month.</td></tr>
                    <tr><td class="fw-semibold"><a href="{{ route('leave.register') }}">Leave Register</a></td><td>Month-wise grid for the year, like the Excel sheet, with EL credit.</td></tr>
                    <tr><td class="fw-semibold"><a href="{{ route('leave.el-summary') }}">EL Summary</a></td><td>EL credited per year and its value for encashment.</td></tr>
                </table>
                <div class="warn mb-0">Leave in a <strong>finalized</strong> month cannot be added, changed or cancelled.</div>
            </div>
        </div>

        {{-- 6 OT --}}
        <div class="card help-sec mb-3" id="ot">
            <div class="card-header"><span class="h-ico"><i class="bi bi-clock-history"></i></span> OT &amp; shift hours</div>
            <div class="card-body">
                <ul class="mb-2">
                    <li><a href="{{ route('ot.index') }}">OT Entry</a>: choose year and month, type hours for each employee, then <span class="kbd-chip">Save</span> or <span class="kbd-chip">Save &amp; approve</span>. Leave days in the month are shown for checking.</li>
                    <li>2nd shift ₹{{ num_clean(setting('second_shift_rate')) }}/hour, 3rd shift ₹{{ num_clean(setting('third_shift_rate')) }}/hour. OT hours are paid at per-day ÷ {{ num_clean(setting('ot_hours_per_day')) }} × the employee's multiplier.</li>
                    <li><span class="kbd-chip">Approve all pending</span> approves the whole month. Pending or rejected OT is not paid.</li>
                    <li><a href="{{ route('ot.report') }}">OT Report</a>: printable month report by plant with Excel export. <a href="{{ route('ot.history') }}">OT History</a>: approved hours per month for the year.</li>
                </ul>
            </div>
        </div>

        {{-- 7 payroll --}}
        <div class="card help-sec mb-3" id="payroll">
            <div class="card-header"><span class="h-ico"><i class="bi bi-cash-stack"></i></span> Payroll &amp; payslips</div>
            <div class="card-body">
                <h6 class="mt-0">How a payslip is calculated</h6>
                <table class="formula w-100 mb-3">
                    <tr><td>Per day</td><td>Basic ÷ working days ({{ num_clean(setting('working_days')) }})</td></tr>
                    <tr><td>Basic earned</td><td>Per day × present days (working days − LOP days)</td></tr>
                    <tr><td>Shift pay</td><td>2nd / 3rd shift hours × shift rate</td></tr>
                    <tr><td>OT pay</td><td>Per day ÷ {{ num_clean(setting('ot_hours_per_day')) }} × multiplier × OT hours (or a manual amount)</td></tr>
                    <tr><td>Gross</td><td>Basic earned + shift + OT + special OT + manager special + factory special</td></tr>
                    <tr><td>ESI</td><td>Gross × {{ num_clean(setting('esi_percent')) }}% (if applicable)</td></tr>
                    <tr><td>PF</td><td>{{ num_clean(setting('pf_percent')) }}% of basic earned, max ₹{{ inr(setting('pf_cap'), 0) }} (if applicable)</td></tr>
                    <tr><td>Net</td><td>Gross − (ESI + PF + tax + advance + other deductions)</td></tr>
                    <tr><td>Attendance bonus</td><td>One day's pay when the employee had no absence</td></tr>
                    <tr><td>Total payable</td><td>Net + allowances + attendance bonus + birthday welfare (rounded to the rupee)</td></tr>
                </table>
                <h6>Actions</h6>
                <ul class="mb-2">
                    <li><strong>Edit a payslip</strong> (draft only): advance, other deduction, special OT amount, birthday welfare, OT override, remarks. These stay when you recalculate.</li>
                    <li><strong>Export</strong> the salary register to Excel; download all payslips as one PDF or a single payslip.</li>
                    <li><strong>Finalize</strong> locks the month. @if ($isSuper)As Super Admin you can <strong>Re-open</strong> or <strong>Delete</strong> a run if a correction is needed.@else Only a Super Admin can re-open it.@endif</li>
                    <li><strong>Email</strong> sends each payslip PDF to the employee's email (set up SMTP in Settings → Email first).</li>
                </ul>
                <h6>Only filled columns and lines</h6>
                <ul class="mb-2">
                    <li>The <strong>salary register</strong> hides columns that are empty for the whole month (e.g. no 3rd shift, no tax, no advance) and shows them automatically as soon as one employee has a value. Click <em>show all columns</em> to see every column. Filled cells are coloured: <span class="reg-key f-add">Addition</span> <span class="reg-key f-red">Reduction</span> <span class="reg-key f-allow">Allowance</span>.</li>
                    <li>Each <strong>payslip</strong> (screen, PDF and email) lists only the earnings, deductions and allowances that apply to that employee that month.</li>
                </ul>
                <p class="small text-muted mb-0">The payroll list shows one year at a time with totals for gross, net and payable. The Excel export keeps every column, in the layout of the original salary sheet.</p>
            </div>
        </div>

        {{-- 8 settlement --}}
        <div class="card help-sec mb-3" id="settlement">
            <div class="card-header"><span class="h-ico"><i class="bi bi-briefcase"></i></span> EL &amp; gratuity settlement</div>
            <div class="card-body">
                <p>When an employee leaves, open <a href="{{ route('settlements.create') }}">EL &amp; Gratuity Settlement</a>, choose the employee and exit date. The system fills in:</p>
                <table class="formula w-100 mb-2">
                    <tr><td>EL amount</td><td>Sum of each year's EL days × per-day rate (Basic ÷ 26)</td></tr>
                    <tr><td>Gratuity</td><td>Basic ÷ {{ num_clean(setting('gratuity_divisor') ?: 26) }} × {{ num_clean(setting('gratuity_days')) }} × years of service (eligible after {{ num_clean(setting('gratuity_min_years')) }} years)</td></tr>
                </table>
                <p class="mb-0">Year rows, bonus, other amounts and deductions can be adjusted before saving. The settlement can be printed or downloaded as PDF; the list shows settlements of the selected year.</p>
            </div>
        </div>

        {{-- 9 imports --}}
        <div class="card help-sec mb-3" id="imports">
            <div class="card-header"><span class="h-ico"><i class="bi bi-cloud-arrow-up"></i></span> Importing from Excel</div>
            <div class="card-body">
                <ol class="mb-2">
                    <li>Open <a href="{{ route('imports.index') }}">Import Data</a> and download the <strong>template</strong> (it already lists your employees).</li>
                    <li>Fill it in Excel, choose the month (OT) or year (leave register), then <strong>drag the file onto the coloured box</strong> (or click it) and press <span class="kbd-chip">Upload &amp; verify</span>. The box turns green and shows the file name when it is ready.</li>
                    <li>A <strong>preview</strong> checks every row: unknown Emp IDs, wrong numbers, too many hours, locked months. If the workbook has several sheets you can switch sheet. Rows are colour-coded: <span class="badge bg-success">green edge = ready</span> <span class="badge bg-warning">amber = check</span> <span class="badge bg-danger">red = error</span>, and every filled value is tinted green. Use the chips <em>All / Ready / Check / Errors</em> above the table to show one kind of row.</li>
                    <li>Fix or skip problem rows, then confirm. Nothing is saved before this step.</li>
                </ol>
                <div class="tip mb-0">Employees are matched by Emp ID, then by name. Importing again for the same month/year replaces the earlier import.</div>
            </div>
        </div>

        {{-- 10 admin --}}
        <div class="card help-sec mb-3" id="admin">
            <div class="card-header"><span class="h-ico"><i class="bi bi-shield-lock"></i></span> Backups &amp; administration <span class="badge bg-danger ms-1">Super Admin</span></div>
            <div class="card-body">
                <ul class="mb-0">
                    <li><strong>Database Backups:</strong> a full backup (all tables and all data) is taken daily (Windows scheduled task, plus an automatic backup if a day was missed). A reminder pops up at the set time to take and download one yourself. Backups are ZIP files with a README explaining how to restore.</li>
                    <li><strong>Settings:</strong> company details, working days, payroll deadline day, rates (shift, OT, ESI, PF, gratuity), leave types and quotas, allowance types, weekly off and email (SMTP).</li>
                    <li><strong>Admin Accounts:</strong> add admins, reset passwords, enable or disable accounts.</li>
                    <li><strong>Activity Log:</strong> who did what and when (IST), filterable by year, month, user and module.</li>
                </ul>
            </div>
        </div>

        {{-- 11 FAQ --}}
        <div class="card help-sec mb-3" id="faq">
            <div class="card-header"><span class="h-ico"><i class="bi bi-question-circle"></i></span> Questions &amp; fixes</div>
            <div class="card-body">
                <div class="accordion" id="faqAcc">
                    @foreach ([
                        ['A page shows no data / old numbers.', 'Check the Year in the top bar; it may still be on a past year (shown in orange). Switch it to the current year.'],
                        ['An employee is missing from OT entry or payroll.', 'Check the joining date, exit date and status on the employee page. Only employees on the rolls during the month are included.'],
                        ['Leave or OT is not reducing / adding pay.', 'Only approved leave and approved OT count. Approve them, then click Recalculate on the payroll.'],
                        ['I need to change a finalized month.', 'Ask a Super Admin to Re-open the payroll, make the change, Recalculate, and Finalize again.'],
                        ['A joiner has the wrong leave.', 'Correct the joining date; leave is recalculated. Or edit the figures on Yearly Leave Allocation.'],
                        ['Payslip email failed.', 'Check the employee’s email address and Settings → Email (send a test mail). Then send again from the payroll page.'],
                        ['The clock or dates look wrong.', 'The app always uses India Standard Time from the server, so a wrong PC clock does not matter. Tell the Super Admin if the server time is wrong.'],
                    ] as $i => [$q, $a])
                        <div class="accordion-item">
                            <h2 class="accordion-header"><button class="accordion-button {{ $i ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faq{{ $i }}">{{ $q }}</button></h2>
                            <div id="faq{{ $i }}" class="accordion-collapse collapse {{ $i ? '' : 'show' }}" data-bs-parent="#faqAcc"><div class="accordion-body">{{ $a }}</div></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
(function () {
    // highlight the section in view
    const links = [...document.querySelectorAll('#helpToc a')];
    const obs = new IntersectionObserver(entries => entries.forEach(e => {
        if (e.isIntersecting) links.forEach(a => a.classList.toggle('active', a.getAttribute('href') === '#' + e.target.id));
    }), {rootMargin: '-30% 0px -60% 0px'});
    document.querySelectorAll('.help-sec').forEach(s => obs.observe(s));

    // search: show only matching sections
    const input = document.getElementById('helpSearch');
    let t;
    input.addEventListener('input', () => {
        clearTimeout(t);
        t = setTimeout(() => {
            const q = input.value.trim().toLowerCase();
            let hits = 0;
            document.querySelectorAll('.help-sec').forEach(s => {
                const show = !q || s.textContent.toLowerCase().includes(q);
                s.style.display = show ? '' : 'none';
                links.find(a => a.getAttribute('href') === '#' + s.id).style.display = show ? '' : 'none';
                if (show) hits++;
            });
            if (q) window.notify?.({type: hits ? 'info' : 'warning', title: hits ? `${hits} section(s) match “${input.value.trim()}”` : 'Nothing found', message: hits ? '' : 'Try another word.', duration: 2500});
        }, 400);
    });
})();
</script>
@endpush
