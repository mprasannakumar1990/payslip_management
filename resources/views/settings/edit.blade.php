@extends('layouts.app')
@section('title', 'Settings')
@section('content')
<form method="POST" action="{{ route('settings.update') }}">
    @csrf @method('PUT')
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Company</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Company name</label><input name="company_name" class="form-control" value="{{ old('company_name', $s['company_name']) }}" required></div>
                    <div class="mb-3"><label class="form-label">Address</label><input name="company_address" class="form-control" value="{{ old('company_address', $s['company_address']) }}"></div>
                    <div class="mb-3">
                        <label class="form-label">Payslip deadline (day of the following month)</label>
                        <input type="number" min="1" max="28" name="payroll_deadline_day" class="form-control" style="max-width:120px" value="{{ old('payroll_deadline_day', $s['payroll_deadline_day']) }}" required>
                        <div class="form-text">Payslips for a month are generated from the 1st to this day of the next month. Until this day the app opens the previous month by default.</div>
                    </div>
                    <div class="mb-3"><label class="form-label">Payslip footer message</label><input name="payslip_footer" class="form-control" value="{{ old('payslip_footer', $s['payslip_footer']) }}"></div>
                    <div class="mb-3">
                        <label class="form-label">Plants / OT groups (one per line, in report order)</label>
                        <textarea name="ot_groups" rows="7" class="form-control">{{ old('ot_groups', $s['ot_groups']) }}</textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Salary &amp; OT rules</div>
                <div class="card-body row g-3">
                    @foreach ([
                        ['working_days', 'Working days per month', 'Per-day salary = Basic ÷ this'],
                        ['ot_hours_per_day', 'Hours per day', 'OT hourly rate = per day ÷ this'],
                        ['ot_multiplier', 'Default OT multiplier', 'e.g. 1.5 = time and a half'],
                        ['second_shift_rate', '2nd shift rate (₹ / hour)', ''],
                        ['third_shift_rate', '3rd shift rate (₹ / hour)', ''],
                        ['pf_percent', 'PF % of earned basic', ''],
                        ['pf_cap', 'PF maximum (₹)', '0 = no cap'],
                        ['esi_percent', 'ESI % of gross', ''],
                    ] as [$k, $label, $help])
                        <div class="col-md-6">
                            <label class="form-label">{{ $label }}</label>
                            <input type="number" step="0.001" name="{{ $k }}" class="form-control" value="{{ old($k, $s[$k]) }}" required>
                            @if($help)<div class="form-text">{{ $help }}</div>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">Leave, EL &amp; gratuity rules</div>
                <div class="card-body row g-3">
                    @foreach ([
                        ['annual_leave_pool', 'Annual leave pool (days)', 'CL + SL + EL; usage beyond this is LOP'],
                        ['el_max_per_year', 'Max EL credited per year', 'Unused leave credited as EL up to this'],
                        ['gratuity_days', 'Gratuity days per year', 'Basic ÷ divisor × days × years'],
                        ['gratuity_divisor', 'Gratuity divisor', ''],
                        ['gratuity_min_years', 'Minimum years for gratuity', 'Shown as a warning only'],
                        ['krw_rate', 'KRW per INR', 'For the KRW column in EL summary'],
                    ] as [$k, $label, $help])
                        <div class="col-md-4 col-lg-2">
                            <label class="form-label">{{ $label }}</label>
                            <input type="number" step="0.01" name="{{ $k }}" class="form-control" value="{{ old($k, $s[$k]) }}" required>
                            @if($help)<div class="form-text">{{ $help }}</div>@endif
                        </div>
                    @endforeach
                    <div class="col-md-4 col-lg-2">
                        <label class="form-label">Weekly off</label>
                        <select name="weekly_off" class="form-select">
                            @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $i => $d)
                                <option value="{{ $i }}" @selected((int) old('weekly_off', $s['weekly_off']) === $i)>{{ $d }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Excluded when counting leave days</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="my-3"><button class="btn btn-primary"><i class="bi bi-save"></i> Save settings</button></div>
</form>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Allowance types</div>
            <div class="card-body">
                <p class="small text-muted">Fixed monthly allowances paid on top of net salary (Food, Transport, increments …). Amounts are set per employee.</p>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Name</th><th style="width:80px">Order</th><th style="width:70px">Active</th><th></th></tr></thead>
                    @foreach ($allowanceTypes as $t)
                        <tr>
                            <td><input form="at{{ $t->id }}" name="name" value="{{ $t->name }}" class="form-control form-control-sm"></td>
                            <td><input form="at{{ $t->id }}" name="sort_order" type="number" value="{{ $t->sort_order }}" class="form-control form-control-sm"></td>
                            <td><input form="at{{ $t->id }}" type="checkbox" name="is_active" value="1" class="form-check-input" @checked($t->is_active)></td>
                            <td><button form="at{{ $t->id }}" class="btn btn-sm btn-outline-primary">Save</button></td>
                        </tr>
                    @endforeach
                </table>
                @foreach ($allowanceTypes as $t)
                    <form id="at{{ $t->id }}" method="POST" action="{{ route('settings.allowance-types.update', $t) }}">@csrf @method('PUT')</form>
                @endforeach
                <form method="POST" action="{{ route('settings.allowance-types.store') }}" class="d-flex gap-2">@csrf
                    <input name="name" class="form-control form-control-sm" placeholder="New allowance name" required>
                    <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-plus"></i> Add</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Leave types</div>
            <div class="card-body">
                <p class="small text-muted">Yearly quota per type. Loss of Pay is unpaid and reduces present days in payroll.</p>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Code</th><th>Name</th><th style="width:110px">Yearly quota</th><th></th></tr></thead>
                    @foreach ($leaveTypes as $t)
                        <tr>
                            <td><span class="badge {{ $t->is_paid ? 'bg-primary' : 'bg-danger' }}">{{ $t->code }}</span></td>
                            <td><input form="lt{{ $t->id }}" name="name" value="{{ $t->name }}" class="form-control form-control-sm"></td>
                            <td><input form="lt{{ $t->id }}" name="yearly_quota" type="number" step="0.5" value="{{ $t->yearly_quota }}" class="form-control form-control-sm" @readonly(! $t->is_paid)></td>
                            <td><button form="lt{{ $t->id }}" class="btn btn-sm btn-outline-primary">Save</button></td>
                        </tr>
                    @endforeach
                </table>
                @foreach ($leaveTypes as $t)
                    <form id="lt{{ $t->id }}" method="POST" action="{{ route('settings.leave-types.update', $t) }}">@csrf @method('PUT')</form>
                @endforeach
            </div>
        </div>
    </div>
</div>
<div class="card mt-3" id="email">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-envelope"></i> Email (SMTP) for sending payslips</span>
        @if (\App\Models\Setting::mailConfigured())<span class="badge bg-success">Configured</span>@else<span class="badge bg-warning text-dark">Not configured — emails go to the log file</span>@endif
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('settings.mail') }}" class="row g-3">
            @csrf @method('PUT')
            <div class="col-md-4"><label class="form-label">SMTP host</label><input name="mail_host" class="form-control" value="{{ old('mail_host', $s['mail_host']) }}" placeholder="smtp.gmail.com / smtp.office365.com"></div>
            <div class="col-md-2"><label class="form-label">Port</label><input type="number" name="mail_port" class="form-control" value="{{ old('mail_port', $s['mail_port']) }}"></div>
            <div class="col-md-2"><label class="form-label">Encryption</label>
                <select name="mail_encryption" class="form-select">@foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None'] as $k => $v)<option value="{{ $k }}" @selected(old('mail_encryption', $s['mail_encryption']) === $k)>{{ $v }}</option>@endforeach</select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Username (full email address)</label>
                <input type="email" name="mail_username" class="form-control @error('mail_username') is-invalid @enderror" value="{{ old('mail_username', $s['mail_username']) }}" placeholder="name@gmail.com" autocomplete="off">
                @error('mail_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">The email address you sign in with, not your name.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Password / app password</label>
                <input type="password" name="mail_password" class="form-control" placeholder="{{ $s['mail_password'] ? '•••••• saved — leave blank to keep' : '' }}" autocomplete="new-password">
                <div class="form-text">Gmail: use a 16-character <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">App Password</a> (needs 2-Step Verification), not your normal password.</div>
                @if ($s['mail_password'])<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_password" value="1" id="clearpw"><label class="form-check-label small" for="clearpw">Remove saved password</label></div>@endif
            </div>
            <div class="col-md-4"><label class="form-label">From email</label><input type="email" name="mail_from_address" class="form-control" value="{{ old('mail_from_address', $s['mail_from_address']) }}"></div>
            <div class="col-md-4"><label class="form-label">From name</label><input name="mail_from_name" class="form-control" value="{{ old('mail_from_name', $s['mail_from_name']) }}"></div>
            <div class="col-md-12"><label class="form-label">Subject</label><input name="mail_subject" class="form-control" value="{{ old('mail_subject', $s['mail_subject']) }}"></div>
            <div class="col-md-12">
                <label class="form-label">Message</label>
                <textarea name="mail_body" rows="6" class="form-control">{{ old('mail_body', $s['mail_body']) }}</textarea>
                <div class="form-text">Placeholders: {name}, {emp_code}, {month}, {amount}, {company}. The payslip PDF is attached automatically.</div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-save"></i> Save email settings</button>
            </div>
        </form>
        <form method="POST" action="{{ route('settings.mail.test') }}" class="mt-3 d-flex flex-wrap gap-2 align-items-end"
              data-confirm="Send a test email using the saved settings?" data-confirm-title="Send test email" data-busy="Sending test email…">@csrf
            <div>
                <label class="form-label">Send test to</label>
                <input type="email" name="test_to" class="form-control form-control-sm" style="min-width:260px" required
                       value="{{ old('test_to', $s['mail_test_to'] ?? auth()->user()->email) }}">
            </div>
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-send"></i> Send test email</button>
            <span class="small text-muted">Save the settings above first — the test uses the saved settings.</span>
        </form>
    </div>
</div>
@endsection
