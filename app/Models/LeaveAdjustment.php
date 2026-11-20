<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveAdjustment extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['days' => 'float'];
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
