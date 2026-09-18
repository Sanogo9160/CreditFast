<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('activity_type', 100);
            $table->string('sector', 100)->nullable();
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->string('location', 255)->nullable();
            $table->decimal('monthly_revenue', 15, 2)->default(0);
            $table->string('status', 30)->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('financial_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->decimal('monthly_income', 15, 2)->default(0);
            $table->decimal('other_income', 15, 2)->default(0);
            $table->decimal('monthly_expenses', 15, 2)->default(0);
            $table->decimal('existing_debt_payment', 15, 2)->default(0);
            $table->integer('dependents_count')->default(0);
            $table->decimal('disposable_income', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_profiles');
        Schema::dropIfExists('activities');
    }
};
