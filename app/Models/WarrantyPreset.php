<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarrantyPreset extends Model
{
    protected $fillable = ['name', 'days', 'active'];

    protected function casts(): array
    {
        return [
            'days' => 'integer',
            'active' => 'boolean',
        ];
    }
}
