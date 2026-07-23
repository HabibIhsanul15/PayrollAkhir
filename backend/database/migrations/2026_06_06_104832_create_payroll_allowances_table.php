<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allowance_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('mandays')->nullable();
            $table->json('calculation_detail')->nullable();
            $table->boolean('is_manual_override')->default(false);
            $table->text('amount_enc')->nullable();
            $table->string('salary_alg', 20)->nullable();
            $table->string('salary_key_id', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_allowances');
    }
};
