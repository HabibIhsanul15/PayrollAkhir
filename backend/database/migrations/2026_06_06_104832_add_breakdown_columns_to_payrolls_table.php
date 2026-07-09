<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('total_allowances', 15, 2)->nullable()->after('potongan_enc');
            $table->decimal('total_deductions', 15, 2)->nullable()->after('total_allowances');
            $table->string('calculation_mode', 20)->default('manual')->after('total_deductions');
            $table->timestamp('calculated_at')->nullable()->after('calculation_mode');
            $table->text('total_allowances_enc')->nullable()->after('calculated_at');
            $table->text('total_deductions_enc')->nullable()->after('total_allowances_enc');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'total_allowances',
                'total_deductions',
                'calculation_mode',
                'calculated_at',
                'total_allowances_enc',
                'total_deductions_enc'
            ]);
        });
    }
};
