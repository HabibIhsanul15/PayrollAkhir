<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Semua kolom baru nullable agar employee lama tidak rusak
            $table->foreignId('grade_id')
                ->nullable()
                ->after('position')
                ->constrained('grades')
                ->nullOnDelete();

            $table->foreignId('employment_type_id')
                ->nullable()
                ->after('grade_id')
                ->constrained('employment_types')
                ->nullOnDelete();

            $table->foreignId('work_basis_id')
                ->nullable()
                ->after('employment_type_id')
                ->constrained('work_bases')
                ->nullOnDelete();

            // Kolom atribut dengan default value (tidak nullable)
            $table->unsignedTinyInteger('num_toddlers')
                ->default(0)
                ->after('work_basis_id')
                ->comment('Jumlah balita, digunakan untuk syarat Tunjangan Pengasuh');

            $table->boolean('is_trainer')
                ->default(false)
                ->after('num_toddlers')
                ->comment('Kategori trainer, digunakan untuk Tunjangan Training (1.5x rate)');

            $table->boolean('is_on_probation')
                ->default(false)
                ->after('is_trainer')
                ->comment('Masa percobaan promosi, Tunjangan Jabatan 50%');

            $table->index(['grade_id']);
            $table->index(['employment_type_id']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['grade_id']);
            $table->dropForeign(['employment_type_id']);
            $table->dropForeign(['work_basis_id']);
            $table->dropIndex(['grade_id']);
            $table->dropIndex(['employment_type_id']);
            $table->dropColumn([
                'grade_id',
                'employment_type_id',
                'work_basis_id',
                'num_toddlers',
                'is_trainer',
                'is_on_probation',
            ]);
        });
    }
};
