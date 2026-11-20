<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtEntry extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'second_shift_hours' => 'float',
            'third_shift_hours' => 'float',
            'normal_ot_hours' => 'float',
            'spl_ot_hours' => 'float',
            'approved_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function totalOt(): float
    {
        return $this->normal_ot_hours + $this->spl_ot_hours;
    }
}
