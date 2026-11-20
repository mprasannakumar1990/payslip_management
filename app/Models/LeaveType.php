<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = ['code', 'name', 'yearly_quota', 'is_paid', 'sort_order'];

    protected function casts(): array
    {
        return ['is_paid' => 'boolean', 'yearly_quota' => 'float'];
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }
}
