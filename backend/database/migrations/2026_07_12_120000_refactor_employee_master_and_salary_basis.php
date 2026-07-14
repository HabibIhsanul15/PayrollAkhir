<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'join_date')) {
                $table->date('join_date')->nullable()->after('name');
            }
        });

        Schema::table('grades', function (Blueprint $table) {
            if (! Schema::hasColumn('grades', 'base_salary_basis')) {
                $table->string('base_salary_basis', 20)->default('daily')->after('description');
            }
            if (! Schema::hasColumn('grades', 'default_base_salary_amount')) {
                $table->decimal('default_base_salary_amount', 14, 2)->nullable()->after('base_salary_basis');
            }
        });

        Schema::table('salary_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('salary_profiles', 'base_salary_basis')) {
                $table->string('base_salary_basis', 20)->nullable()->after('position');
            }
            if (! Schema::hasColumn('salary_profiles', 'base_salary_amount')) {
                $table->decimal('base_salary_amount', 14, 2)->nullable()->after('base_salary_basis');
            }
            if (! Schema::hasColumn('salary_profiles', 'base_salary_amount_enc')) {
                $table->longText('base_salary_amount_enc')->nullable()->after('base_salary_amount');
            }
        });

        DB::table('grades')->update([
            'base_salary_basis' => 'daily',
            'default_base_salary_amount' => DB::raw('COALESCE(default_base_salary_amount, default_mandays_rate)'),
        ]);

        $employeeBasisById = DB::table('employees')
            ->leftJoin('work_bases', 'employees.work_basis_id', '=', 'work_bases.id')
            ->select('employees.id', 'work_bases.code as work_basis_code')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->id => $row->work_basis_code])
            ->all();

        $profiles = DB::table('salary_profiles')
            ->select('id', 'employee_id', 'mandays_rate', 'mandays_rate_enc')
            ->get();

        foreach ($profiles as $profile) {
            $basis = (($employeeBasisById[(int) $profile->employee_id] ?? 'mandays') === 'monthly')
                ? 'monthly'
                : 'daily';

            DB::table('salary_profiles')
                ->where('id', $profile->id)
                ->update([
                    'base_salary_basis' => $basis,
                    'base_salary_amount' => $profile->mandays_rate,
                    'base_salary_amount_enc' => $profile->mandays_rate_enc,
                ]);
        }

        $firstJobHistories = DB::table('job_histories')
            ->select('employee_id', DB::raw('MIN(start_date) as first_join_date'))
            ->groupBy('employee_id')
            ->get();

        foreach ($firstJobHistories as $history) {
            DB::table('employees')
                ->where('id', $history->employee_id)
                ->whereNull('join_date')
                ->update(['join_date' => $history->first_join_date]);
        }
    }

    public function down(): void
    {
        Schema::table('salary_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'base_salary_basis',
                'base_salary_amount',
                'base_salary_amount_enc',
            ]);
        });

        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn([
                'base_salary_basis',
                'default_base_salary_amount',
            ]);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('join_date');
        });
    }
};
