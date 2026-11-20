<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        $casts = ['el_rows' => 'array', 'date_of_exit' => 'date', 'settlement_date' => 'date'];
        foreach (['basic_salary', 'per_day_rate', 'el_days', 'el_amount', 'gratuity_years', 'gratuity_amount', 'bonus_amount',
            'other_amount', 'deduction_amount', 'round_off', 'total_amount'] as $f) {
            $casts[$f] = 'float';
        }

        return $casts;
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
