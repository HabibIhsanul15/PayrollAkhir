<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('payrolls', function (Blueprint $table) {
      $table->longText('gaji_pokok_enc')->nullable()->after('gaji_pokok');
      $table->longText('tunjangan_enc')->nullable()->after('tunjangan');
      $table->longText('potongan_enc')->nullable()->after('potongan');
      $table->longText('total_enc')->nullable()->after('total');
      $table->longText('catatan_enc')->nullable()->after('catatan');

      $table->string('salary_alg', 20)->default('AES')->after('catatan_enc');
      $table->string('salary_key_id', 50)->nullable()->after('salary_alg');
    });
  }

  public function down(): void {
    Schema::table('payrolls', function (Blueprint $table) {
      $table->dropColumn([
        'gaji_pokok_enc','tunjangan_enc','potongan_enc','total_enc','catatan_enc',
        'salary_alg','salary_key_id'
      ]);
    });
  }
};
