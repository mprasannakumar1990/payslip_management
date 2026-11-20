<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElLedger extends Model
{
    protected $fillable = ['employee_id', 'year', 'days', 'note'];

    protected function casts(): array
    {
        return ['days' => 'float'];
    }
}
