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
        Schema::create('security_test_logs', function (Blueprint $table) {
            $table->id();

            $table->string('test_name', 50);   // TAMPER_CIPHERTEXT / WRONG_KEY / AUTHZ_MASKING
            $table->string('alg', 10);         // AES / RSA / HYBRID (kalau relevan)
            $table->unsignedBigInteger('payroll_id')->nullable();

            $table->string('result', 10);      // PASS / FAIL
            $table->string('expected', 200)->nullable();
            $table->string('actual', 200)->nullable();

            $table->json('meta')->nullable();  // detail kecil: field yang diubah, error message singkat, dsb
            $table->timestamps();

            $table->index(['test_name', 'alg']);
            $table->index('payroll_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_test_logs');
    }
};
