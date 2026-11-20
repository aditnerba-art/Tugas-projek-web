<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('status_lama', 40)->nullable();
            $table->string('status_baru', 40);
            $table->text('catatan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['service_id', 'created_at']);
        });

        $legacyLogs = DB::table('service_logs')
            ->select('service_id', 'status_lama', 'status_baru')
            ->selectRaw('catatan_teknis as catatan, NULL as user_id, created_at')
            ->where(function ($query) {
                $query->whereNull('status_lama')
                    ->orWhereColumn('status_lama', '<>', 'status_baru');
            });

        DB::table('service_status_logs')->insertUsing(
            ['service_id', 'status_lama', 'status_baru', 'catatan', 'user_id', 'created_at'],
            $legacyLogs,
        );

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('service_status_logs');
    }
};
