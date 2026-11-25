<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedBigInteger('amount');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('service_rate_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('service_items', function (Blueprint $table) {
            $table->string('approval_status', 24)->default('installed');
        });
    }

    public function down(): void
    {
        Schema::table('service_items', function (Blueprint $table) {
            $table->dropColumn('approval_status');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_rate_id');
        });

        Schema::dropIfExists('service_rates');
    }
};
