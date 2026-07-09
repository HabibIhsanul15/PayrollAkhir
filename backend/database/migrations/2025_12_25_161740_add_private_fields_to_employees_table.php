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
    Schema::table('employees', function (Blueprint $table) {
        if (!Schema::hasColumn('employees', 'nik')) {
            $table->string('nik', 32)->nullable()->after('status');
        }
        if (!Schema::hasColumn('employees', 'npwp')) {
            $table->string('npwp', 32)->nullable()->after('nik');
        }
        if (!Schema::hasColumn('employees', 'phone')) {
            $table->string('phone', 32)->nullable()->after('npwp');
        }
        if (!Schema::hasColumn('employees', 'address')) {
            $table->text('address')->nullable()->after('phone');
        }
        if (!Schema::hasColumn('employees', 'bank_name')) {
            $table->string('bank_name', 100)->nullable()->after('address');
        }
        if (!Schema::hasColumn('employees', 'bank_account_name')) {
            $table->string('bank_account_name', 100)->nullable()->after('bank_name');
        }
        if (!Schema::hasColumn('employees', 'bank_account_number')) {
            $table->string('bank_account_number', 50)->nullable()->after('bank_account_name');
        }
    });
}
public function down(): void
{
    Schema::table('employees', function (Blueprint $table) {
        $table->dropColumn([
            'nik','npwp','phone','address',
            'bank_name','bank_account_name','bank_account_number'
        ]);
    });
}
};
