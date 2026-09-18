<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_profiles', function (Blueprint $table) {
            $table->unique('client_id');
        });

        Schema::table('scoring_models', function (Blueprint $table) {
            $table->unique(['version', 'scoring_mode']);
        });

        Schema::table('scoring_rules', function (Blueprint $table) {
            $table->unique(['scoring_model_id', 'rule_code']);
        });

        Schema::table('credit_requests', function (Blueprint $table) {
            $table->index('status');
            $table->index('submitted_at');
        });

        Schema::table('anomalies', function (Blueprint $table) {
            $table->index(['credit_request_id', 'status']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->unique('credit_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropUnique(['credit_request_id']);
        });

        Schema::table('anomalies', function (Blueprint $table) {
            $table->dropIndex(['credit_request_id', 'status']);
        });

        Schema::table('credit_requests', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['submitted_at']);
        });

        Schema::table('scoring_rules', function (Blueprint $table) {
            $table->dropUnique(['scoring_model_id', 'rule_code']);
        });

        Schema::table('scoring_models', function (Blueprint $table) {
            $table->dropUnique(['version', 'scoring_mode']);
        });

        Schema::table('financial_profiles', function (Blueprint $table) {
            $table->dropUnique(['client_id']);
        });
    }
};
