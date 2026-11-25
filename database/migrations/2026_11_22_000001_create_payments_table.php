<?php

use App\Services\LegacyPaymentImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('method', 16);
            $table->timestamp('paid_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->string('type', 12)->default('payment');
            $table->timestamps();
            $table->index(['paid_at', 'received_by']);
            $table->index(['service_id', 'type']);
        });

        app(LegacyPaymentImporter::class)->import();

        Schema::table('services', function (Blueprint $table) {
            $table->string('status_pembayaran', 30)->default('belum')->change();
        });
    }

    public function down(): void
    {
        DB::table('services')
            ->where('status_pembayaran', 'belum')
            ->update(['status_pembayaran' => 'belum_bayar']);
        DB::table('services')
            ->where('status_pembayaran', 'sebagian')
            ->update(['status_pembayaran' => 'dp']);

        Schema::table('services', function (Blueprint $table) {
            $table->string('status_pembayaran', 30)->default('belum_bayar')->change();
        });
        Schema::dropIfExists('payments');
    }
};
