<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove redundant payroll fields and unused calculation-engine metadata.
     */
    public function up(): void
    {
        $columns = [];

        foreach (['total_allowances_enc', 'total_deductions_enc', 'catatan_enc', 'engine_version'] as $column) {
            if (Schema::hasColumn('payrolls', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns !== []) {
            Schema::table('payrolls', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    /**
     * Restore the nullable columns only when this migration is rolled back.
     */
    public function down(): void
    {
        $columns = [];

        foreach (['total_allowances_enc', 'total_deductions_enc', 'catatan_enc', 'engine_version'] as $column) {
            if (! Schema::hasColumn('payrolls', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns !== []) {
            Schema::table('payrolls', function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $column === 'engine_version'
                        ? $table->string($column, 20)->nullable()
                        : $table->text($column)->nullable();
                }
            });
        }
    }
};
