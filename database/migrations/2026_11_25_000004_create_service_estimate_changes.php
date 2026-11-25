<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_estimate_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('previous_estimated_completion_at')->nullable();
            $table->date('proposed_estimated_completion_at');
            $table->unsignedSmallInteger('additional_days');
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['service_id', 'status']);
        });

        DB::table('message_templates')->updateOrInsert(
            ['key' => 'estimate_approval'],
            [
                'name' => 'Persetujuan perubahan estimasi',
                'body' => 'Halo {nama}, estimasi servis {kode} diusulkan berubah dari {estimasi_lama} menjadi {estimasi_baru} (+{tambahan_hari} hari). Alasan: {alasan}. Silakan setujui atau tolak melalui {link}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('message_templates')->where('key', 'estimate_approval')->delete();
        Schema::dropIfExists('service_estimate_changes');
    }
};
