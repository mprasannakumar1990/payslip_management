@extends('layouts.app')
@section('title', 'Payroll & Payslips')
@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Run payroll for a month</div>
            <div class="card-body">
                <form method="POST" action="{{ route('payroll.store') }}" data-confirm="Generate payslips for every employee for the selected month from the latest employee, leave and approved OT data?" data-confirm-title="Generate payslips" data-confirm-level="double" data-busy="Generating payslips…">@csrf
                    <div class="mb-3"><label class="form-label">Month *</label><input type="month" name="period" class="form-control" value="{{ old('period', working_period()->format('Y-m')) }}" required></div>
                    <div class="mb-3"><label class="form-label">Working days *</label><input type="number" step="0.5" name="working_days" class="form-control" value="{{ old('working_days', $workingDays) }}" required></div>
                    <button class="btn btn-primary w-100"><i class="bi bi-gear-wide-connected"></i> Generate payslips</button>
                </form>
                <hr>
                <div class="small text-muted">
                    Payslips are calculated from:
                    <ul class="mb-0 ps-3">
                        <li>employee basic, specials, allowances &amp; tax</li>
                        <li><strong>approved</strong> OT / shift hours of the month</li>
                        <li>approved leave → absent days; leave beyond the yearly entitlement and LOP leave reduce present days</li>
                    </ul>
                    You can edit any payslip (advance, birthday welfare, overrides) before finalizing.
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-stack">
                    <thead><tr><th>Month</th><th class="num">Payslips</th><th class="num">Gross</th><th class="num">Net</th><th class="num">Total payable</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($runs as $r)
                        <tr>
                            <td><a href="{{ route('payroll.show', $r) }}" class="fw-semibold">{{ $r->periodLabel() }}</a><div class="small text-muted">{{ num_clean($r->working_days) }} working days</div></td>
                            <td class="num">{{ $r->payslips_count }}</td>
                            <td class="num">{{ inr($r->payslips_sum_gross_salary, 0) }}</td>
                            <td class="num">{{ inr($r->payslips_sum_net_salary, 0) }}</td>
                            <td class="num fw-semibold">{{ inr($r->payslips_sum_total_amount, 0) }}</td>
                            <td>
                                <span class="badge {{ $r->isFinalized() ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($r->status) }}</span>
                                @if ($r->isFinalized())<div class="small text-muted">{{ $r->finalizer?->name }}, {{ $r->finalized_at?->format('d M') }}</div>@endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('payroll.show', $r) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                <a href="{{ route('payroll.pdf', $r) }}" class="btn btn-sm btn-outline-danger" title="All payslips PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No payroll yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body py-2">{{ $runs->links() }}</div>
        </div>
    </div>
</div>
@endsection
