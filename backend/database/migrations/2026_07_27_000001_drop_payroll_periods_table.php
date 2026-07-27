<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Periode payroll memakai aturan tetap 28--27, sehingga master tabel tidak diperlukan.
     */
    public function up(): void
    {
        Schema::dropIfExists('payroll_periods');
    }

    public function down(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->char('period_month', 7)->unique();
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });
    }
};
