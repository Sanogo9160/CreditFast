<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_account_application_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bank_account_application_id');
            $table->string('document_type', 50);
            $table->string('file_path', 255);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('bank_account_application_id', 'baa_docs_app_fk')
                ->references('id')
                ->on('bank_account_applications')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_account_application_documents');
    }
};
