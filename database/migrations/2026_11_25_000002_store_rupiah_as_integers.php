<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'services' => ['biaya_jasa', 'total_biaya', 'dp_dibayar', 'nominal_dibayar', 'kembalian'],
            'spareparts' => ['harga_beli', 'harga_jual'],
            'service_items' => ['harga_modal', 'harga_satuan', 'subtotal'],
            'purchases' => ['total_biaya'],
            'purchase_details' => ['harga_beli_satuan', 'subtotal'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)->update([$column => DB::raw("ROUND({$column})")]);
            }
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->unsignedBigInteger($column)->default(0)->change();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'services' => ['biaya_jasa', 'total_biaya', 'dp_dibayar', 'nominal_dibayar', 'kembalian'],
            'spareparts' => ['harga_beli', 'harga_jual'],
            'service_items' => ['harga_modal', 'harga_satuan', 'subtotal'],
            'purchases' => ['total_biaya'],
            'purchase_details' => ['harga_beli_satuan', 'subtotal'],
        ] as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->decimal($column, 12, 2)->default(0)->change();
                }
            });
        }
    }
};
