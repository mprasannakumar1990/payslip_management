<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Column layout of the monthly salary sheet ("Salary Workings Oct 2026.xls" and later).
 * Used by the payroll register, its Excel export, the payslip and the salary-sheet import, so they all match.
 */
class SalaryLayout
{
    /** Fixed allowance columns, in sheet order (Attendance Bonus and Birthday Welfare are per-month, not master data). */
    public const ALLOWANCES = ['Spl Allowance', 'Food', 'Transport', 'Food Welfare', 'Mobile', 'Old Increment', 'Increment 2026'];

    /** Former columns (Nov 2025 sheet) that are now added together as "Old Increment". */
    public const RETIRED = ['2022 Increment', 'Pandemic Allowance', 'Increment 2024', 'Increment 2025'];

    /** Column groups of the salary sheet, in order, with their colour key. */
    public const GROUPS = ['info' => 'Information', 'basic' => 'Basic', 'add' => 'Addition', 'gross' => 'Gross Salary',
        'red' => 'Reduction', 'net' => 'Net Salary', 'allow' => 'Allowance', 'total' => 'Total Amount'];

    /** Columns that can never be hidden in the register (the row would be unreadable). */
    public const ALWAYS = ['emp_code', 'name', 'total_amount'];

    /**
     * Every salary-register column in sheet order: [key, group, heading, value(payslip), format, summed in the total row].
     * Allowance columns come from the allowance types (active ones, plus any name used on the given payslips).
     */
    public static function registerColumns(iterable $extraAllowances = []): array
    {
        $m = fn (string $f) => fn ($p) => (float) $p->$f;
        $cols = [
            ['emp_code', 'info', 'Emp ID NO', fn ($p) => $p->emp_code, 'text', false],
            ['name', 'info', 'Name', fn ($p) => $p->name, 'name', false],
            ['designation', 'info', 'Designation', fn ($p) => $p->designation, 'text', false],
            ['grade', 'info', 'Grade', fn ($p) => $p->grade, 'text', false],
            ['uan_no', 'info', 'EPF No', fn ($p) => $p->uan_no, 'text', false],
            ['esic_no', 'info', 'ESIC No', fn ($p) => $p->esic_no, 'text', false],
            ['date_of_joining', 'info', 'Joining Date', fn ($p) => $p->date_of_joining?->format('d-m-Y'), 'text', false],
            ['basic_salary', 'basic', 'Basic', $m('basic_salary'), 'inr0', true],
            ['per_day', 'basic', 'hr (day/8hr)', $m('per_day'), 'inr', false],
            ['present_days', 'basic', 'Present', $m('present_days'), 'qty', false],
            ['absent_days', 'basic', 'Absent', $m('absent_days'), 'absent', false],
            ['working_days', 'basic', 'Total Working', $m('working_days'), 'qty', false],
            ['basic_earned', 'basic', 'Total', $m('basic_earned'), 'inr', true],
            ['second_shift_amount', 'add', '2nd', $m('second_shift_amount'), 'inr', true],
            ['third_shift_amount', 'add', '3rd', $m('third_shift_amount'), 'inr', true],
            ['spl_ot_amount', 'add', 'Spl Ot', $m('spl_ot_amount'), 'inr', true],
            ['ot_hours', 'add', 'OT', $m('ot_hours'), 'qty', true],
            ['ot_amount', 'add', 'OT Pays', $m('ot_amount'), 'ot', true],
            ['manager_special', 'add', 'Manager Special', $m('manager_special'), 'inr', true],
            ['factory_special', 'add', 'Factory Special', $m('factory_special'), 'inr', true],
            ['other_allowance', 'add', 'Other Allowance', $m('other_allowance'), 'inr', true],
            ['general_allowance', 'add', 'General Allowances', $m('general_allowance'), 'inr', true],
            ['total_addition', 'add', 'Total', $m('total_addition'), 'inr-b', true],
            ['gross_salary', 'gross', 'Gross Salary', $m('gross_salary'), 'inr-b', true],
            ['esi', 'red', 'ESI', $m('esi'), 'inr', true],
            ['pf', 'red', 'PF', $m('pf'), 'inr0', true],
            ['tax', 'red', 'TAX', $m('tax'), 'inr0', true],
            ['food_coupon', 'red', 'Food Coupon', $m('food_coupon'), 'inr', true],
            ['advance', 'red', 'Advance', $m('advance'), 'inr', true],
            ['other_deduction', 'red', 'Other Ded.', $m('other_deduction'), 'inr', true],
            ['total_deduction', 'red', 'Total', $m('total_deduction'), 'inr-b', true],
            ['net_salary', 'net', 'Net Salary', $m('net_salary'), 'inr-b', true],
        ];

        $names = \App\Models\AllowanceType::ordered()->where('is_active', true)->pluck('name')->all();
        foreach ($extraAllowances as $n) {
            if (! in_array($n, $names, true)) {
                $names[] = $n;
            }
        }
        $order = array_flip(self::ALLOWANCES);
        usort($names, fn ($a, $b) => ($order[$a] ?? 50) <=> ($order[$b] ?? 50));
        $amount = fn ($n) => fn ($p) => (float) collect($p->allowances ?? [])->where('name', $n)->sum('amount');
        $bonusDone = false;
        foreach ($names as $n) {
            if (! $bonusDone && ($order[$n] ?? 50) > $order['Food Welfare']) {
                $cols[] = ['attendance_bonus', 'allow', 'Attendance Bonus', $m('attendance_bonus'), 'inr', true];
                $bonusDone = true;
            }
            $cols[] = ['al:'.$n, 'allow', $n, $amount($n), 'inr', true];
        }
        if (! $bonusDone) {
            $cols[] = ['attendance_bonus', 'allow', 'Attendance Bonus', $m('attendance_bonus'), 'inr', true];
        }
        $cols[] = ['birthday_welfare', 'allow', 'Birthday Welfare', $m('birthday_welfare'), 'inr', true];
        $cols[] = ['total_allowance', 'allow', 'Total', $m('total_allowance'), 'inr-b', true];
        $cols[] = ['total_amount', 'total', 'Total Amount', $m('total_amount'), 'inr0-b', true];

        return $cols;
    }

