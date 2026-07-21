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
        Schema::create('special_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deduction_type_id')
                ->nullable()
                ->constrained('deduction_types')
                ->nullOnDelete();
            $table->string('type', 50)->default('kasbon');
            $table->string('period_month', 7); // format: YYYY-MM
            $table->text('amount_enc')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('salary_alg', 20)->nullable();
            $table->string('salary_key_id', 100)->nullable();
            $table->timestamps();

            $table->unique(
                ['employee_id', 'period_month', 'deduction_type_id'],
                'special_deduction_employee_period_type_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('special_deductions');
    }
};
