<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseDetail extends Model
{
    protected $fillable = ['purchase_id', 'sparepart_id', 'jumlah', 'harga_beli_satuan', 'subtotal'];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'harga_beli_satuan' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function sparepart(): BelongsTo
    {
        return $this->belongsTo(Sparepart::class);
    }
}