    /** Column keys hidden by default in the salary register (Settings → Salary sheet columns). Empty = show every column. */
    public static function hiddenByDefault(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) \App\Models\Setting::get('register_hidden', '')))));
    }

    /** Make the allowance types match the layout and roll the retired increments into "Old Increment". Safe to run again. */
    public static function applyAllowanceTypes(): void
    {
        $now = now();
        $ids = [];
        foreach (self::ALLOWANCES as $i => $name) {
            $id = DB::table('allowance_types')->where('name', $name)->value('id');
            if ($id) {
                DB::table('allowance_types')->where('id', $id)->update(['sort_order' => $i + 1, 'is_active' => true, 'updated_at' => $now]);
            } else {
                $id = DB::table('allowance_types')->insertGetId(['name' => $name, 'sort_order' => $i + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
            $ids[$name] = $id;
        }

        $retired = DB::table('allowance_types')->whereIn('name', self::RETIRED)->pluck('id');
        if ($retired->isEmpty()) {
            return;
        }
        $sums = DB::table('employee_allowances')->whereIn('allowance_type_id', $retired)
            ->selectRaw('employee_id, sum(amount) total')->groupBy('employee_id')->pluck('total', 'employee_id');
        foreach ($sums as $empId => $total) {
            $exists = DB::table('employee_allowances')->where(['employee_id' => $empId, 'allowance_type_id' => $ids['Old Increment']])->exists();
            if ((float) $total != 0 && ! $exists) {
                DB::table('employee_allowances')->insert(['employee_id' => $empId, 'allowance_type_id' => $ids['Old Increment'],
                    'amount' => $total, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        DB::table('allowance_types')->whereIn('id', $retired)->update(['is_active' => false, 'sort_order' => 90, 'updated_at' => $now]);
    }
}
