<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected function casts(): array
    {
        return [
            'date_of_joining' => 'date',
            'date_of_exit' => 'date',
            'date_of_birth' => 'date',
            'pf_applicable' => 'boolean',
            'esi_applicable' => 'boolean',
            'ot_eligible' => 'boolean',
            'attendance_bonus_applicable' => 'boolean',
            'basic_salary' => 'float',
            'manager_special' => 'float',
            'factory_special' => 'float',
            'monthly_tax' => 'float',
            'ot_multiplier' => 'float',
        ];
    }

    public function allowances()
    {
        return $this->hasMany(EmployeeAllowance::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function otEntries()
    {
        return $this->hasMany(OtEntry::class);
    }

    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }

    public function elLedgers()
    {
        return $this->hasMany(ElLedger::class);
    }

    public function settlements()
    {
        return $this->hasMany(Settlement::class);
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function perDay(): float
    {
        $wd = Setting::num('working_days') ?: 26;

        return $this->basic_salary / $wd;
    }

    public function serviceYears($until = null): float
    {
        if (! $this->date_of_joining) {
            return 0;
        }
        $until = $until ? \Illuminate\Support\Carbon::parse($until) : ($this->date_of_exit ?? now());

        return round($this->date_of_joining->floatDiffInYears($until), 1);
    }

    public function label(): string
    {
        return $this->emp_code.' - '.$this->name;
    }
}
