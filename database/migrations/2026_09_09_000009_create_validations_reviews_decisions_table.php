<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('human_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_request_id')->constrained('credit_requests')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('validator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('validation_type', 50);
            $table->string('decision', 30)->default('VALIDATED');
            $table->text('comment')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_request_id')->constrained('credit_requests')->cascadeOnDelete();
            $table->foreignId('analyst_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_status', 30)->default('IN_PROGRESS');
            $table->string('recommendation', 40)->default('RESERVED');
            $table->text('comment')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_committee_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_request_id')->constrained('credit_requests')->cascadeOnDelete();
            $table->foreignId('committee_member_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 30);
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->integer('approved_duration_months')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_committee_decisions');
        Schema::dropIfExists('credit_reviews');
        Schema::dropIfExists('human_validations');
    }
};
