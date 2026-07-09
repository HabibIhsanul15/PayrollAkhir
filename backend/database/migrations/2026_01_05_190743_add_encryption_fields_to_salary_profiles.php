<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('salary_profiles', function (Blueprint $table) {
      $table->longText('base_salary_enc')->nullable()->after('base_salary');
      $table->longText('allowance_fixed_enc')->nullable()->after('allowance_fixed');
      $table->longText('deduction_fixed_enc')->nullable()->after('deduction_fixed');

      $table->longText('daily_rate_enc')->nullable()->after('daily_rate');
      $table->longText('overtime_rate_per_hour_enc')->nullable()->after('overtime_rate_per_hour');
      $table->longText('late_penalty_per_minute_enc')->nullable()->after('late_penalty_per_minute');

      $table->string('salary_alg', 20)->default('AES')->after('late_penalty_per_minute_enc');
      $table->string('salary_key_id', 50)->nullable()->after('salary_alg');
    });
  }

  public function down(): void {
    Schema::table('salary_profiles', function (Blueprint $table) {
      $table->dropColumn([
        'base_salary_enc','allowance_fixed_enc','deduction_fixed_enc',
        'daily_rate_enc','overtime_rate_per_hour_enc','late_penalty_per_minute_enc',
        'salary_alg','salary_key_id'
      ]);
    });
  }
};

