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
            $table->string('base_salary_basis', 20)->default('daily');
            $table->decimal('default_base_salary_amount', 14, 2)->nullable();
            $table->decimal('default_late_penalty_amount', 14, 2)->nullable();
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
