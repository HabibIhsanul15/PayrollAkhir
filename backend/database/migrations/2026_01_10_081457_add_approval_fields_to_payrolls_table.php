<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'status')) {
                $table->string('status', 20)->default('draft')->after('periode');
            }

            if (!Schema::hasColumn('payrolls', 'requested_by')) {
                $table->unsignedBigInteger('requested_by')->nullable()->after('status');
                $table->timestamp('requested_at')->nullable()->after('requested_by');
            }

            if (!Schema::hasColumn('payrolls', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('requested_at');
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (!Schema::hasColumn('payrolls', 'paid_by')) {
                $table->unsignedBigInteger('paid_by')->nullable()->after('approved_at');
                $table->timestamp('paid_at')->nullable()->after('paid_by');
            }

            if (!Schema::hasColumn('payrolls', 'approval_note')) {
                $table->text('approval_note')->nullable()->after('paid_at');
            }

            // ✅ index biar filter status cepat
            // (aman karena cuma dibuat kalau belum ada)
            $table->index(['status', 'periode'], 'payrolls_status_periode_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            // drop index dulu
            if ($this->hasIndex('payrolls', 'payrolls_status_periode_idx')) {
                $table->dropIndex('payrolls_status_periode_idx');
            }

            $cols = [
                'status',
                'requested_by', 'requested_at',
                'approved_by', 'approved_at',
                'paid_by', 'paid_at',
                'approval_note',
            ];

            foreach ($cols as $c) {
                if (Schema::hasColumn('payrolls', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }

    // helper kecil untuk cek index (biar down aman)
    private function hasIndex(string $table, string $indexName): bool
    {
        $sm = Schema::getConnection()->getDoctrineSchemaManager();
        $indexes = $sm->listTableIndexes($table);
        return array_key_exists($indexName, $indexes);
    }
};
