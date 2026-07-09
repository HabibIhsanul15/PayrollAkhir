<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {

            // 1) kasih index biasa untuk FK user_id (biar FK ga "numpang" di unique index)
            $table->index('user_id', 'payrolls_user_id_index');

            // 2) baru aman drop unique lama
            $table->dropUnique('payrolls_user_id_periode_unique');

            // 3) ganti unique jadi per employee + periode
            $table->unique(['employee_id', 'periode'], 'payrolls_employee_id_periode_unique');
        });
    }

public function down(): void
{
    Schema::table('payrolls', function (Blueprint $table) {
        // 1) drop FK dulu (nama constraint biasanya payrolls_employee_id_foreign)
        if (Schema::hasColumn('payrolls', 'employee_id')) {
            try { $table->dropForeign(['employee_id']); } catch (\Throwable $e) {}
        }

        // 2) baru drop unique index
        try { $table->dropUnique('payrolls_employee_id_periode_unique'); } catch (\Throwable $e) {}

        // (opsional) kalau sebelumnya kamu punya unique lain, balikin lagi di sini kalau perlu
        // $table->unique(['employee_id', 'periode'], 'payrolls_employee_id_periode_unique');
    });
}
};
