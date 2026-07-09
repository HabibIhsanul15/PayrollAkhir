<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('employees', function (Blueprint $table) {
      $table->longText('nik_enc')->nullable()->after('nik');
      $table->longText('npwp_enc')->nullable()->after('npwp');
      $table->longText('bank_account_number_enc')->nullable()->after('bank_account_number');

      // opsional kalau mau ikut terenkripsi
      $table->longText('phone_enc')->nullable()->after('phone');
      $table->longText('address_enc')->nullable()->after('address');

      $table->string('pii_alg', 20)->default('AES')->after('address_enc');
      $table->string('pii_key_id', 50)->nullable()->after('pii_alg');
    });
  }

  public function down(): void {
    Schema::table('employees', function (Blueprint $table) {
      $table->dropColumn([
        'nik_enc','npwp_enc','bank_account_number_enc',
        'phone_enc','address_enc',
        'pii_alg','pii_key_id'
      ]);
    });
  }
};

