<?php

namespace App\Services;

use App\Models\AllowanceType;
use App\Models\Employee;
use App\Models\OtEntry;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Salary rules reproduced from "Salary Workings Nov 2025.xls":
 *  per day          = Basic / working days (26)
 *  basic earned     = per day x present (paid) days
 *  2nd / 3rd shift  = shift hours x shift rate (Rs 5 / Rs 6.875 per hour)
 *  OT pay           = per day / 8 x OT multiplier (1.5, driver 1.2) x OT hours
 *  gross            = basic earned + shift + OT + Spl OT + Manager special + Factory special + Other + General allowance
 *  ESI              = gross x 0.75 %            (when applicable)
 *  PF               = 12 % of basic earned, max Rs 3,000 (setting pf_cap; was 1,800 in 2025)
 *  net              = gross - (ESI + PF + TAX + food coupon + advance + other deductions)
 *  attendance bonus = one day's pay when there was no absence
 *  total payable    = net + allowances (rounded to the rupee)
 */
class PayrollService
{
    public function __construct(private LeaveService $leave)
    {
    }

    /** Pure calculation. $p holds the inputs; returns them along with every computed field. */
    public function calculate(array $p): array
    {
        $wd = (float) ($p['working_days'] ?: 26);
        $perDay = $p['basic_salary'] / $wd;
        $hoursPerDay = Setting::num('ot_hours_per_day') ?: 8;

        $p['per_day'] = round($perDay, 4);
        $p['basic_earned'] = round($perDay * $p['present_days'], 2);
        $p['second_shift_amount'] = round($p['second_shift_hours'] * Setting::num('second_shift_rate'), 2);
        $p['third_shift_amount'] = round($p['third_shift_hours'] * Setting::num('third_shift_rate'), 2);
        $p['ot_amount_manual'] = isset($p['ot_amount_manual']) && $p['ot_amount_manual'] !== '' ? (float) $p['ot_amount_manual'] : null;
        $p['ot_amount'] = $p['ot_amount_manual'] ?? round($perDay / $hoursPerDay * $p['ot_multiplier'] * $p['ot_hours'], 2);
        $p['total_addition'] = round($p['second_shift_amount'] + $p['third_shift_amount'] + $p['spl_ot_amount']
            + $p['ot_amount'] + $p['manager_special'] + $p['factory_special'] + ($p['other_allowance'] ?? 0) + ($p['general_allowance'] ?? 0), 2);
        $p['gross_salary'] = round($p['basic_earned'] + $p['total_addition'], 2);

        $p['esi'] = ! empty($p['esi_applicable']) ? round($p['gross_salary'] * Setting::num('esi_percent') / 100, 2) : 0;
        $p['pf'] = ! empty($p['pf_applicable'])
            ? min(round($p['basic_earned'] * Setting::num('pf_percent') / 100), Setting::num('pf_cap') ?: PHP_INT_MAX) : 0;
        $p['food_coupon'] = (float) ($p['food_coupon'] ?? 0);
        $p['total_deduction'] = round($p['esi'] + $p['pf'] + $p['tax'] + $p['advance'] + $p['food_coupon'] + $p['other_deduction'], 2);
        $p['net_salary'] = round($p['gross_salary'] - $p['total_deduction'], 2);

        if (! array_key_exists('attendance_bonus', $p) || $p['attendance_bonus'] === null) {
            $p['attendance_bonus'] = (! empty($p['attendance_bonus_applicable']) && $p['absent_days'] <= 0) ? round($perDay, 2) : 0;
        }
        $allowanceTotal = array_sum(array_map(fn ($a) => (float) $a['amount'], $p['allowances'] ?? []));
        $p['total_allowance'] = round($allowanceTotal + $p['attendance_bonus'] + $p['birthday_welfare'], 2);
        $p['total_amount'] = round($p['net_salary'] + $p['total_allowance']);

        unset($p['esi_applicable'], $p['pf_applicable'], $p['attendance_bonus_applicable']);

        return $p;
    }

