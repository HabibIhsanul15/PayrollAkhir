<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('salary_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // aturan dasar (nanti presensi tinggal tambah)
            $table->decimal('base_salary', 14, 2)->default(0);
            $table->decimal('allowance_fixed', 14, 2)->default(0);
            $table->decimal('deduction_fixed', 14, 2)->default(0);

            // buat pengembangan presensi
            $table->decimal('daily_rate', 14, 2)->nullable();
            $table->decimal('overtime_rate_per_hour', 14, 2)->nullable();
            $table->decimal('late_penalty_per_minute', 14, 2)->nullable();

            $table->date('effective_from')->default(now()); // mulai berlaku
            $table->timestamps();

            $table->index(['employee_id','effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_profiles');
    }
};
