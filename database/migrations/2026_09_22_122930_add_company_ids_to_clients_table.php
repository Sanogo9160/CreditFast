<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('tax_id', 100)->nullable()->after('registration_number');
            $table->string('rccm_number', 100)->nullable()->after('tax_id');
            $table->string('receipt_number', 100)->nullable()->after('rccm_number');
            $table->string('inps_number', 100)->nullable()->after('receipt_number');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['tax_id', 'rccm_number', 'receipt_number', 'inps_number']);
        });
    }
};
