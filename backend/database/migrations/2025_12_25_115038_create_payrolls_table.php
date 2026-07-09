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
    Schema::create('payrolls', function ($table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->date('periode');
        $table->decimal('gaji_pokok', 15, 2)->default(0);
        $table->decimal('tunjangan', 15, 2)->default(0);
        $table->decimal('potongan', 15, 2)->default(0);
        $table->decimal('total', 15, 2)->default(0);
        $table->text('catatan')->nullable();
        $table->timestamps();

        $table->unique(['user_id', 'periode']);
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
