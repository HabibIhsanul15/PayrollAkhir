<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FIELDS = [
        'wfo_days',
        'wfh_days',
        'out_of_town_days',
        'training_days',
        'overtime_hours',
        'total_mandays',
    ];

    /**
     * Store recap counters as whole numbers, matching the UI and validation rules.
     */
    public function up(): void
    {
        $fields = self::FIELDS;

        Schema::table('monthly_recaps', function (Blueprint $table) use ($fields) {
            foreach ($fields as $field) {
                $table->unsignedInteger($field)->default(0)->change();
            }
        });
    }

    /**
     * Restore decimal storage if this migration is rolled back.
     */
    public function down(): void
    {
        $fields = self::FIELDS;

        Schema::table('monthly_recaps', function (Blueprint $table) use ($fields) {
            foreach ($fields as $field) {
                $table->decimal($field, 8, 2)->default(0)->change();
            }
        });
    }
};
