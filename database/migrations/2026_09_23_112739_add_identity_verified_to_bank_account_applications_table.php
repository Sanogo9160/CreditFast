<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_account_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_account_applications', 'identity_verified')) {
                $table->boolean('identity_verified')->default(false)->after('status');
            }
            if (! Schema::hasColumn('bank_account_applications', 'identity_verified_by')) {
                $table->foreignId('identity_verified_by')->nullable()->after('identity_verified')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('bank_account_applications', 'identity_verified_at')) {
                $table->timestamp('identity_verified_at')->nullable()->after('identity_verified_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_account_applications', function (Blueprint $table) {
            if (Schema::hasColumn('bank_account_applications', 'identity_verified_by')) {
                $table->dropConstrainedForeignId('identity_verified_by');
            }
            if (Schema::hasColumn('bank_account_applications', 'identity_verified_at')) {
                $table->dropColumn('identity_verified_at');
            }
            if (Schema::hasColumn('bank_account_applications', 'identity_verified')) {
                $table->dropColumn('identity_verified');
            }
        });
    }
};
