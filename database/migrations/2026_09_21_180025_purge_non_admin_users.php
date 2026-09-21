<?php

use App\Services\KeepAdminOnlyUsers;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * One-shot cleanup for deployed environments that still have staff/demo accounts.
     */
    public function up(): void
    {
        app(KeepAdminOnlyUsers::class)();
    }

    /**
     * Irreversible data cleanup.
     */
    public function down(): void
    {
        //
    }
};
