<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->foreignId('caisse_id')->nullable()->after('client_id')->constrained('caisses')->nullOnDelete();
            $table->foreignId('guichet_id')->nullable()->after('caisse_id')->constrained('guichets')->nullOnDelete();
            $table->foreignId('cash_desk_id')->nullable()->after('guichet_id')->constrained('cash_desks')->nullOnDelete();
            $table->unsignedBigInteger('bank_account_application_id')->nullable()->after('cash_desk_id');
            $table->foreign('bank_account_application_id', 'fa_baa_fk')
                ->references('id')
                ->on('bank_account_applications')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropForeign('fa_baa_fk');
            $table->dropColumn('bank_account_application_id');
            $table->dropConstrainedForeignId('cash_desk_id');
            $table->dropConstrainedForeignId('guichet_id');
            $table->dropConstrainedForeignId('caisse_id');
        });
    }
};
