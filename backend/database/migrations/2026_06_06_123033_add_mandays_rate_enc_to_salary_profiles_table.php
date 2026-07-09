<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_profiles', function (Blueprint $table) {
            $table->text('mandays_rate_enc')->nullable()->after('mandays_rate');
        });
    }

    public function down(): void
    {
        Schema::table('salary_profiles', function (Blueprint $table) {
            $table->dropColumn('mandays_rate_enc');
        });
    }
};
