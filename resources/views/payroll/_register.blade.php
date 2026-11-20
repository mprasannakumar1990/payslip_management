{{-- Salary register. Optional columns appear only when at least one payslip of the month has a value (?all=1 shows every column). --}}
@php
    $showAll = request()->boolean('all');
    $money = fn ($f) => fn ($p) => (float) $p->$f;
    // [group, label, value(p), format, always shown]
    $allCols = [
        ['info', 'Emp ID', fn ($p) => $p->emp_code, 'text', true],
        ['info', 'Name', fn ($p) => $p->name, 'name', true],
        ['info', 'Grade', fn ($p) => $p->grade, 'text', false],
        ['basic', 'Basic', $money('basic_salary'), 'inr0', true],
        ['basic', 'Per day', $money('per_day'), 'inr', true],
        ['basic', 'Present', $money('present_days'), 'qty', true],
        ['basic', 'Absent', $money('absent_days'), 'absent', true],
        ['basic', 'Earned', $money('basic_earned'), 'inr', true],
        ['add', '2nd shift', $money('second_shift_amount'), 'inr', false],
        ['add', '3rd shift', $money('third_shift_amount'), 'inr', false],
        ['add', 'Spl OT', $money('spl_ot_amount'), 'inr', false],
        ['add', 'OT hrs', $money('ot_hours'), 'qty', false],
        ['add', 'OT pays', $money('ot_amount'), 'ot', false],
        ['add', 'Mgr + Fac spl', fn ($p) => (float) $p->manager_special + (float) $p->factory_special, 'inr', false],
        ['gross', 'Salary', $money('gross_salary'), 'inr-b', true],
        ['red', 'ESI', $money('esi'), 'inr', false],
        ['red', 'PF', $money('pf'), 'inr0', false],
        ['red', 'TAX', $money('tax'), 'inr0', false],
        ['red', 'Advance', $money('advance'), 'inr', false],
        ['red', 'Other', $money('other_deduction'), 'inr', false],
        ['net', 'Salary', $money('net_salary'), 'inr-b', true],
        ['allow', 'Att. bonus', $money('attendance_bonus'), 'inr', false],
        ['allow', 'Birthday', $money('birthday_welfare'), 'inr', false],
        ['allow', 'Total', $money('total_allowance'), 'inr', false],
        ['total', 'Amount', $money('total_amount'), 'inr0-b', true],
    ];
    $cols = array_values(array_filter($allCols, fn ($c) => $showAll || $c[4]
        || $payslips->contains(fn ($p) => ! in_array($c[2]($p), [0, 0.0, null, ''], true))));
    $hidden = count($allCols) - count($cols);
    $groups = ['info' => 'Information', 'basic' => 'Basic', 'add' => 'Addition', 'gross' => 'Gross', 'red' => 'Reduction', 'net' => 'Net', 'allow' => 'Allowance', 'total' => 'Total'];
    $span = collect($cols)->countBy(fn ($c) => $c[0]);
    $fmt = fn ($c, $v) => match ($c[3]) {
        'text', 'name' => (string) $v,
        'qty' => $v ? num_clean($v) : '',
        'absent' => num_clean($v),
        'inr0', 'inr0-b' => $v ? inr($v, 0) : '',
        default => $v ? inr($v) : '',
    };
@endphp
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center no-print">
        <span><i class="bi bi-table"></i> Salary register</span>
        <span class="d-flex flex-wrap gap-1 small">
            <span class="reg-key f-add">Addition</span><span class="reg-key f-red">Reduction</span><span class="reg-key f-allow">Allowance</span>
        </span>
        <span class="ms-auto small text-muted">
            @if ($showAll)
                Showing every column · <a href="{{ request()->fullUrlWithQuery(['all' => null]) }}">only filled columns</a>
            @elseif ($hidden)
                {{ $hidden }} empty column(s) hidden · <a href="{{ request()->fullUrlWithQuery(['all' => 1]) }}">show all columns</a>
            @else
                Every column has values
            @endif
        </span>
    </div>
    <div class="table-responsive" style="max-height:75vh">
        <table class="table table-sm table-bordered table-hover table-register reg-dyn mb-0">
            <thead class="sticky-top">
                <tr>
                    @foreach ($groups as $g => $label)
                        @if ($span[$g] ?? 0)<th colspan="{{ $span[$g] }}" class="text-center g-{{ $g }}">{{ $label }}</th>@endif
                    @endforeach
                    <th class="no-print">Email</th><th class="no-print"></th>
                </tr>
                <tr>
                    @foreach ($cols as $c)<th class="{{ in_array($c[3], ['text', 'name']) ? '' : 'num' }} g-{{ $c[0] }}">{{ $c[1] }}</th>@endforeach
                    <th class="no-print"></th><th class="no-print"></th>
                </tr>
            </thead>
            <tbody>
            @foreach ($payslips as $p)
                <tr>
                    @foreach ($cols as $c)
                        @php($v = $c[2]($p))
                        @if ($c[3] === 'name')
                            <td class="text-truncate" style="max-width:170px" title="{{ $p->designation }}">{{ $p->name }} @if($p->remarks)<i class="bi bi-chat-left-text text-muted" title="{{ $p->remarks }}"></i>@endif</td>
                        @elseif ($c[3] === 'text')
                            <td>{{ $v }}</td>
                        @else
                            <td class="num {{ str_ends_with($c[3], '-b') ? 'fw-semibold' : '' }} {{ $v && in_array($c[0], ['add', 'red', 'allow']) ? 'filled f-'.$c[0] : '' }} {{ $c[3] === 'absent' && $p->lop_days > 0 ? 'text-danger fw-semibold' : '' }} {{ $c[3] === 'ot' && $p->ot_amount_manual !== null ? 'text-info' : '' }}"
                                @if ($c[3] === 'absent') title="LOP {{ num_clean($p->lop_days) }}" @endif>{{ $fmt($c, $v) }}</td>
                        @endif
                    @endforeach
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
            <tr class="total-row">
                @foreach ($cols as $i => $c)
                    @if ($i === 0)<td>Total</td>
                    @elseif (in_array($c[3], ['text', 'name']) || in_array($c[1], ['Per day', 'Present', 'Absent']))<td></td>
                    @else<td class="num">{{ $fmt($c, $payslips->sum(fn ($p) => $c[2]($p))) }}</td>@endif
                @endforeach
                <td class="no-print"></td><td class="no-print"></td>
            </tr>
            </tbody>
        </table>
    </div>
</div>
