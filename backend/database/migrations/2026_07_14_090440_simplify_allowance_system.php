<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop columns from allowance_types
        Schema::table('allowance_types', function (Blueprint $table) {
            // Drop columns if they exist
            if (Schema::hasColumn('allowance_types', 'input_source')) {
                $table->dropColumn('input_source');
            }
            if (Schema::hasColumn('allowance_types', 'condition_field')) {
                $table->dropColumn('condition_field');
            }
            if (Schema::hasColumn('allowance_types', 'condition_operator')) {
                $table->dropColumn('condition_operator');
            }
            if (Schema::hasColumn('allowance_types', 'condition_value')) {
                $table->dropColumn('condition_value');
            }
        });

        // 2. Drop columns from position_allowance_rates
        Schema::table('position_allowance_rates', function (Blueprint $table) {
            if (Schema::hasColumn('position_allowance_rates', 'rate_multiplier')) {
                $table->dropColumn('rate_multiplier');
            }
            if (Schema::hasColumn('position_allowance_rates', 'rate_formula')) {
                $table->dropColumn('rate_formula');
            }
            if (Schema::hasColumn('position_allowance_rates', 'requires_condition')) {
                $table->dropColumn('requires_condition');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('allowance_types', function (Blueprint $table) {
            $table->string('input_source', 50)->nullable();
            $table->string('condition_field', 50)->nullable();
            $table->string('condition_operator', 10)->nullable();
            $table->string('condition_value', 100)->nullable();
        });

        Schema::table('position_allowance_rates', function (Blueprint $table) {
            $table->decimal('rate_multiplier', 8, 4)->nullable();
            $table->string('rate_formula', 200)->nullable();
            $table->string('requires_condition', 100)->nullable();
        });
    }
};
