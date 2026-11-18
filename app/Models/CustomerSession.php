<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSession extends Model
{
    protected $primaryKey = 'session_hash';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['session_hash', 'access_token_id', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(ServiceAccessToken::class, 'access_token_id');
    }
}
