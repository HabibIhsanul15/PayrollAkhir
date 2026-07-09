<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::table('payrolls', function (Blueprint $table) {
        $table->decimal('gaji_pokok', 15, 2)->nullable()->change();
        $table->decimal('tunjangan', 15, 2)->nullable()->change();
        $table->decimal('potongan', 15, 2)->nullable()->change();
        $table->decimal('total', 15, 2)->nullable()->change();
        $table->text('catatan')->nullable()->change(); // kalau catatan text
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
