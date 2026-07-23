<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tidak ada UI, endpoint, atau perhitungan yang mendukung override
     * potongan. Kolom ini selalu bernilai false sehingga tidak diperlukan.
     */
    public function up(): void
    {
        Schema::table('payroll_deductions', function (Blueprint $table) {
            $table->dropColumn('is_manual_override');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_deductions', function (Blueprint $table) {
            $table->boolean('is_manual_override')->default(false);
        });
    }
};
