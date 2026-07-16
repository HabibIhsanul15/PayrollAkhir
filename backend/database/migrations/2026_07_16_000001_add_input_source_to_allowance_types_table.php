<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('allowance_types', 'input_source')) {
            Schema::table('allowance_types', function (Blueprint $table) {
                $table->string('input_source', 50)->nullable()->after('calculation_type');
            });
        }

        DB::table('allowance_types')
            ->where('calculation_type', 'per_trip')
            ->whereNull('input_source')
            ->update(['input_source' => 'business_trips']);

        DB::table('allowance_types')
            ->where('calculation_type', 'per_mandays')
            ->whereNull('input_source')
            ->update(['input_source' => 'total_mandays']);

        DB::table('allowance_types')
            ->where('code', 'training')
            ->update(['input_source' => 'training_days']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('allowance_types', 'input_source')) {
            Schema::table('allowance_types', function (Blueprint $table) {
                $table->dropColumn('input_source');
            });
        }
    }
};
