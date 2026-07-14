<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_recaps', function (Blueprint $table) {
            if (! Schema::hasColumn('monthly_recaps', 'late_count')) {
                $table->unsignedInteger('late_count')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('monthly_recaps', function (Blueprint $table) {
            if (Schema::hasColumn('monthly_recaps', 'late_count')) {
                $table->dropColumn('late_count');
            }
        });
    }
};
