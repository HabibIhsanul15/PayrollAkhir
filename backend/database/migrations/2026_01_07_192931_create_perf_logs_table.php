<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('perf_logs', function (Blueprint $table) {
            $table->id();
            $table->string('scenario', 30); // CREATE / READ_DETAIL
            $table->string('alg', 10);      // AES / RSA / HYBRID
            $table->unsignedBigInteger('payroll_id')->nullable();

            $table->decimal('encrypt_ms', 10, 3)->nullable();
            $table->decimal('decrypt_ms', 10, 3)->nullable();
            $table->decimal('db_ms', 10, 3)->nullable();
            $table->decimal('total_ms', 10, 3);

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['scenario', 'alg']);
            $table->index('payroll_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perf_logs');
    }
};
