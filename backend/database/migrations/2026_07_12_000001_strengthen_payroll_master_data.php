<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        if (! Schema::hasColumn('allowance_types', 'condition_field')) {
            Schema::table('allowance_types', function (Blueprint $table) {
                $table->string('condition_field', 50)->nullable()->after('input_source');
            });
        }
        if (! Schema::hasColumn('allowance_types', 'condition_operator')) {
            Schema::table('allowance_types', function (Blueprint $table) {
                $table->string('condition_operator', 10)->nullable()->after('condition_field');
            });
        }
        if (! Schema::hasColumn('allowance_types', 'condition_value')) {
            Schema::table('allowance_types', function (Blueprint $table) {
                $table->decimal('condition_value', 14, 2)->nullable()->after('condition_operator');
            });
        }

        Schema::table('salary_profiles', function (Blueprint $table) {
            $table->unique(['employee_id', 'effective_from'], 'salary_profile_employee_date_unique');
        });

        Schema::table('job_histories', function (Blueprint $table) {
            $table->index(['employee_id', 'start_date'], 'job_history_employee_start_index');
        });
    }

    public function down(): void
    {
        Schema::table('job_histories', function (Blueprint $table) {
            $table->dropIndex('job_history_employee_start_index');
        });

        Schema::table('salary_profiles', function (Blueprint $table) {
            $table->dropUnique('salary_profile_employee_date_unique');
        });

        Schema::table('allowance_types', function (Blueprint $table) {
            $table->dropColumn([
                'input_source',
                'condition_field',
                'condition_operator',
                'condition_value',
            ]);
        });
    }
};
