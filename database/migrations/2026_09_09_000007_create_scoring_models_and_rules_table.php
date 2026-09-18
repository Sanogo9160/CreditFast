<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_models', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('version', 50);
            $table->string('scoring_mode', 30)->default('STANDARD');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('ACTIVE');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('scoring_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scoring_model_id')->constrained('scoring_models')->cascadeOnDelete();
            $table->string('rule_code', 50);
            $table->string('rule_name', 100);
            $table->string('factor_type', 50);
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2)->default(0.00);
            $table->decimal('min_score', 5, 2)->default(0.00);
            $table->decimal('max_score', 5, 2)->default(100.00);
            $table->json('rule_config')->nullable();
            $table->integer('priority')->default(1);
            $table->string('status', 30)->default('ACTIVE');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_rules');
        Schema::dropIfExists('scoring_models');
    }
};
