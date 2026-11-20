@extends('layouts.app')
@section('title', 'EL & Gratuity Settlements')
@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        <form class="d-flex gap-2 flex-grow-1 auto-filter" method="GET">
            <select name="employee_id" class="form-select form-select-sm select-search" style="min-width:240px"><option value="">All employees</option>@foreach ($employees as $e)<option value="{{ $e->id }}" @selected(request('employee_id') == $e->id)>{{ $e->emp_code }} - {{ $e->name }}</option>@endforeach</select>
            
        </form>
        <a href="{{ route('settlements.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> New settlement</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-stack">
            <thead><tr><th>Date</th><th>Employee</th><th>Exit date</th><th class="num">EL days</th><th class="num">EL amount</th><th class="num">Gratuity</th><th class="num">Bonus / other</th><th class="num">Total</th><th></th></tr></thead>
            @forelse ($items as $st)
                <tr>
                    <td>{{ $st->settlement_date->format('d M Y') }}</td>
                    <td>{{ $st->employee->emp_code }} — {{ $st->employee->name }}</td>
                    <td>{{ $st->date_of_exit?->format('d M Y') }}</td>
                    <td class="num">{{ num_clean($st->el_days) }}</td>
                    <td class="num">{{ inr($st->el_amount, 0) }}</td>
                    <td class="num">{{ inr($st->gratuity_amount, 0) }}</td>
                    <td class="num">{{ inr($st->bonus_amount + $st->other_amount, 0) }}</td>
                    <td class="num fw-bold">₹ {{ inr($st->total_amount, 0) }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('settlements.show', $st) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('settlements.pdf', $st) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i></a>
                        <form method="POST" action="{{ route('settlements.destroy', $st) }}" class="d-inline" data-confirm="Delete this settlement?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No settlements yet.</td></tr>
            @endforelse
        </table>
    </div>
    <div class="card-body py-2">{{ $items->links() }}</div>
</div>
@endsection
