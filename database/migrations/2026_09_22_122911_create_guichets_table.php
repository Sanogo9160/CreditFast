<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guichets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caisse_id')->constrained('caisses')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['caisse_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guichets');
    }
};