    /** Build the calculation inputs for one employee and month from master data, OT entries and leave records. */
    public function inputsFor(Employee $emp, PayrollRun $run): array
    {
        $ot = OtEntry::where('employee_id', $emp->id)->where('year', $run->year)->where('month', $run->month)
            ->where('status', 'approved')->first();
        $usage = $this->leave->monthlyUsage($emp, $run->year);
        $lop = $this->leave->lopForMonth($emp, $run->year, $run->month, $usage);
        $balances = $this->leave->balances($emp, $run->year, $run->month, $usage);

        $wd = $run->working_days;
        // pro-rate the month for joiners / leavers
        $monthStart = Carbon::create($run->year, $run->month, 1);
        $notEmployedDays = 0;
        if ($emp->date_of_joining && $emp->date_of_joining->gt($monthStart)) {
            $notEmployedDays += $this->leave->countDays($monthStart, $emp->date_of_joining->copy()->subDay());
        }
        if ($emp->date_of_exit && $emp->date_of_exit->lt($monthStart->copy()->endOfMonth())) {
            $notEmployedDays += $this->leave->countDays($emp->date_of_exit->copy()->addDay(), $monthStart->copy()->endOfMonth());
        }
        $present = max(0, $wd - $lop['lop'] - $notEmployedDays);

        $allowances = [];
        $emp->loadMissing('allowances.type');
        foreach (AllowanceType::ordered()->where('is_active', true)->get() as $t) {
            $amount = (float) optional($emp->allowances->firstWhere('allowance_type_id', $t->id))->amount;
            if ($amount != 0) {
                $allowances[] = ['name' => $t->name, 'amount' => $amount];
            }
        }

        $otEligible = $emp->ot_eligible;

        return [
            'employee_id' => $emp->id,
            'emp_code' => $emp->emp_code,
            'name' => $emp->name,
            'designation' => $emp->designation,
            'grade' => $emp->grade,
            'ot_group' => $emp->ot_group,
            'uan_no' => $emp->uan_no,
            'esic_no' => $emp->esic_no,
            'date_of_joining' => $emp->date_of_joining?->toDateString(),
            'basic_salary' => $emp->basic_salary,
            'working_days' => $wd,
            'present_days' => $present,
            'absent_days' => $lop['absent'],
            'lop_days' => $lop['lop'] + $notEmployedDays,
            'second_shift_hours' => $otEligible ? ($ot->second_shift_hours ?? 0) : 0,
            'third_shift_hours' => $otEligible ? ($ot->third_shift_hours ?? 0) : 0,
            'ot_hours' => $otEligible && $ot ? $ot->totalOt() : 0,
            'ot_multiplier' => $emp->ot_multiplier ?: Setting::num('ot_multiplier'),
            'spl_ot_amount' => 0,
            'manager_special' => $emp->manager_special,
            'factory_special' => $emp->factory_special,
            'other_allowance' => $emp->other_allowance,
            'general_allowance' => $emp->general_allowance,
            'tax' => $emp->monthly_tax,
            'advance' => 0,
            'food_coupon' => 0,
            'other_deduction' => 0,
            'birthday_welfare' => 0,
            'attendance_bonus' => null,
            'attendance_bonus_applicable' => $emp->attendance_bonus_applicable,
            'esi_applicable' => $emp->esi_applicable,
            'pf_applicable' => $emp->pf_applicable,
            'allowances' => $allowances,
            'leave_summary' => $balances,
        ];
    }

    /** Employees who were on the rolls during the run's month. */
    public function eligibleEmployees(PayrollRun $run)
    {
        $start = Carbon::create($run->year, $run->month, 1);
        $end = $start->copy()->endOfMonth();

        return Employee::query()
            ->whereIn('status', ['active', 'exited'])
            ->where(fn ($q) => $q->whereNull('date_of_joining')->orWhereDate('date_of_joining', '<=', $end))
            ->where(fn ($q) => $q->whereNull('date_of_exit')->orWhereDate('date_of_exit', '>=', $start))
            ->orderBy('emp_code')
            ->get();
    }

    /**
     * (Re)generate payslips for a draft run. Existing payslips keep their manual entries
     * (advance, other deductions, spl OT amount, birthday welfare, remarks) unless $reset is true.
     */
    public function generate(PayrollRun $run, bool $reset = false): int
    {
        abort_if($run->isFinalized(), 422, 'Payroll is finalized.');
        $count = 0;
        DB::transaction(function () use ($run, $reset, &$count) {
            $existing = $run->payslips()->get()->keyBy('employee_id');
            foreach ($this->eligibleEmployees($run) as $emp) {
                $in = $this->inputsFor($emp, $run);
                if (! $reset && ($old = $existing[$emp->id] ?? null)) {
                    foreach (['advance', 'food_coupon', 'other_deduction', 'spl_ot_amount', 'birthday_welfare', 'ot_amount_manual'] as $f) {
                        $in[$f] = $old->$f;
                    }
                    $in['remarks'] = $old->remarks;
                }
                $data = $this->calculate($in);
                Payslip::updateOrCreate(['payroll_run_id' => $run->id, 'employee_id' => $emp->id], $data);
                $count++;
            }
        });

        return $count;
    }

    /** Recalculate one payslip after the admin edits its inputs. */
    public function recalculate(Payslip $slip, array $overrides): Payslip
    {
        $emp = $slip->employee;
        $in = $slip->only(['working_days', 'present_days', 'absent_days', 'lop_days', 'basic_salary', 'second_shift_hours',
            'third_shift_hours', 'ot_hours', 'ot_multiplier', 'ot_amount_manual', 'spl_ot_amount', 'manager_special', 'factory_special', 'other_allowance', 'general_allowance', 'tax',
            'advance', 'food_coupon', 'other_deduction', 'birthday_welfare', 'attendance_bonus', 'allowances', 'remarks']);
        $in = array_merge($in, $overrides);
        $in['esi_applicable'] = $overrides['esi_applicable'] ?? $slip->esi > 0 || $emp?->esi_applicable;
        $in['pf_applicable'] = $overrides['pf_applicable'] ?? $slip->pf > 0 || $emp?->pf_applicable;
        $slip->fill($this->calculate($in))->save();

        return $slip;
    }
}
