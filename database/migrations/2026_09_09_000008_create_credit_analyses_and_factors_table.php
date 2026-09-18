<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_request_id')->constrained('credit_requests')->cascadeOnDelete();
            $table->foreignId('scoring_model_id')->nullable()->constrained('scoring_models')->nullOnDelete();
            $table->decimal('declared_income', 15, 2)->default(0);
            $table->decimal('documented_income', 15, 2)->default(0);
            $table->decimal('income_consistency_score', 5, 2)->default(0.00);
            $table->decimal('expense_score', 5, 2)->default(0.00);
            $table->decimal('activity_score', 5, 2)->default(0.00);
            $table->decimal('document_score', 5, 2)->default(0.00);
            $table->decimal('savings_score', 5, 2)->default(0.00);
            $table->decimal('credit_history_score', 5, 2)->default(0.00);
            $table->decimal('guarantee_score', 5, 2)->default(0.00);
            $table->decimal('repayment_capacity_score', 5, 2)->default(0.00);
            $table->decimal('residential_zone_score', 5, 2)->default(0.00);
            $table->decimal('overall_score', 5, 2)->default(0.00);
            $table->decimal('confidence_score', 5, 2)->default(0.00);
            $table->string('recommendation', 40)->default('RESERVED');
            $table->text('analysis_summary')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_score_factors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_analysis_id')->constrained('credit_analyses')->cascadeOnDelete();
            $table->foreignId('scoring_rule_id')->nullable()->constrained('scoring_rules')->nullOnDelete();
            $table->string('factor_name', 100);
            $table->string('factor_type', 30);
            $table->decimal('score', 5, 2)->default(0.00);
            $table->decimal('weight', 5, 2)->default(0.00);
            $table->text('explanation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_score_factors');
        Schema::dropIfExists('credit_analyses');
    }
};
