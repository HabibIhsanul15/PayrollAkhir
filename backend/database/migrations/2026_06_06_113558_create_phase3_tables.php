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
        Schema::create('monthly_recaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->char('period_month', 7); // e.g., 2026-06
            
            $table->decimal('wfo_days', 8, 2)->default(0);
            $table->decimal('wfh_days', 8, 2)->default(0);
            $table->decimal('out_of_town_days', 8, 2)->default(0);
            $table->integer('business_trips')->default(0);
            $table->decimal('training_days', 8, 2)->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->decimal('total_mandays', 8, 2)->default(0);
            
            $table->boolean('is_finalized')->default(false);
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('finalized_at')->nullable();
            
            $table->timestamps();

            $table->unique(['employee_id', 'period_month'], 'monthly_recap_emp_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_recaps');
    }
};
