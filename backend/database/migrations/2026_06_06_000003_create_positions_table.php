<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->unsignedTinyInteger('level')->comment('Hierarki jabatan: 1 = tertinggi');
            $table->text('description')->nullable();
            $table->longText('default_base_salary_amount_enc')->nullable();
            $table->longText('default_late_penalty_amount_enc')->nullable();
            $table->string('salary_alg', 20)->default('HYBRID');
            $table->string('salary_key_id', 100)->nullable();
            $table->longText('dek_enc')->nullable();
            $table->json('enc_meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['level']);
            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
