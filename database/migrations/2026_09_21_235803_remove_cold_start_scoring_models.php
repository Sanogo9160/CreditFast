<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * IMF rule: no credit without a bank/institution account — Cold Start model retired.
     */
    public function up(): void
    {
        DB::table('scoring_models')
            ->where('scoring_mode', 'COLD_START')
            ->delete();
    }

    public function down(): void
    {
        // Cold Start intentionally not restored.
    }
};
