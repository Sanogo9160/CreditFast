<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caisse_id')->constrained('caisses')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['caisse_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_number_sequences');
    }
};
