<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('body');
            $table->timestamps();
        });

        DB::table('message_templates')->insert([
            [
                'key' => 'service_registered',
                'name' => 'Servis didaftarkan',
                'body' => 'Halo {nama}, unit {unit} telah diterima untuk servis. Kode akses: {kode}. Lacak servis: {link}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'cost_approval',
                'name' => 'Persetujuan biaya',
                'body' => 'Halo {nama}, estimasi biaya servis {kode} untuk {unit} adalah {biaya}. Mohon berikan persetujuan melalui {link}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'service_ready',
                'name' => 'Servis selesai / siap diambil',
                'body' => 'Halo {nama}, servis {kode} ({unit}) telah selesai dan siap diambil. Lacak servis: {link}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'pickup_reminder',
                'name' => 'Pengingat unit belum diambil',
                'body' => 'Halo {nama}, unit {unit} pada servis {kode} masih menunggu untuk diambil. Lacak servis: {link}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message_type', 60);
            $table->string('recipient', 20);
            $table->text('message');
            $table->string('status', 20)->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['message_type', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('message_templates');
    }
};
