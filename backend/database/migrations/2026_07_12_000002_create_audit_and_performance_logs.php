<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perf_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scenario', 50);
            $table->string('alg', 20);
            $table->decimal('encrypt_ms', 12, 3)->nullable();
            $table->decimal('decrypt_ms', 12, 3)->nullable();
            $table->decimal('db_ms', 12, 3)->nullable();
            $table->decimal('total_ms', 12, 3)->nullable();
            $table->unsignedBigInteger('cipher_bytes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['scenario', 'alg', 'created_at'], 'perf_scenario_alg_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perf_logs');
    }
};
