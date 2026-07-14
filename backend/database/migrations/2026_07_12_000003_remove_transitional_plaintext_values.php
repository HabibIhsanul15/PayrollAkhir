<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('employees')->whereNotNull('nik_enc')->update(['nik' => null]);
        DB::table('employees')->whereNotNull('npwp_enc')->update(['npwp' => null]);
        DB::table('employees')->whereNotNull('phone_enc')->update(['phone' => null]);
        DB::table('employees')->whereNotNull('address_enc')->update(['address' => null]);
        DB::table('employees')->whereNotNull('bank_account_number_enc')->update(['bank_account_number' => null]);

        DB::table('salary_profiles')->whereNotNull('position_allowance_enc')->update(['position_allowance' => 0]);
        DB::table('salary_profiles')->whereNotNull('allowance_fixed_enc')->update(['allowance_fixed' => 0]);
        DB::table('salary_profiles')->whereNotNull('deduction_fixed_enc')->update(['deduction_fixed' => 0]);
        DB::table('salary_profiles')->whereNotNull('daily_rate_enc')->update(['daily_rate' => null]);
        DB::table('salary_profiles')->whereNotNull('overtime_rate_per_hour_enc')->update(['overtime_rate_per_hour' => null]);
        DB::table('salary_profiles')->whereNotNull('late_penalty_per_minute_enc')->update(['late_penalty_per_minute' => null]);
        DB::table('salary_profiles')->whereNotNull('mandays_rate_enc')->update(['mandays_rate' => null]);

        foreach (['gaji_pokok', 'tunjangan', 'potongan', 'total', 'catatan', 'total_allowances', 'total_deductions'] as $field) {
            DB::table('payrolls')->whereNotNull($field.'_enc')->update([$field => null]);
        }

        DB::table('payroll_allowances')->whereNotNull('amount_enc')->update(['amount' => null]);
        DB::table('payroll_deductions')->whereNotNull('amount_enc')->update(['amount' => null]);
    }

    public function down(): void
    {
        // Plaintext sengaja tidak dipulihkan; sumber datanya tetap ciphertext.
    }
};
