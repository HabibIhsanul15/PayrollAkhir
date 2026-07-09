<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_profiles', function (Blueprint $table) {
            // nullable — tidak wajib untuk fix_rate / monthly
            $table->decimal('mandays_rate', 14, 2)
                ->nullable()
                ->after('late_penalty_per_minute')
                ->comment('Rate per hari untuk Project Partner (mandays basis)');
        });
    }

    public function down(): void
    {
        Schema::table('salary_profiles', function (Blueprint $table) {
            $table->dropColumn('mandays_rate');
        });
    }
};
