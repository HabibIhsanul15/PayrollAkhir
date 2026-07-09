<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_allowance_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('allowance_type_id')->constrained('allowance_types')->cascadeOnDelete();
            $table->decimal('rate_amount', 14, 2)->nullable()->comment('Nominal per unit (per manday/trip/bulan). Null = tidak berlaku untuk grade ini');
            $table->decimal('rate_multiplier', 8, 4)->nullable()->comment('Multiplier untuk formula, misal 1.5 untuk TRAINER');
            $table->string('rate_formula', 200)->nullable()->comment('Deskripsi formula, misal: 1.5x mandays_rate');
            $table->string('requires_condition', 100)->nullable()->comment('Kondisi tambahan, misal: num_toddlers>=3');
            $table->date('effective_from');
            $table->date('effective_to')->nullable()->comment('Null = berlaku seterusnya');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['grade_id', 'allowance_type_id', 'effective_from'], 'gar_unique_grade_type_date');
            $table->index(['grade_id', 'is_active']);
            $table->index(['allowance_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_allowance_rates');
    }
};
