{{-- Shared payslip body used for HTML view and PDF. Expects $p (Payslip), $run (PayrollRun), $s (settings), $cur (currency prefix) --}}
@php
    $cur = $cur ?? 'Rs.';
    $earnings = array_filter([
        'BASIC SALARY' => $p->basic_earned,
        'MANAGER SPL' => $p->manager_special,
        'FACTORY SPL' => $p->factory_special,
        'OTHER ALLOWANCE' => $p->other_allowance,
        'GENERAL ALLOWANCES' => $p->general_allowance,
        'OT PAYS ('.num_clean($p->ot_hours).' hrs)' => $p->ot_amount,
        '2ND SHIFT ('.num_clean($p->second_shift_hours).' hrs)' => $p->second_shift_amount,
        '3RD SHIFT ('.num_clean($p->third_shift_hours).' hrs)' => $p->third_shift_amount,
        'SPL OT' => $p->spl_ot_amount,
    ], fn ($v, $k) => $v != 0 || $k === 'BASIC SALARY', ARRAY_FILTER_USE_BOTH);
    $deductions = array_filter([
        'EPF' => $p->pf, 'ESIC' => $p->esi, 'TAX' => $p->tax, 'FOOD COUPON' => $p->food_coupon, 'ADVANCE' => $p->advance, 'MISC' => $p->other_deduction,
    ], fn ($v) => $v != 0); // only deductions that apply to this employee
    $allowances = collect($p->allowances ?? [])->filter(fn ($a) => (float) ($a['amount'] ?? 0) != 0)->pluck('amount', 'name')->all();
    // salary-sheet order: Attendance bonus after Food Welfare, the rest in layout order
    $order = array_flip(\App\Support\SalaryLayout::ALLOWANCES);
    uksort($allowances, fn ($a, $b) => ($order[$a] ?? 50) <=> ($order[$b] ?? 50));
    if ($p->attendance_bonus) {
        $pos = array_search('Food Welfare', array_keys($allowances), true);
        $pos = $pos === false ? count(array_filter(array_keys($allowances), fn ($k) => ($order[$k] ?? 50) <= $order['Food Welfare'])) : $pos + 1;
        $allowances = array_slice($allowances, 0, $pos, true) + ['Attendance' => $p->attendance_bonus] + array_slice($allowances, $pos, null, true);
    }
    if ($p->birthday_welfare) { $allowances['Birthday Welfare'] = $p->birthday_welfare; }
    $rows = max(count($earnings), count($deductions));
    $lv = $p->leave_summary ?? null;
@endphp
<div class="slip">
    <table class="slip-head">
        <tr><td class="form-t">FORM - T<br><span>( See Sub-Rule (6) of Rule II )</span></td></tr>
        <tr><td class="company">{{ $s['company_name'] }}</td></tr>
        <tr><td class="address">{{ $s['company_address'] }}</td></tr>
        <tr><td class="title">PAYSLIP FOR THE MONTH OF {{ strtoupper($run->periodLabel()) }}</td></tr>
    </table>

    <table class="grid info">
        <tr><th>Employee Name</th><td>{{ $p->name }}</td><th>Designation</th><td>{{ $p->designation }}</td></tr>
        <tr><th>Employee ID</th><td>{{ $p->emp_code }}</td><th>Employee ESI ID</th><td>{{ $p->esic_no ?: '—' }}</td></tr>
        <tr><th>Joining Date</th><td>{{ $p->date_of_joining?->format('d-m-Y') }}</td><th>Employee EPF ID</th><td>{{ $p->uan_no ?: '—' }}</td></tr>
        <tr><th>Working / Present</th><td>{{ num_clean($p->working_days) }} / {{ num_clean($p->present_days) }} days</td><th>Leave / LOP</th><td>{{ num_clean($p->absent_days) }} / {{ num_clean($p->lop_days) }} days</td></tr>
    </table>

    <table class="grid money">
        <tr class="section"><th colspan="2">Earnings</th><th colspan="2">Deductions</th></tr>
        @for ($i = 0; $i < $rows; $i++)
            @php($ek = array_keys($earnings)[$i] ?? null)
            @php($dk = array_keys($deductions)[$i] ?? null)
            <tr>
                <td>{{ $ek }}</td><td class="amt">{{ $ek ? inr($earnings[$ek]) : '' }}</td>
                <td>{{ $dk }}</td><td class="amt">{{ $dk ? inr($deductions[$dk]) : '' }}</td>
            </tr>
        @endfor
        <tr class="total"><td>Gross Salary</td><td class="amt">{{ inr($p->gross_salary) }}</td><td>Total Deductions</td><td class="amt">{{ inr($p->total_deduction) }}</td></tr>
    </table>

    <table class="grid money">
        <tr class="section"><th colspan="2">Allowance</th><th colspan="2">Summary</th></tr>
        @php($ak = array_keys($allowances))
        @php($summary = ['Gross Salary' => $p->gross_salary, 'Deductions' => -$p->total_deduction, 'Net Salary' => $p->net_salary, 'Allowance' => $p->total_allowance])
        @php($sk = array_keys($summary))
        @for ($i = 0; $i < max(count($ak), count($sk)); $i++)
            <tr>
                <td>{{ $ak[$i] ?? '' }}</td><td class="amt">{{ isset($ak[$i]) ? inr($allowances[$ak[$i]]) : '' }}</td>
                <td>{{ $sk[$i] ?? '' }}</td><td class="amt">{{ isset($sk[$i]) ? inr($summary[$sk[$i]]) : '' }}</td>
            </tr>
        @endfor
        <tr class="total"><td>Total</td><td class="amt">{{ inr($p->total_allowance) }}</td><td>Net Salary + Allowances</td><td class="amt">{{ inr($p->total_amount) }}</td></tr>
    </table>

    <table class="grid net">
        <tr><th>Net Pay for the Month (Total Earnings − Deductions + Allowances)</th><td class="amt">{{ $cur }} {{ inr($p->total_amount) }}</td></tr>
        <tr><td colspan="2" class="words">Rupees {{ amount_in_words($p->total_amount) }} Only</td></tr>
    </table>

    @if ($lv)
        <table class="grid leave">
            <tr class="section"><th colspan="{{ count($lv['types']) * 2 + 3 }}">Leave ({{ 'Jan ~ '.month_name($run->month, true).' '.$run->year }})</th></tr>
            <tr>
                @foreach ($lv['types'] as $t)<th colspan="2">{{ $t['name'] }}</th>@endforeach
                <th colspan="2">Leave</th><th>Leave Balance</th>
            </tr>
            <tr>
                @foreach ($lv['types'] as $t)<td>Total</td><td>Balance</td>@endforeach
                <td>Total</td><td>Usage</td><td></td>
            </tr>
            <tr>
                @foreach ($lv['types'] as $t)<td>{{ num_clean($t['quota']) }}</td><td>{{ num_clean($t['balance']) }}</td>@endforeach
                <td>{{ num_clean($lv['pool']) }}</td><td>{{ num_clean($lv['used']) }}</td><td><strong>{{ num_clean($lv['balance']) }}</strong></td>
            </tr>
        </table>
    @endif

    @if ($p->remarks)<p class="remarks">Remarks: {{ $p->remarks }}</p>@endif
    <p class="foot">This is a system generated payslip and does not require signature.</p>
    @if ($s['payslip_footer'])<p class="foot quote">“{{ $s['payslip_footer'] }}”</p>@endif
</div>
