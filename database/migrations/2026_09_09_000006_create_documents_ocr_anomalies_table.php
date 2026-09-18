<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_request_id')->constrained('credit_requests')->cascadeOnDelete();
            $table->string('document_type', 80);
            $table->string('original_filename', 255);
            $table->string('file_path', 255);
            $table->string('mime_type', 100)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->string('status', 30)->default('UPLOADED');
            $table->timestamps();
        });

        Schema::create('document_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->text('extracted_text')->nullable();
            $table->string('extraction_status', 30)->default('COMPLETED');
            $table->decimal('extraction_confidence', 5, 2)->default(0.00);
            $table->json('extracted_data')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_request_id')->constrained('credit_requests')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('anomaly_type', 80);
            $table->string('severity', 30)->default('MEDIUM');
            $table->text('description')->nullable();
            $table->text('detected_value')->nullable();
            $table->text('expected_value')->nullable();
            $table->string('status', 30)->default('OPEN');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomalies');
        Schema::dropIfExists('document_extractions');
        Schema::dropIfExists('documents');
    }
};
