<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ganti istilah jabatan yang tampil, tanpa mengubah kode peran staff
     * yang digunakan oleh sistem otorisasi.
     */
    public function up(): void
    {
        $now = now();

        DB::table('positions')->where('code', 'staff')->update([
            'name' => 'Pegawai',
            'description' => 'Pegawai',
            'updated_at' => $now,
        ]);

        foreach (['employees', 'salary_profiles', 'job_histories'] as $table) {
            DB::table($table)->where('position', 'Staff')->update([
                'position' => 'Pegawai',
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $now = now();

        DB::table('positions')->where('code', 'staff')->update([
            'name' => 'Staff',
            'description' => 'Staff',
            'updated_at' => $now,
        ]);

        foreach (['employees', 'salary_profiles', 'job_histories'] as $table) {
            DB::table($table)->where('position', 'Pegawai')->update([
                'position' => 'Staff',
                'updated_at' => $now,
            ]);
        }
    }
};
