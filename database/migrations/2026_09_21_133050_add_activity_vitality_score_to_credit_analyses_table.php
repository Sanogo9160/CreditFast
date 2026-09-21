<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_analyses', function (Blueprint $table) {
            $table->decimal('activity_vitality_score', 5, 2)->default(0.00)->after('activity_score');
        });
    }

    public function down(): void
    {
        Schema::table('credit_analyses', function (Blueprint $table) {
            $table->dropColumn('activity_vitality_score');
        });
    }
};
