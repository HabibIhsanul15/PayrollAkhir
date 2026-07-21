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
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->string('position', 255)->nullable();
            $table->string('base_salary_basis', 20)->nullable();

            // aturan dasar (nanti presensi tinggal tambah)
            $table->longText('base_salary_amount_enc')->nullable();
            $table->longText('position_allowance_enc')->nullable();
            $table->longText('allowance_fixed_enc')->nullable();
            $table->longText('deduction_fixed_enc')->nullable();

            // buat pengembangan presensi
            $table->longText('daily_rate_enc')->nullable();
            $table->longText('overtime_rate_per_hour_enc')->nullable();
            $table->longText('late_penalty_per_minute_enc')->nullable();
            
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
