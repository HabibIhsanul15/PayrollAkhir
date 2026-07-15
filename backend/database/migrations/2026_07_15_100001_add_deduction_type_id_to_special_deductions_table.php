<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('special_deductions', function (Blueprint $table) {
            $table->foreignId('deduction_type_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('deduction_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('special_deductions', function (Blueprint $table) {
            $table->dropForeign(['deduction_type_id']);
            $table->dropColumn('deduction_type_id');
        });
    }
};
