<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deduction_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->unsignedInteger('display_order')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('deduction_types')->insert([
            [
                'code' => 'bpjs_kesehatan',
                'name' => 'BPJS Kesehatan',
                'display_order' => 1,
                'description' => 'Nominal bagian karyawan yang dipotong dari payroll.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'bpjs_tk_jht',
                'name' => 'BPJS TK - JHT',
                'display_order' => 2,
                'description' => 'Nominal bagian karyawan yang dipotong dari payroll.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'bpjs_tk_jp',
                'name' => 'BPJS TK - JP',
                'display_order' => 3,
                'description' => 'Nominal bagian karyawan yang dipotong dari payroll.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'employee_loan',
                'name' => 'Pinjaman Karyawan',
                'display_order' => 4,
                'description' => 'Cicilan atau pengembalian pinjaman karyawan.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'other',
                'name' => 'Potongan Lainnya',
                'display_order' => 5,
                'description' => 'Potongan manual lain sesuai kebijakan perusahaan.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('deduction_types');
    }
};
