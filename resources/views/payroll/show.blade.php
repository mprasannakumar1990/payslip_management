@extends('layouts.app')
@section('title', 'Payslip — '.$run->periodLabel())
@section('back', route('payroll.index'))
@section('content')
<div class="card mb-3 no-print">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center py-2">
        <span class="badge fs-6 {{ $run->isFinalized() ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($run->status) }}</span>
        <span class="small text-muted">{{ num_clean($run->working_days) }} working days · {{ $payslips->count() }} payslips</span>
        <form class="d-flex flex-wrap gap-1 ms-md-3 auto-filter" method="GET"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search name / ID"><select name="email" class="form-select form-select-sm" style="width:150px"><option value="">All payslips</option>@foreach (['sent' => 'Emailed', 'unsent' => 'Not emailed', 'failed' => 'Email failed', 'noemail' => 'No email address'] as $k => $v)<option value="{{ $k }}" @selected(request('email') === $k)>{{ $v }}</option>@endforeach</select></form>
        <div class="ms-auto d-flex flex-wrap gap-2">
            @unless ($run->isFinalized())
                <form method="POST" action="{{ route('payroll.generate', $run) }}" class="d-flex gap-1" data-confirm-title="Recalculate payslips" data-confirm-level="double" data-busy="Recalculating…" data-confirm="Recalculate all payslips from the latest employee, leave and OT data? Manual entries (advance, other deductions, Spl OT, birthday welfare, OT override) are kept.">@csrf
                    <input type="number" step="0.5" name="working_days" value="{{ num_clean($run->working_days) }}" class="form-control form-control-sm" style="width:70px" title="Working days">
                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Recalculate</button>
                </form>
                <form method="POST" action="{{ route('payroll.finalize', $run) }}" data-confirm="Finalize {{ $run->periodLabel() }}? Payslips, leave and OT for the month will be locked." data-confirm-title="Finalize payroll" data-confirm-level="double" data-confirm-final="After finalizing, only a Super Admin can re-open this month.">@csrf
                    <button class="btn btn-sm btn-success"><i class="bi bi-lock"></i> Finalize</button>
                </form>
            @endunless
            @if (auth()->user()->isSuperAdmin())
                @if ($run->isFinalized())
                    <form method="POST" action="{{ route('payroll.reopen', $run) }}" data-confirm="Re-open {{ $run->periodLabel() }} for editing? Payslips already emailed may then differ from the system." data-confirm-title="Re-open payroll" data-confirm-variant="warning" data-confirm-level="double">@csrf<button class="btn btn-sm btn-outline-warning"><i class="bi bi-unlock"></i> Re-open</button></form>
                @else
                    <form method="POST" action="{{ route('payroll.destroy', $run) }}" data-confirm="Delete the {{ $run->periodLabel() }} payroll and all its payslips?" data-confirm-title="Delete payroll" data-confirm-level="double">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                @endif
            @endif
            <a href="{{ route('payroll.export', $run) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Salary register</a>
            <a href="{{ route('payroll.pdf', $run) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> All payslips PDF</a>
            <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
        </div>
    </div>
</div>
@if ($pendingOt && ! $run->isFinalized())
    <div class="alert alert-warning no-print">{{ $pendingOt }} OT entries for this month are pending approval and are <strong>not</strong> included. <a href="{{ route('ot.index', ['period' => sprintf('%04d-%02d', $run->year, $run->month)]) }}">Review OT</a>, then Recalculate.</div>
