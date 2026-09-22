<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_account_application_parties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bank_account_application_id');
            $table->string('role', 30);
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->date('date_of_birth')->nullable();
            $table->string('birth_place', 150)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('function_in_company', 150)->nullable();
            $table->string('id_document_type', 50)->nullable();
            $table->string('id_document_number', 80)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('link_with_company', 255)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('signature_path', 255)->nullable();
            $table->timestamps();

            $table->foreign('bank_account_application_id', 'baa_parties_app_fk')
                ->references('id')
                ->on('bank_account_applications')
                ->cascadeOnDelete();
            $table->index(['bank_account_application_id', 'role'], 'baa_parties_app_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_account_application_parties');
    }
};
