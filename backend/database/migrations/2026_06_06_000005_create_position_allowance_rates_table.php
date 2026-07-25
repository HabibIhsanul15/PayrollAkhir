<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('position_allowance_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('allowance_type_id')->constrained('allowance_types')->cascadeOnDelete();
            $table->longText('rate_amount_enc')->nullable();
            $table->string('salary_alg', 20)->default('HYBRID');
            $table->string('salary_key_id', 100)->nullable();
            $table->longText('dek_enc')->nullable();
            $table->json('enc_meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['position_id', 'allowance_type_id'], 'pos_allowance_unique');
            $table->index(['position_id', 'is_active']);
            $table->index(['allowance_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_allowance_rates');
    }
};
