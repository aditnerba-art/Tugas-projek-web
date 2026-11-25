<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceItem extends Model
{
    protected $fillable = ['service_id', 'sparepart_id', 'jumlah', 'harga_modal', 'harga_satuan', 'subtotal', 'approval_status'];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'harga_modal' => 'integer',
            'harga_satuan' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function sparepart(): BelongsTo
    {
        return $this->belongsTo(Sparepart::class);
    }
}
