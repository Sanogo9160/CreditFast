<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_requests', function (Blueprint $table) {
            $table->string('borrower_type', 30)->nullable()->after('client_id');
            $table->string('credit_type', 50)->nullable()->after('borrower_type');
        });
    }

    public function down(): void
    {
        Schema::table('credit_requests', function (Blueprint $table) {
            $table->dropColumn(['borrower_type', 'credit_type']);
        });
    }
};
