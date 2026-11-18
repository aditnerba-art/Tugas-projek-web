<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sparepart extends Model
{
    protected $fillable = ['kode_barang', 'nama_barang', 'stok', 'harga_beli', 'harga_jual'];

    protected function casts(): array
    {
        return ['stok' => 'integer', 'harga_beli' => 'decimal:2', 'harga_jual' => 'decimal:2'];
    }

    public function serviceItems(): HasMany
    {
        return $this->hasMany(ServiceItem::class);
    }

    public function purchaseDetails(): HasMany
    {
        return $this->hasMany(PurchaseDetail::class);
    }
}