@endif
@php($sentCount = $run->payslips()->whereNotNull('emailed_at')->count())
@php($allCount = $run->payslips()->count())
<div class="card mb-3 no-print">
    <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
        <i class="bi bi-envelope fs-5"></i>
        <span><strong>{{ $sentCount }}</strong> of {{ $allCount }} payslips emailed</span>
        @unless (\App\Models\Setting::mailConfigured())
            <span class="badge bg-warning text-dark">SMTP not configured — emails are written to the log file.@if(auth()->user()->isSuperAdmin()) <a href="{{ route('settings.edit') }}#email">Set up email</a>@endif</span>
        @endunless
        @if ($run->isFinalized())
            <div class="ms-auto d-flex gap-2">
                <form method="POST" action="{{ route('payroll.email', $run) }}" data-confirm="Email payslips to all employees who have not received one yet ({{ $allCount - $sentCount }})?" data-confirm-title="Email payslips" data-confirm-variant="success" data-confirm-level="double" data-confirm-final="Emails cannot be recalled once sent. Please make sure the payslips are correct." data-busy="Sending emails… this can take a minute">@csrf
                    <button class="btn btn-sm btn-success" @disabled($sentCount >= $allCount)><i class="bi bi-send"></i> Email all unsent ({{ $allCount - $sentCount }})</button>
                </form>
                <form method="POST" action="{{ route('payroll.email', $run) }}" data-confirm="Send the payslip again to EVERY employee, including those already emailed?" data-confirm-title="Resend to all" data-confirm-variant="warning" data-confirm-level="double" data-busy="Sending emails… this can take a minute">@csrf
                    <input type="hidden" name="resend" value="1">
                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat"></i> Resend to all</button>
                </form>
            </div>
        @else
            <span class="ms-auto small text-muted">Finalize the payroll to enable emailing.</span>
        @endif
    </div>
</div>

