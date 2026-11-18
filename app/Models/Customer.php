<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['nama', 'no_wa', 'alamat'];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
