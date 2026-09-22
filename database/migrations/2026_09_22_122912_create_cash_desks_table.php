<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_desks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guichet_id')->constrained('guichets')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('label', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['guichet_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_desks');
    }
};
