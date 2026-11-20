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

@include('payroll._register')
@foreach ($payslips as $p)
    <form id="em-{{ $p->id }}" method="POST" action="{{ route('payslips.email', $p) }}" class="d-none">@csrf</form>
@endforeach
@endsection
