<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guarantees', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('verified_at');
            $table->string('original_filename')->nullable()->after('file_path');
            $table->string('mime_type', 120)->nullable()->after('original_filename');
        });
    }

    public function down(): void
    {
        Schema::table('guarantees', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'original_filename', 'mime_type']);
        });
    }
};
