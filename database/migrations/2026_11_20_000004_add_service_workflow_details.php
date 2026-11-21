<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->date('estimated_completion_at')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->json('accessories')->nullable();
            $table->text('physical_condition')->nullable();
            $table->unsignedSmallInteger('warranty_days')->default(0);
            $table->date('warranty_expires_at')->nullable();
            $table->string('tracking_key', 64)->nullable()->unique();
        });

        Schema::table('service_items', function (Blueprint $table) {
            $table->decimal('harga_modal', 12, 2)->default(0);
        });

        Schema::table('spareparts', function (Blueprint $table) {
            $table->unsignedInteger('stok_minimum')->default(5);
        });

        DB::table('services')->whereNull('tracking_key')->orderBy('id')->each(function (object $service): void {
            DB::table('services')->where('id', $service->id)->update(['tracking_key' => Str::random(64)]);
        });

        Schema::create('service_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 20);
            $table->string('path');
            $table->string('thumbnail_path');
            $table->string('mime_type', 50);
            $table->unsignedInteger('size');
            $table->timestamps();
            $table->index(['service_id', 'kind']);
        });

        DB::table('message_templates')->updateOrInsert(
            ['key' => 'estimate_changed'],
            [
                'name' => 'Perubahan estimasi',
                'body' => 'Halo {nama}, estimasi servis {kode} diperbarui. Estimasi biaya: {biaya}; estimasi selesai: {estimasi_selesai}. Lacak detail: {link}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('message_templates')->where('key', 'estimate_changed')->delete();
        Schema::dropIfExists('service_photos');

        Schema::table('spareparts', function (Blueprint $table) {
            $table->dropColumn('stok_minimum');
        });

        Schema::table('service_items', function (Blueprint $table) {
            $table->dropColumn('harga_modal');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique(['tracking_key']);
            $table->dropColumn([
                'estimated_completion_at', 'priority', 'accessories', 'physical_condition',
                'warranty_days', 'warranty_expires_at', 'tracking_key',
            ]);
        });
    }
};
