<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Semua periode selalu open dan aplikasi tidak menyediakan cara untuk
     * menutupnya, sehingga status tidak memiliki fungsi operasional.
     */
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->enum('status', ['open', 'closed'])->default('open');
        });
    }
};
