<?php

namespace App\Services;

use App\Models\AllowanceType;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Setting;
use App\Support\SalaryLayout;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Imports a monthly salary sheet ("Salary Workings <Month Year>.xls", Oct 2026 layout) in up to three ways:
 *  1. update the employee master (salary structure, allowances, PF/ESI flags, IDs) from the sheet,
 *  2. register employees that are not in the system yet,
 *  3. save the month's payslips exactly as calculated in the sheet (a draft payroll run you can review and finalize).
 */
class SalarySheetImporter
{
    /** Allowances that depend on attendance in the month: the sheet value is not the monthly master amount when someone was absent. */
    private const ATTENDANCE_BASED = ['Food'];

    public function __construct(private EmployeeMatcher $matcher, private LeaveService $leave)
    {
    }

    /** Match every sheet row to an employee and work out what would change. */
    public function analyse(array $parsed, int $year, int $month): array
    {
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth();
        $employees = Employee::withTrashed()->with('allowances.type')->get();
        $types = AllowanceType::pluck('name')->map(fn ($n) => strtolower($n))->all();
        $rows = [];
        $seen = [];
        foreach ($parsed['rows'] as $r) {
            $r['errors'] = [];
            $r['warnings'] = [];
            $r['changes'] = [];
            // match by Emp ID, then by name (the MD row has no Emp ID)
            $emp = $r['emp_code'] !== '' ? $employees->first(fn ($e) => strcasecmp($e->emp_code, $r['emp_code']) === 0) : null;
            if (! $emp) {
                [$emp, $how] = $this->matcher->match($r['name'], $r['emp_code'] ?: null);
                if ($emp && $r['emp_code'] !== '' && strcasecmp($emp->emp_code, $r['emp_code']) !== 0) {
                    $emp = null; // different Emp ID: a new employee with a similar name
                }
                if ($emp && $how === 'close') {
                    $r['warnings'][] = "Matched by a similar name to {$emp->emp_code} {$emp->name} — check";
                }
            }
            $r['employee_id'] = $emp?->id;
            $r['action'] = $emp ? 'update' : 'create';
            $r['current'] = $emp ? $emp->emp_code.' · '.$emp->name : null;
            if (! $emp && $r['emp_code'] === '') {
                $r['errors'][] = 'No Emp ID and no employee with this name — add the Emp ID in the sheet';
            }
            if ($emp && isset($seen[$emp->id])) {
                $r['errors'][] = "Same employee as sheet line {$seen[$emp->id]}";
            }
            if ($emp) {
                $seen[$emp->id] = $r['line'];
            }

            // joining date: must be a real date before the end of the month
            $doj = ImportService::date($r['date_of_joining'] ?? null);
            if (($r['date_of_joining'] ?? '') !== '' && (! $doj || $doj > $monthEnd->toDateString() || $doj < '1970-01-01')) {
                $r['warnings'][] = 'Joining date “'.$r['date_of_joining'].'” is not valid'.($emp?->date_of_joining ? ' — keeping '.$emp->date_of_joining->format('d-m-Y') : '');
                $doj = null;
            }
            $r['doj'] = $doj ?? $emp?->date_of_joining?->toDateString();
            if (! $emp && ! $r['doj']) {
                $r['errors'][] = 'Joining date is missing for a new employee';
            }

            // the sheet's own arithmetic
            foreach ([
                ['Gross', $r['gross_salary'], $r['basic_earned'] + ($r['total_addition'] ?? 0)],
                ['Net', $r['net_salary'], $r['gross_salary'] - ($r['total_deduction'] ?? 0)],
                ['Total amount', $r['total_amount'] ?? null, $r['net_salary'] + ($r['total_allowance'] ?? 0)],
            ] as [$what, $sheet, $calc]) {
                if ($sheet !== null && abs($sheet - $calc) > 1) {
                    $r['warnings'][] = "$what in the sheet (".num_clean($sheet).') differs from its parts ('.num_clean(round($calc, 2)).')';
                }
            }
            if ($r['present_days'] > ($r['working_days'] ?: 26)) {
                $r['errors'][] = 'Present days are more than the working days';
            }

            // master data the sheet carries
            $r['master'] = array_filter([
                'name' => $emp ? null : $r['name'],
                'designation' => $r['designation'] ?? null, 'grade' => $r['grade'] ?? null,
                'uan_no' => $r['uan_no'] ?? null, 'esic_no' => $r['esic_no'] ?? null,
                'date_of_joining' => $doj,
                'basic_salary' => $r['basic_salary'],
                'manager_special' => $r['manager_special'] ?? 0, 'factory_special' => $r['factory_special'] ?? 0,
                'other_allowance' => $r['other_allowance'] ?? 0, 'general_allowance' => $r['general_allowance'] ?? 0,
                'monthly_tax' => $r['tax'] ?? 0,
                'pf_applicable' => ($r['pf'] ?? 0) > 0, 'esi_applicable' => ($r['esi'] ?? 0) > 0,
            ], fn ($v) => $v !== null && $v !== '');
            if (($r['attendance_bonus'] ?? 0) > 0) {
                $r['master']['attendance_bonus_applicable'] = true;
            }
            $r['master_allowances'] = [];
            foreach ($r['allowances'] as $heading => $amount) {
                $was = $emp ? (float) optional($emp->allowances->first(fn ($a) => strcasecmp((string) $a->type?->name, $heading) === 0))->amount : 0.0;
                // Food is reduced in the sheet for absent days, so it is not the full monthly amount: keep the higher of
                // the stored amount and the sheet value; for a new joiner estimate it from the absent days.
                $absent = (float) ($r['absent_days'] ?? 0);
                $wd = (float) ($r['working_days'] ?: 26);
                if (in_array($heading, self::ATTENDANCE_BASED, true) && $absent > 0 && $amount > 0) {
                    if ($emp && $was > 0) {
                        if ($was > $amount) {
                            $r['warnings'][] = "$heading ".num_clean($amount).' is reduced for absence — keeping the monthly amount '.num_clean($was).' (check)';
                            $amount = $was;
                        }
                    } elseif ($absent < $wd) {
                        $full = round($amount * $wd / ($wd - $absent) / 10) * 10;
                        $r['warnings'][] = "$heading ".num_clean($amount).' is reduced for absence — monthly amount taken as '.num_clean($full).' (check)';
                        $amount = $full;
                    }
                }
                $r['master_allowances'][$heading] = $amount;
                if (! in_array(strtolower($heading), $types, true)) {
                    $r['warnings'][] = "New allowance column “{$heading}” will be added";
                }
            }

            // what changes for an existing employee
            if ($emp) {
                $labels = ['designation' => 'Designation', 'grade' => 'Grade', 'uan_no' => 'EPF', 'esic_no' => 'ESIC', 'date_of_joining' => 'Joined',
                    'basic_salary' => 'Basic', 'manager_special' => 'Manager spl', 'factory_special' => 'Factory spl', 'other_allowance' => 'Other allow.',
                    'general_allowance' => 'General allow.', 'monthly_tax' => 'Tax', 'pf_applicable' => 'PF', 'esi_applicable' => 'ESI'];
                foreach ($labels as $f => $lbl) {
                    if (! array_key_exists($f, $r['master'])) {
                        continue;
                    }
                    $was = $emp->$f instanceof \DateTimeInterface ? $emp->$f->toDateString() : $emp->$f;
                    $new = $r['master'][$f];
                    $differs = is_bool($new) ? (bool) $was !== $new : (is_numeric($new) && is_numeric($was) ? abs((float) $new - (float) $was) > 0.004 : strcasecmp(trim((string) $new), trim((string) $was)) !== 0);
                    if ($differs) {
                        $show = fn ($v) => is_bool($v) ? ($v ? 'yes' : 'no') : ($v === null || $v === '' ? '—' : (is_numeric($v) ? num_clean($v) : $v));
                        $r['changes'][] = "$lbl: ".$show(is_bool($new) ? (bool) $was : $was).' → '.$show($new);
                    }
                }
                foreach ($r['master_allowances'] as $heading => $amount) {
                    $was = (float) optional($emp->allowances->first(fn ($a) => strcasecmp((string) $a->type?->name, $heading) === 0))->amount;
                    if (abs($was - $amount) > 0.004) {
                        $r['changes'][] = "$heading: ".num_clean($was).' → '.num_clean($amount);
                    }
                }
                if ($emp->basic_salary > 0 && abs($r['basic_salary'] - $emp->basic_salary) / $emp->basic_salary > 0.3) {
                    $r['warnings'][] = 'Basic salary changes by more than 30%';
                }
                if ($emp->status !== 'active') {
                    $r['warnings'][] = "Employee is marked “{$emp->status}” in the system";
                }
            }
            $rows[] = $r;
        }

        // on the rolls this month but not in the sheet (left? forgotten?)
        $inSheet = collect($rows)->pluck('employee_id')->filter()->all();
        $missing = Employee::active()
            ->where(fn ($q) => $q->whereNull('date_of_joining')->orWhereDate('date_of_joining', '<=', $monthEnd))
            ->whereNotIn('id', $inSheet)->orderBy('emp_code')->get(['id', 'emp_code', 'name', 'date_of_joining']);

        $run = PayrollRun::where('year', $year)->where('month', $month)->withCount('payslips')->first();

        return ['rows' => $rows, 'missing' => $missing, 'run' => $run];
    }

