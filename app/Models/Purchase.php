<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = ['no_pembelian', 'supplier', 'tanggal_pembelian', 'total_biaya', 'keterangan', 'created_by'];

    protected function casts(): array
    {
        return ['tanggal_pembelian' => 'date', 'total_biaya' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseDetail::class);
    }
}
