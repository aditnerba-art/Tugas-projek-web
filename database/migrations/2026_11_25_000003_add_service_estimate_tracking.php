<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->date('initial_estimated_completion_at')->nullable();
            $table->timestamp('actual_completed_at')->nullable();
        });

        DB::table('services')
            ->whereNull('initial_estimated_completion_at')
            ->update(['initial_estimated_completion_at' => DB::raw('estimated_completion_at')]);

        DB::table('services')
            ->whereIn('status', ['selesai', 'diambil'])
            ->whereNull('actual_completed_at')
            ->orderBy('id')
            ->chunkById(100, function ($services): void {
                foreach ($services as $service) {
                    $completedAt = DB::table('service_status_logs')
                        ->where('service_id', $service->id)
                        ->where('status_baru', 'selesai')
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->value('created_at');

                    DB::table('services')
                        ->where('id', $service->id)
                        ->update(['actual_completed_at' => $completedAt ?: $service->updated_at]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['initial_estimated_completion_at', 'actual_completed_at']);
        });
    }
};
