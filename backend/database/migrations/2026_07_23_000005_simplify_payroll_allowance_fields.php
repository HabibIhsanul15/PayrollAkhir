<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubah mandays ke bilangan utuh dan hapus penanda kondisi lama
     * yang tidak lagi dipakai oleh perhitungan maupun UI.
     */
    public function up(): void
    {
        Schema::table('payroll_allowances', function (Blueprint $table) {
            $table->unsignedInteger('mandays')->nullable()->change();
            $table->dropColumn('condition_met');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_allowances', function (Blueprint $table) {
            $table->decimal('mandays', 8, 2)->nullable()->change();
            $table->boolean('condition_met')->default(true);
        });
    }
};