<div class="card">
    <div class="table-responsive" style="max-height:75vh">
        <table class="table table-sm table-bordered table-hover table-register mb-0">
            <thead class="sticky-top">
                <tr>
                    <th colspan="3">Information</th><th colspan="5" class="text-center">Basic</th><th colspan="6" class="text-center">Addition</th><th class="num">Gross</th><th colspan="4" class="text-center">Reduction</th><th class="num">Net</th><th colspan="2" class="text-center">Allowance</th><th class="num">Total</th><th class="no-print">Email</th><th class="no-print"></th>
                </tr>
                <tr>
                    <th>Emp ID</th><th>Name</th><th>Grade</th>
                    <th class="num">Basic</th><th class="num">Per day</th><th class="num">Present</th><th class="num">Absent</th><th class="num">Earned</th>
                    <th class="num">2nd</th><th class="num">3rd</th><th class="num">Spl OT</th><th class="num">OT hrs</th><th class="num">OT pays</th><th class="num">Mgr + Fac spl</th>
                    <th class="num">Salary</th>
                    <th class="num">ESI</th><th class="num">PF</th><th class="num">TAX</th><th class="num">Other</th>
                    <th class="num">Salary</th>
                    <th class="num">Att. bonus</th><th class="num">Total</th>
                    <th class="num">Amount</th><th class="no-print"></th><th class="no-print"></th>
                </tr>
            </thead>
            <tbody>
            @foreach ($payslips as $p)
                <tr>
                    <td>{{ $p->emp_code }}</td>
                    <td class="text-truncate" style="max-width:160px" title="{{ $p->designation }}">{{ $p->name }} @if($p->remarks)<i class="bi bi-chat-left-text text-muted" title="{{ $p->remarks }}"></i>@endif</td>
                    <td>{{ $p->grade }}</td>
                    <td class="num">{{ inr($p->basic_salary, 0) }}</td>
                    <td class="num">{{ inr($p->per_day) }}</td>
                    <td class="num">{{ num_clean($p->present_days) }}</td>
                    <td class="num {{ $p->lop_days > 0 ? 'text-danger fw-semibold' : '' }}" title="LOP {{ num_clean($p->lop_days) }}">{{ num_clean($p->absent_days) }}</td>
                    <td class="num">{{ inr($p->basic_earned) }}</td>
                    <td class="num">{{ inr($p->second_shift_amount) }}</td>
                    <td class="num">{{ inr($p->third_shift_amount) }}</td>
                    <td class="num">{{ $p->spl_ot_amount ? inr($p->spl_ot_amount) : '' }}</td>
                    <td class="num">{{ num_clean($p->ot_hours) }}</td>
                    <td class="num {{ $p->ot_amount_manual !== null ? 'text-info' : '' }}">{{ inr($p->ot_amount) }}</td>
                    <td class="num">{{ inr($p->manager_special + $p->factory_special) }}</td>
                    <td class="num fw-semibold">{{ inr($p->gross_salary) }}</td>
                    <td class="num">{{ inr($p->esi) }}</td>
                    <td class="num">{{ inr($p->pf, 0) }}</td>
                    <td class="num">{{ $p->tax ? inr($p->tax, 0) : '' }}</td>
                    <td class="num">{{ ($p->advance + $p->other_deduction) ? inr($p->advance + $p->other_deduction) : '' }}</td>
                    <td class="num fw-semibold">{{ inr($p->net_salary) }}</td>
                    <td class="num">{{ $p->attendance_bonus ? inr($p->attendance_bonus) : '' }}</td>
                    <td class="num">{{ inr($p->total_allowance) }}</td>
                    <td class="num fw-bold">{{ inr($p->total_amount, 0) }}</td>
                    <td class="no-print text-nowrap">
                        @if ($p->emailed_at)
                            <span class="text-success" title="Sent to {{ $p->emailed_to }} on {{ $p->emailed_at->format('d M Y H:i') }}"><i class="bi bi-envelope-check"></i> {{ $p->emailed_at->format('d M') }}</span>
                        @elseif ($p->email_error)
                            <span class="text-danger" title="{{ $p->email_error }}"><i class="bi bi-envelope-exclamation"></i> failed</span>
                        @elseif (! $p->employee?->email)
                            <span class="text-muted" title="Add an email address on the employee record"><i class="bi bi-envelope-slash"></i> no email</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                        @if ($run->isFinalized() && $p->employee?->email)
                            <button form="em-{{ $p->id }}" class="btn btn-link btn-sm p-0" title="{{ $p->emailed_at ? 'Resend' : 'Send' }} payslip"><i class="bi bi-send"></i></button>
                        @endif
                    </td>
                    <td class="text-nowrap no-print">
                        <a href="{{ route('payslips.show', $p) }}" class="btn btn-sm btn-link p-0" title="View"><i class="bi bi-eye"></i></a>
                        @unless ($run->isFinalized())<a href="{{ route('payslips.edit', $p) }}" class="btn btn-sm btn-link p-0" title="Edit"><i class="bi bi-pencil"></i></a>@endunless
                        <a href="{{ route('payslips.pdf', $p) }}" class="btn btn-sm btn-link p-0 text-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                    </td>
                </tr>
            @endforeach
            @php($t = fn ($f) => $payslips->sum($f))
            <tr class="total-row">
                <td colspan="3">Total</td>
                <td class="num">{{ inr($t('basic_salary'), 0) }}</td><td></td><td></td><td></td>
                <td class="num">{{ inr($t('basic_earned')) }}</td>
                <td class="num">{{ inr($t('second_shift_amount')) }}</td><td class="num">{{ inr($t('third_shift_amount')) }}</td><td class="num">{{ inr($t('spl_ot_amount')) }}</td>
                <td class="num">{{ num_clean($t('ot_hours')) }}</td><td class="num">{{ inr($t('ot_amount')) }}</td><td class="num">{{ inr($t('manager_special') + $t('factory_special')) }}</td>
                <td class="num">{{ inr($t('gross_salary')) }}</td>
                <td class="num">{{ inr($t('esi')) }}</td><td class="num">{{ inr($t('pf'), 0) }}</td><td class="num">{{ inr($t('tax'), 0) }}</td><td class="num">{{ inr($t('advance') + $t('other_deduction')) }}</td>
                <td class="num">{{ inr($t('net_salary')) }}</td>
                <td class="num">{{ inr($t('attendance_bonus')) }}</td><td class="num">{{ inr($t('total_allowance')) }}</td>
                <td class="num">{{ inr($t('total_amount'), 0) }}</td><td class="no-print"></td><td class="no-print"></td>
            </tr>
            </tbody>
        </table>
    </div>
</div>
@foreach ($payslips as $p)
    <form id="em-{{ $p->id }}" method="POST" action="{{ route('payslips.email', $p) }}" class="d-none">@csrf</form>
@endforeach
@endsection
