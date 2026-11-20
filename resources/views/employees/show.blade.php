@extends('layouts.app')
@section('title', $employee->emp_code.' — '.$employee->name)
@section('back', route('employees.index'))
@section('content')
<div class="d-flex flex-wrap gap-2 mb-3 no-print">
    <a href="{{ route('employees.edit', $employee) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i> Edit</a>
    <a href="{{ route('leave-requests.create', ['employee_id' => $employee->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar-plus"></i> Add leave</a>
    <a href="{{ route('settlements.create', ['employee_id' => $employee->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-briefcase"></i> EL / Gratuity settlement</a>
    <a href="{{ route('employees.index') }}" class="btn btn-sm btn-light ms-auto">Back to list</a>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3 overflow-hidden">
            <div class="profile-cover">
                @include('partials.avatar', ['e' => $employee, 'size' => 88, 'ring' => 'ring'])
                <div class="min-w-0">
                    <div class="fs-5 fw-bold text-truncate">{{ $employee->name }}</div>
                    <div class="small opacity-75">{{ $employee->emp_code }} · {{ $employee->designation }}</div>
                    @php($nb = $employee->nextBirthday())
                    @if ($nb?->isToday())
                        <span class="badge bg-warning mt-1">🎂 Happy birthday today!</span>
                    @elseif ($nb && $nb->diffInDays(now()->startOfDay(), true) <= 30)
                        <span class="badge bg-light mt-1">🎂 Birthday {{ $nb->format('d M') }}</span>
                    @endif
                </div>
            </div>
            <div class="card-body small">
                <dl class="row mb-0">
                    @foreach ([
                        'Designation' => $employee->designation, 'Grade' => $employee->grade, 'Plant / group' => $employee->ot_group,
                        'Date of birth' => $employee->date_of_birth?->format('d M Y'),
                        'Joined' => $employee->date_of_joining?->format('d M Y'), 'Service' => $employee->serviceYears().' yrs',
                        'Exit date' => $employee->date_of_exit?->format('d M Y'), 'Status' => ucfirst($employee->status),
                        'EPF / UAN' => $employee->uan_no, 'ESIC' => $employee->esic_no, 'PAN' => $employee->pan_no,
                        'Phone' => $employee->phone, 'Email' => $employee->email,
                        'Bank' => trim($employee->bank_name.' '.$employee->bank_account_no.' '.$employee->bank_ifsc),
                    ] as $k => $val)
                        @if ($val)
                            <dt class="col-5 text-muted fw-normal">{{ $k }}</dt><dd class="col-7 mb-1">{{ $val }}</dd>
                        @endif
                    @endforeach
                </dl>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">Salary structure</div>
            <table class="table table-sm mb-0">
                <tr><td>Basic</td><td class="num">{{ inr($employee->basic_salary) }}</td></tr>
                <tr><td>Per day (÷{{ num_clean(setting('working_days')) }})</td><td class="num">{{ inr($employee->perDay()) }}</td></tr>
                @if($employee->manager_special)<tr><td>Manager special</td><td class="num">{{ inr($employee->manager_special) }}</td></tr>@endif
                @if($employee->factory_special)<tr><td>Factory special</td><td class="num">{{ inr($employee->factory_special) }}</td></tr>@endif
                @if($employee->monthly_tax)<tr><td>Monthly TAX</td><td class="num">{{ inr($employee->monthly_tax) }}</td></tr>@endif
                <tr><td>OT multiplier</td><td class="num">{{ num_clean($employee->ot_multiplier) }}×</td></tr>
                @foreach ($employee->allowances as $a)
                    <tr><td>{{ $a->type?->name }}</td><td class="num">{{ inr($a->amount) }}</td></tr>
                @endforeach
                <tr><td colspan="2" class="small text-muted">
                    PF: {{ $employee->pf_applicable ? 'Yes' : 'No' }} · ESI: {{ $employee->esi_applicable ? 'Yes' : 'No' }} ·
                    OT: {{ $employee->ot_eligible ? 'Yes' : 'No' }} · Attendance bonus: {{ $employee->attendance_bonus_applicable ? 'Yes' : 'No' }}
                </td></tr>
            </table>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Leave — {{ $year }}</span>
                <form class="d-flex gap-1 auto-filter" method="GET"><select name="year" class="form-select form-select-sm" style="width:100px">@foreach (year_options() as $y)<option @selected($y == $year)>{{ $y }}</option>@endforeach</select></form>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3 text-center">
                    @foreach ($balances['types'] as $t)
                        <div class="col"><div class="border rounded p-2"><div class="small text-muted">{{ $t['code'] }}</div><div class="fw-bold">{{ num_clean($t['balance']) }} <span class="text-muted fw-normal small">/ {{ num_clean($t['quota']) }}</span></div></div></div>
                    @endforeach
                    <div class="col"><div class="border rounded p-2"><div class="small text-muted">Used</div><div class="fw-bold">{{ num_clean($balances['used']) }}</div></div></div>
                    <div class="col"><div class="border rounded p-2 {{ $balances['excess'] + $balances['lop'] > 0 ? 'border-danger' : '' }}"><div class="small text-muted">LOP</div><div class="fw-bold text-danger">{{ num_clean($balances['excess'] + $balances['lop']) }}</div></div></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered text-center mb-0">
                        <tr>@foreach (range(1, 12) as $m)<th>{{ month_name($m, true) }}</th>@endforeach<th>Total</th></tr>
                        <tr>@foreach ($usage as $u)<td>{{ $u['total'] ? num_clean($u['total']) : '' }}</td>@endforeach<td class="fw-bold">{{ num_clean(array_sum(array_column($usage, 'total'))) }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Earned leave ledger</div>
            <div class="card-body">
                <p class="small text-muted mb-2">Each year's EL credit is computed as min({{ num_clean(setting('el_max_per_year')) }}, {{ num_clean(setting('annual_leave_pool')) }} − leave used), pro-rated in the joining year. Enter a value to override a year (e.g. for history before the system); clear it to go back to the computed value.</p>
                <div class="table-responsive" style="max-height:320px">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Year</th><th>Period</th><th class="num">EL days</th><th>Source</th><th style="width:330px">Override</th></tr></thead>
                        @foreach ($elHistory as $h)
                            <tr>
                                <td>{{ $h['year'] }}</td>
                                <td class="small">{{ $h['period'] }}</td>
                                <td class="num fw-semibold">{{ num_clean($h['days']) }}</td>
                                <td class="small">
                                    <span class="badge {{ $h['source'] === 'manual' ? 'bg-info' : 'bg-light text-dark' }}">{{ $h['source'] }}</span>
                                    @if($h['provisional'])<span class="badge bg-warning text-dark">provisional</span>@endif
                                    <span class="text-muted">{{ $h['note'] }}</span>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('employees.el-ledger', $employee) }}" class="d-flex gap-1">@csrf
                                        <input type="hidden" name="year" value="{{ $h['year'] }}">
                                        <input type="number" step="0.01" name="days" class="form-control form-control-sm" style="width:80px" value="{{ $h['source'] === 'manual' ? $h['days'] : '' }}" placeholder="auto">
                                        <input name="note" class="form-control form-control-sm" placeholder="note" value="{{ $h['source'] === 'manual' ? $h['note'] : '' }}">
                                        <button class="btn btn-sm btn-outline-primary">Set</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        <tr class="total-row"><td colspan="2">Total EL</td><td class="num">{{ num_clean(collect($elHistory)->sum('days')) }}</td><td colspan="2" class="small">≈ ₹ {{ inr(collect($elHistory)->sum('days') * round($employee->perDay())) }} at today's per-day rate</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Leave requests — {{ $year }}</div>
                    <table class="table table-sm mb-0">
                        @forelse ($leaves as $l)
                            <tr><td>{{ $l->leaveType->code }}</td><td class="small">{{ $l->from_date->format('d M y') }}{{ $l->from_date->ne($l->to_date) ? ' – '.$l->to_date->format('d M y') : '' }}</td><td>{{ num_clean($l->days) }}</td><td><span class="badge bg-light text-dark">{{ $l->status }}</span></td></tr>
                        @empty
                            <tr><td class="text-muted">None</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">OT — {{ $year }}</div>
                    <table class="table table-sm mb-0">
                        <tr><th>Month</th><th class="num">2nd</th><th class="num">3rd</th><th class="num">OT</th><th></th></tr>
                        @forelse ($otEntries as $o)
                            <tr><td>{{ month_name($o->month, true) }} {{ $o->year }}</td><td class="num">{{ num_clean($o->second_shift_hours) }}</td><td class="num">{{ num_clean($o->third_shift_hours) }}</td><td class="num">{{ num_clean($o->totalOt()) }}</td><td><span class="badge bg-light text-dark">{{ $o->status }}</span></td></tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">None</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Payslips — {{ $year }}</div>
                    <table class="table table-sm mb-0">
                        <tr><th>Month</th><th class="num">Gross</th><th class="num">Net</th><th class="num">Total paid</th><th></th></tr>
                        @forelse ($payslips as $p)
                            <tr>
                                <td>{{ $p->run->periodLabel() }}</td><td class="num">{{ inr($p->gross_salary) }}</td><td class="num">{{ inr($p->net_salary) }}</td><td class="num fw-semibold">{{ inr($p->total_amount, 0) }}</td>
                                <td class="text-end"><a href="{{ route('payslips.show', $p) }}" class="btn btn-sm btn-outline-secondary py-0">View</a> <a href="{{ route('payslips.pdf', $p) }}" class="btn btn-sm btn-outline-danger py-0">PDF</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No payslips yet.</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
            @if ($settlements->isNotEmpty())
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">Settlements</div>
                        <table class="table table-sm mb-0">
                            @foreach ($settlements as $st)
                                <tr><td>{{ $st->settlement_date->format('d M Y') }}</td><td class="num">₹ {{ inr($st->total_amount, 0) }}</td><td class="text-end"><a href="{{ route('settlements.show', $st) }}">View</a></td></tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
