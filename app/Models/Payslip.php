<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payslip extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public const MONEY = ['basic_salary', 'per_day', 'working_days', 'present_days', 'absent_days', 'lop_days', 'basic_earned',
        'second_shift_hours', 'second_shift_amount', 'third_shift_hours', 'third_shift_amount', 'spl_ot_amount', 'ot_hours',
        'ot_multiplier', 'ot_amount', 'manager_special', 'factory_special', 'other_allowance', 'general_allowance', 'total_addition', 'gross_salary', 'esi', 'pf', 'tax',
        'advance', 'food_coupon', 'other_deduction', 'total_deduction', 'net_salary', 'attendance_bonus', 'birthday_welfare', 'total_allowance',
        'total_amount'];

    protected function casts(): array
    {
        $casts = ['allowances' => 'array', 'leave_summary' => 'array', 'date_of_joining' => 'date', 'emailed_at' => 'datetime'];
        foreach (self::MONEY as $f) {
            $casts[$f] = 'float';
        }

        return $casts;
    }

    public function run()
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }
}