    /**
     * Apply the analysed rows. $opts: update_master, create_new, save_payroll (bools).
     * Returns counts for the message.
     */
    public function apply(array $rows, int $year, int $month, array $opts): array
    {
        $stats = ['updated' => 0, 'created' => 0, 'payslips' => 0, 'types' => 0];
        $run = PayrollRun::where('year', $year)->where('month', $month)->first();
        if (! empty($opts['save_payroll']) && $run?->isFinalized()) {
            throw new \RuntimeException('Payroll for '.$run->periodLabel().' is finalized. Re-open it first to replace the payslips.');
        }

        DB::transaction(function () use ($rows, $year, $month, $opts, &$stats, &$run) {
            // allowance columns that are new in this sheet become allowance types (e.g. "Increment 2027")
            $types = AllowanceType::all()->keyBy(fn ($t) => strtolower($t->name));
            foreach (collect($rows)->flatMap(fn ($r) => array_keys($r['master_allowances'] ?? []))->unique() as $heading) {
                if (! isset($types[strtolower($heading)])) {
                    $types[strtolower($heading)] = AllowanceType::create(['name' => $heading, 'sort_order' => (int) AllowanceType::max('sort_order') + 1, 'is_active' => true]);
                    $stats['types']++;
                }
            }

            $employees = [];
            foreach ($rows as $i => $r) {
                if ($r['errors']) {
                    continue;
                }
                $emp = $r['employee_id'] ? Employee::withTrashed()->find($r['employee_id']) : null;
                $master = $r['master'];
                if (! $emp) {
                    if (empty($opts['create_new'])) {
                        continue;
                    }
                    $emp = Employee::create($master + ['emp_code' => $r['emp_code'], 'name' => $r['name'], 'status' => 'active',
                        'ot_multiplier' => Setting::num('ot_multiplier') ?: 1.5, 'ot_eligible' => true, 'created_by' => auth()->id()]);
                    $this->saveAllowances($emp, $r['master_allowances'], $types);
                    $stats['created']++;
                } elseif (! empty($opts['update_master'])) {
                    if ($emp->trashed()) {
                        $emp->restore();
                    }
                    $emp->update($master);
                    $this->saveAllowances($emp, $r['master_allowances'], $types);
                    $stats['updated']++;
                }
                $employees[$i] = $emp;
            }

            if (! empty($opts['save_payroll'])) {
                $wd = collect($rows)->pluck('working_days')->filter()->mode()[0] ?? Setting::num('working_days');
                $run ??= PayrollRun::create(['year' => $year, 'month' => $month, 'working_days' => $wd, 'created_by' => auth()->id()]);
                $run->update(['working_days' => $wd]);
                foreach ($employees as $i => $emp) {
                    Payslip::updateOrCreate(['payroll_run_id' => $run->id, 'employee_id' => $emp->id], $this->payslipFrom($rows[$i], $emp, $run));
                    $stats['payslips']++;
                }
            }
        });
        $stats['run'] = $run;

        return $stats;
    }

