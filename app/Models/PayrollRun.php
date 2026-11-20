<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PayrollRun extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['finalized_at' => 'datetime', 'working_days' => 'float'];
    }

    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    /** True when the payroll for the month has been finalized; leave / OT for that month must not change. */
    public static function isLocked(int $year, int $month): bool
    {
        return self::where('year', $year)->where('month', $month)->where('status', 'finalized')->exists();
    }

    public function periodLabel(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }
}
