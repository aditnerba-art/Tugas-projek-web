<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRate extends Model
{
    protected $fillable = ['name', 'amount', 'active'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'active' => 'boolean',
        ];
    }
}
