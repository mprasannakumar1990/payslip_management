@extends('layouts.app')
@section('title', 'Settlement — '.$st->employee->name)
@section('back', route('settlements.index'))
@push('styles')
    @include('payslips._style')
@endpush
@section('content')
<div class="d-flex gap-2 mb-3 no-print">
    <a href="{{ route('settlements.index') }}" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i> All settlements</a>
    <a href="{{ route('settlements.pdf', $st) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Print</button>
    <span class="ms-auto small text-muted align-self-center">Created by {{ $st->creator?->name }} on {{ $st->created_at->format('d M Y H:i') }}</span>
</div>
<div class="card"><div class="card-body bg-white">
    @include('settlements._doc', ['st' => $st, 's' => $s, 'cur' => '₹'])
</div></div>
@endsection
