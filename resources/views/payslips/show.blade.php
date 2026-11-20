@extends('layouts.app')
@section('title', 'Payslip — '.$p->name.' — '.$p->run->periodLabel())
@section('back', route('payroll.show', $p->payroll_run_id))
@push('styles')
    @include('payslips._style')
@endpush
@section('content')
<div class="d-flex flex-wrap gap-2 mb-3 no-print">
    <a href="{{ route('payroll.show', $p->payroll_run_id) }}" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i> Back to {{ $p->run->periodLabel() }}</a>
    @unless ($p->run->isFinalized())<a href="{{ route('payslips.edit', $p) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>@endunless
    <a href="{{ route('payslips.pdf', $p) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Print</button>
    @if ($p->run->isFinalized())
        <form method="POST" action="{{ route('payslips.email', $p) }}" data-confirm="Email this payslip to {{ $p->employee?->email ?: '(no email address)' }}?">@csrf
            <button class="btn btn-sm btn-success" @disabled(! $p->employee?->email)><i class="bi bi-send"></i> {{ $p->emailed_at ? 'Resend' : 'Email' }} to employee</button>
        </form>
    @endif
    <span class="ms-auto small align-self-center">
        @if ($p->emailed_at)<span class="text-success"><i class="bi bi-envelope-check"></i> Emailed to {{ $p->emailed_to }} on {{ $p->emailed_at->format('d M Y H:i') }}</span>
        @elseif ($p->email_error)<span class="text-danger"><i class="bi bi-envelope-exclamation"></i> {{ $p->email_error }}</span>
        @elseif (! $p->employee?->email)<span class="text-muted">No email address on the employee record.</span>@endif
    </span>
</div>
<div class="card"><div class="card-body bg-white">
    @include('payslips._slip', ['p' => $p, 'run' => $p->run, 's' => $s, 'cur' => '₹'])
</div></div>
@endsection
