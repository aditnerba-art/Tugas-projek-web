<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'no_nota', 'customer_id', 'teknisi_id', 'jenis_unit', 'merek_tipe', 'no_seri',
        'keluhan', 'kelengkapan', 'status', 'biaya_jasa', 'total_biaya',
        'status_pembayaran', 'metode_pembayaran', 'dp_dibayar', 'nominal_dibayar', 'kembalian',
    ];

    protected function casts(): array
    {
        return [
            'biaya_jasa' => 'decimal:2',
            'total_biaya' => 'decimal:2',
            'dp_dibayar' => 'decimal:2',
            'nominal_dibayar' => 'decimal:2',
            'kembalian' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teknisi_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceItem::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ServiceLog::class);
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(ServiceAccessToken::class);
    }
}
