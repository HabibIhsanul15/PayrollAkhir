<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mutation_requests', function (Blueprint $table) {
            $table->date('requested_date')->nullable()->after('effective_date');
        });

        // Riwayat lama belum memiliki tanggal pengajuan bisnis. Ambil tanggal
        // pembuatan record agar seluruh data tetap dapat ditampilkan konsisten.
        DB::table('mutation_requests')
            ->whereNull('requested_date')
            ->update(['requested_date' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('mutation_requests', function (Blueprint $table) {
            $table->dropColumn('requested_date');
        });
    }
};
