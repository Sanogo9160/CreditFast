<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->decimal('requested_amount', 15, 2);
            $table->integer('duration_months');
            $table->string('purpose', 255);
            $table->decimal('declared_monthly_income', 15, 2)->default(0);
            $table->decimal('declared_monthly_expenses', 15, 2)->default(0);
            $table->decimal('estimated_monthly_payment', 15, 2)->default(0);
            $table->decimal('disposable_income', 15, 2)->default(0);
            $table->string('repayment_capacity_status', 30)->default('SUFFICIENT');
            $table->string('status', 40)->default('DRAFT');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('guarantees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_request_id')->constrained('credit_requests')->cascadeOnDelete();
            $table->string('guarantee_type', 80);
            $table->text('description')->nullable();
            $table->decimal('declared_value', 15, 2)->default(0);
            $table->decimal('verified_value', 15, 2)->nullable();
            $table->string('verification_status', 30)->default('PENDING');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guarantees');
        Schema::dropIfExists('credit_requests');
    }
};
