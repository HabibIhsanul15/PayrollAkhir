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
            $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->string('position', 255)->nullable();

            // aturan dasar (nanti presensi tinggal tambah)
            $table->decimal('base_salary', 14, 2)->default(0);
            $table->longText('base_salary_enc')->nullable();
            $table->decimal('allowance_fixed', 14, 2)->default(0);
            $table->longText('allowance_fixed_enc')->nullable();
            $table->decimal('deduction_fixed', 14, 2)->default(0);
            $table->longText('deduction_fixed_enc')->nullable();

            // buat pengembangan presensi
            $table->decimal('daily_rate', 14, 2)->nullable();
            $table->longText('daily_rate_enc')->nullable();
            $table->decimal('overtime_rate_per_hour', 14, 2)->nullable();
            $table->longText('overtime_rate_per_hour_enc')->nullable();
            $table->decimal('late_penalty_per_minute', 14, 2)->nullable();
            $table->longText('late_penalty_per_minute_enc')->nullable();
            
            $table->decimal('mandays_rate', 14, 2)->nullable()->comment('Rate per hari untuk Project Partner (mandays basis)');
            $table->text('mandays_rate_enc')->nullable();

            $table->string('salary_alg', 20)->default('AES');
            $table->string('salary_key_id', 50)->nullable();

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
