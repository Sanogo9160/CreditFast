<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('client_type', 30)->default('PHYSICAL_PERSON')->after('client_number');
            $table->string('company_name', 200)->nullable()->after('client_type');
            $table->string('trade_name', 200)->nullable()->after('company_name');
            $table->string('registration_number', 100)->nullable()->after('trade_name');
            $table->string('legal_form', 100)->nullable()->after('registration_number');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'client_type',
                'company_name',
                'trade_name',
                'registration_number',
                'legal_form',
            ]);
        });
    }
};
