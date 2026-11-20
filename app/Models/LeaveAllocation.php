<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One employee's yearly entitlement for one leave type. */
class LeaveAllocation extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['days' => 'float', 'is_manual' => 'boolean'];
    }

    protected static function booted(): void
    {
        // LeaveService is a singleton that caches allocations; keep it in step with every change
        $flush = fn () => app(\App\Services\LeaveService::class)->flushAllocations();
        static::saved($flush);
        static::deleted($flush);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
}
