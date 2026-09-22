<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_analyses', function (Blueprint $table) {
            $table->decimal('proposed_annual_interest_rate', 5, 2)->nullable()->after('overall_score');
        });

        Schema::table('credit_committee_decisions', function (Blueprint $table) {
            $table->decimal('annual_interest_rate_percent', 5, 2)->nullable()->after('approved_duration_months');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('annual_interest_rate_percent', 5, 2)->nullable()->after('duration_months');
        });
    }

    public function down(): void
    {
        Schema::table('credit_analyses', function (Blueprint $table) {
            $table->dropColumn('proposed_annual_interest_rate');
        });

        Schema::table('credit_committee_decisions', function (Blueprint $table) {
            $table->dropColumn('annual_interest_rate_percent');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('annual_interest_rate_percent');
        });
    }
};