    private function saveAllowances(Employee $emp, array $amounts, $types): void
    {
        foreach ($amounts as $heading => $amount) {
            $type = $types[strtolower($heading)] ?? null;
            if (! $type) {
                continue;
            }
            if ($amount != 0) {
                EmployeeAllowance::updateOrCreate(['employee_id' => $emp->id, 'allowance_type_id' => $type->id], ['amount' => $amount]);
            } else {
                EmployeeAllowance::where(['employee_id' => $emp->id, 'allowance_type_id' => $type->id])->delete();
            }
        }
    }

    /** The payslip exactly as the sheet calculated it. */
    private function payslipFrom(array $r, Employee $emp, PayrollRun $run): array
    {
        $wd = $r['working_days'] ?: $run->working_days;
        $perDay = $r['per_day'] ?: round($r['basic_salary'] / ($wd ?: 26), 4);
        $hoursPerDay = Setting::num('ot_hours_per_day') ?: 8;
        $mult = $emp->ot_multiplier ?: 1.5;
        $calcOt = round($perDay / $hoursPerDay * $mult * ($r['ot_hours'] ?? 0), 2);
        $allowances = [];
        foreach ($r['allowances'] as $heading => $amount) {
            if ($amount != 0) {
                $allowances[] = ['name' => $heading, 'amount' => $amount];
            }
        }
        $rate2 = Setting::num('second_shift_rate') ?: 5;
        $rate3 = Setting::num('third_shift_rate') ?: 6.875;

        return [
            'emp_code' => $emp->emp_code, 'name' => $emp->name, 'designation' => $r['designation'] ?: $emp->designation,
            'grade' => $r['grade'] ?: $emp->grade, 'ot_group' => $emp->ot_group, 'uan_no' => $emp->uan_no, 'esic_no' => $emp->esic_no,
            'date_of_joining' => $emp->date_of_joining?->toDateString(),
            'basic_salary' => $r['basic_salary'], 'per_day' => $perDay, 'working_days' => $wd,
            'present_days' => $r['present_days'], 'absent_days' => $r['absent_days'] ?? 0,
            'lop_days' => $r['lop_days'] ?? max(0, $wd - $r['present_days']),
            'basic_earned' => $r['basic_earned'],
            'second_shift_hours' => round(($r['second_shift_amount'] ?? 0) / $rate2, 2), 'second_shift_amount' => $r['second_shift_amount'] ?? 0,
            'third_shift_hours' => round(($r['third_shift_amount'] ?? 0) / $rate3, 2), 'third_shift_amount' => $r['third_shift_amount'] ?? 0,
            'spl_ot_amount' => $r['spl_ot_amount'] ?? 0, 'ot_hours' => $r['ot_hours'] ?? 0, 'ot_multiplier' => $mult,
            'ot_amount' => $r['ot_amount'] ?? 0,
            'ot_amount_manual' => abs(($r['ot_amount'] ?? 0) - $calcOt) > 1 ? ($r['ot_amount'] ?? 0) : null,
            'manager_special' => $r['manager_special'] ?? 0, 'factory_special' => $r['factory_special'] ?? 0,
            'other_allowance' => $r['other_allowance'] ?? 0, 'general_allowance' => $r['general_allowance'] ?? 0,
            'total_addition' => $r['total_addition'] ?? round($r['gross_salary'] - $r['basic_earned'], 2),
            'gross_salary' => $r['gross_salary'],
            'esi' => $r['esi'] ?? 0, 'pf' => $r['pf'] ?? 0, 'tax' => $r['tax'] ?? 0, 'food_coupon' => $r['food_coupon'] ?? 0,
            'advance' => $r['advance'] ?? 0, 'other_deduction' => $r['other_deduction'] ?? 0,
            'total_deduction' => $r['total_deduction'] ?? round($r['gross_salary'] - $r['net_salary'], 2),
            'net_salary' => $r['net_salary'],
            'allowances' => $allowances,
            'attendance_bonus' => $r['attendance_bonus'] ?? 0, 'birthday_welfare' => $r['birthday_welfare'] ?? 0,
            'total_allowance' => $r['total_allowance'] ?? 0,
            'total_amount' => $r['total_amount'] ?? round($r['net_salary'] + ($r['total_allowance'] ?? 0), 2),
            'leave_summary' => $this->leave->balances($emp, $run->year, $run->month),
            'remarks' => ($r['remarks'] ?? '') !== '' ? $r['remarks'] : null,
        ];
    }
}
