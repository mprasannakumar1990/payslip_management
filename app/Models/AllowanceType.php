<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AllowanceType extends Model
{
    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }
}
