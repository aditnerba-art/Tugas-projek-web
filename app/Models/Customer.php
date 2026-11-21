<?php

namespace App\Models;

use App\Services\WhatsApp\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = ['nama', 'no_wa', 'alamat'];

    public function setNoWaAttribute(string $value): void
    {
        $this->attributes['no_wa'] = PhoneNumber::normalize($value);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
