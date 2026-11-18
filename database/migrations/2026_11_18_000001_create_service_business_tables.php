<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->string('no_wa', 20);
            $table->text('alamat')->nullable();
            $table->timestamps();
        });

        Schema::create('spareparts', function (Blueprint $table) {
            $table->id();
            $table->string('kode_barang', 50)->unique();
            $table->string('nama_barang', 150);
            $table->unsignedInteger('stok')->default(0);
            $table->decimal('harga_beli', 12, 2)->default(0);
            $table->decimal('harga_jual', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('no_nota', 32)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teknisi_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('jenis_unit', 100)->nullable();
            $table->string('merek_tipe', 150);
            $table->string('no_seri', 150)->nullable();
            $table->text('keluhan');
            $table->text('kelengkapan')->nullable();
            $table->string('status', 40)->default('masuk');
            $table->decimal('biaya_jasa', 12, 2)->default(0);
            $table->decimal('total_biaya', 12, 2)->default(0);
            $table->string('status_pembayaran', 30)->default('belum_bayar');
            $table->string('metode_pembayaran', 30)->nullable();
            $table->decimal('dp_dibayar', 12, 2)->default(0);
            $table->decimal('nominal_dibayar', 12, 2)->default(0);
            $table->decimal('kembalian', 12, 2)->default(0);
            $table->timestamps();
            $table->index(['teknisi_id', 'status']);
        });

        Schema::create('service_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sparepart_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('jumlah');
            $table->decimal('harga_satuan', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });

        Schema::create('service_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('status_lama', 40)->nullable();
            $table->string('status_baru', 40);
            $table->text('catatan_teknis')->nullable();
            $table->timestamps();
        });

        Schema::create('service_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index('service_id');
        });

        Schema::create('customer_sessions', function (Blueprint $table) {
            $table->char('session_hash', 64)->primary();
            $table->foreignId('access_token_id')->constrained('service_access_tokens')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('no_pembelian', 50)->unique();
            $table->string('supplier', 100);
            $table->date('tanggal_pembelian');
            $table->decimal('total_biaya', 12, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sparepart_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('jumlah');
            $table->decimal('harga_beli_satuan', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_details');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('customer_sessions');
        Schema::dropIfExists('service_access_tokens');
        Schema::dropIfExists('service_logs');
        Schema::dropIfExists('service_items');
        Schema::dropIfExists('services');
        Schema::dropIfExists('spareparts');
        Schema::dropIfExists('customers');
    }
};
