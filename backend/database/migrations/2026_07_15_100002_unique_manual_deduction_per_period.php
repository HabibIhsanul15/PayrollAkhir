<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('special_deductions', function (Blueprint $table) {
            $table->unique(
                ['employee_id', 'period_month', 'deduction_type_id'],
                'special_deduction_employee_period_type_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('special_deductions', function (Blueprint $table) {
            $table->dropUnique('special_deduction_employee_period_type_unique');
        });
    }
};
