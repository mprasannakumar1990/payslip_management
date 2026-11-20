{{-- Settlement statement, same layout as the per-employee sheets in "El Calculation 2025.xlsx". Expects $st, $s, $cur --}}
@php($cur = $cur ?? 'Rs.')
<div class="slip">
    <table class="slip-head">
        <tr><td class="company">{{ $s['company_name'] }}</td></tr>
        <tr><td class="address">{{ $s['company_address'] }}</td></tr>
        <tr><td class="title">Earn Leave Final Settlement Calculation for the Period of {{ $st->employee->date_of_joining?->year }} to {{ ($st->date_of_exit ?? $st->settlement_date)->year }}</td></tr>
    </table>
    <table class="grid info">
        <tr><th>Employee Name</th><td>{{ $st->employee->name }} ({{ $st->employee->emp_code }})</td><th>Date of Joining</th><td>{{ $st->employee->date_of_joining?->format('jS M Y') }}</td></tr>
        <tr><th>Designation</th><td>{{ $st->employee->designation }}</td><th>Date of Exit</th><td>{{ $st->date_of_exit?->format('jS M Y') ?? '—' }}</td></tr>
    </table>
    <table class="grid money">
        <tr class="section"><th>Year</th><th>Period</th><th style="text-align:right">No. of Days Leave Balance</th><th style="text-align:right">Per Day</th><th style="text-align:right">Total Amount</th></tr>
        @foreach ($st->el_rows ?? [] as $r)
            <tr><td>{{ $r['year'] }}</td><td>{{ $r['period'] }}</td><td class="amt">{{ num_clean($r['days']) }}</td><td class="amt">{{ inr($st->per_day_rate, 0) }}</td><td class="amt">{{ inr($r['amount']) }}</td></tr>
        @endforeach
        <tr class="total"><td colspan="2">EL Total Amount</td><td class="amt">{{ num_clean($st->el_days) }}</td><td></td><td class="amt">{{ inr($st->el_amount) }}</td></tr>
    </table>
    <table class="grid money">
        <tr><td>Earn Leave amount</td><td class="amt">{{ inr($st->el_amount) }}</td></tr>
        <tr><td>Gratuity ({{ num_clean($st->gratuity_years) }} years: Basic {{ inr($st->basic_salary, 0) }} ÷ {{ num_clean($s['gratuity_divisor']) }} × {{ num_clean($s['gratuity_days']) }} × {{ num_clean($st->gratuity_years) }})</td><td class="amt">{{ inr($st->gratuity_amount) }}</td></tr>
        @if ($st->bonus_amount)<tr><td>Bonus</td><td class="amt">{{ inr($st->bonus_amount) }}</td></tr>@endif
        @if ($st->other_amount)<tr><td>Other additions</td><td class="amt">{{ inr($st->other_amount) }}</td></tr>@endif
        @if ($st->deduction_amount)<tr><td>Less: deductions</td><td class="amt">-{{ inr($st->deduction_amount) }}</td></tr>@endif
        @if ($st->round_off)<tr><td>Round off</td><td class="amt">{{ inr($st->round_off) }}</td></tr>@endif
    </table>
    <table class="grid net">
        <tr><th>Earn Leave + Gratuity Total Amount</th><td class="amt">{{ $cur }} {{ inr($st->total_amount) }}</td></tr>
        <tr><td colspan="2" class="words">( Rupees {{ amount_in_words($st->total_amount) }} Only )</td></tr>
    </table>
    @if ($st->remarks)<p class="remarks">Remarks: {{ $st->remarks }}</p>@endif
    <table style="margin-top:50px"><tr><td style="text-align:center;width:50%">Prepared By</td><td style="text-align:center">MD</td></tr></table>
</div>
