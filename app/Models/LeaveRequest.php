<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'actioned_at' => 'datetime',
            'half_day' => 'boolean',
            'days' => 'float',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function actioner()
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }
}
