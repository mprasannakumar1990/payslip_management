<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAllowance extends Model
{
    protected $fillable = ['employee_id', 'allowance_type_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'float'];
    }

    public function type()
    {
        return $this->belongsTo(AllowanceType::class, 'allowance_type_id');
    }
}
