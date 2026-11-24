<?php

namespace App\Support;

use App\Models\PayrollRun;
use Illuminate\Support\Collection;

/**
 * Salary register rows in the column layout of the monthly salary sheet ("Salary Workings Oct 2026.xls"),
 * so the export looks like the sheet the office already uses and can be uploaded again with the salary-sheet import.
 * Columns the sheet does not have (Advance, Other Ded., LOP, extra allowances) appear only when a payslip uses them.
 */
class SalaryRegisterExport
{
    /** @return array{0: array, 1: Collection} header and rows (with a TOTAL row) */
    public static function build(PayrollRun $run): array
    {
        $slips = $run->payslips()->orderBy('emp_code')->get();
        $used = fn (string $f) => $slips->contains(fn ($s) => (float) $s->$f != 0);
        $extraAllow = $slips->flatMap(fn ($s) => collect($s->allowances ?? [])->pluck('name'))->unique()
            ->reject(fn ($n) => in_array($n, SalaryLayout::ALLOWANCES, true))->values()->all();

        // [heading, value(slip), total?]
        $cols = [
            ['Emp ID NO', fn ($s) => $s->emp_code, false], ['Name', fn ($s) => $s->name, false],
            ['Designation', fn ($s) => $s->designation, false], ['Grade', fn ($s) => $s->grade, false],
            ['EPF No', fn ($s) => $s->uan_no, false], ['ESIC No', fn ($s) => $s->esic_no, false],
            ['Joining Date', fn ($s) => $s->date_of_joining?->format('d-m-Y'), false],
            ['Basic', fn ($s) => $s->basic_salary, true], ['hr (day/8hr)', fn ($s) => round($s->per_day, 2), false],
            ['Present', fn ($s) => $s->present_days, false], ['Absent', fn ($s) => $s->absent_days, false],
        ];
        if ($used('lop_days')) {
            $cols[] = ['LOP', fn ($s) => $s->lop_days, false];
        }
        array_push($cols,
            ['Total Working', fn ($s) => $s->working_days, false], ['Total', fn ($s) => $s->basic_earned, true],
            ['2nd', fn ($s) => $s->second_shift_amount, true], ['3rd', fn ($s) => $s->third_shift_amount, true],
            ['Spl Ot', fn ($s) => $s->spl_ot_amount, true], ['OT', fn ($s) => $s->ot_hours, true], ['OT Pays', fn ($s) => $s->ot_amount, true],
            ['Manager Special', fn ($s) => $s->manager_special, true], ['Factory Special', fn ($s) => $s->factory_special, true],
            ['Other Allowance', fn ($s) => $s->other_allowance, true], ['General Allowances', fn ($s) => $s->general_allowance, true],
            ['Total Addition', fn ($s) => $s->total_addition, true], ['Gross Salary', fn ($s) => $s->gross_salary, true],
            ['ESI', fn ($s) => $s->esi, true], ['PF', fn ($s) => $s->pf, true], ['TAX', fn ($s) => $s->tax, true],
            ['Food Coupon', fn ($s) => $s->food_coupon, true],
        );
        if ($used('advance')) {
            $cols[] = ['Advance', fn ($s) => $s->advance, true];
        }
        if ($used('other_deduction')) {
            $cols[] = ['Other Ded.', fn ($s) => $s->other_deduction, true];
        }
        $cols[] = ['Total Reduction', fn ($s) => $s->total_deduction, true];
        $cols[] = ['Net Salary', fn ($s) => $s->net_salary, true];
        $allow = fn ($n) => fn ($s) => (float) collect($s->allowances ?? [])->where('name', $n)->sum('amount');
        foreach (SalaryLayout::ALLOWANCES as $n) {
            $cols[] = [$n, $allow($n), true];
            if ($n === 'Food Welfare') {
                $cols[] = ['Attendance Bonus', fn ($s) => $s->attendance_bonus, true];
            }
        }
        foreach ($extraAllow as $n) {
            $cols[] = [$n, $allow($n), true];
        }
        array_push($cols,
            ['Birthday Welfare', fn ($s) => $s->birthday_welfare, true], ['Total Allowance', fn ($s) => $s->total_allowance, true],
            ['Total Amount', fn ($s) => $s->total_amount, true], ['Remarks', fn ($s) => $s->remarks, false],
        );

        $rows = $slips->map(fn ($s) => array_map(fn ($c) => $c[1]($s), $cols));
        $total = array_map(fn ($c) => $c[2] ? round($slips->sum(fn ($s) => (float) $c[1]($s)), 2) : '', $cols);
        $total[1] = 'TOTAL';
        $rows->push($total);

        return [array_column($cols, 0), $rows];
    }
}
