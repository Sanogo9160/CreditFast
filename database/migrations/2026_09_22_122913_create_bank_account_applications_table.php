<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_account_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('caisse_id')->constrained('caisses')->restrictOnDelete();
            $table->foreignId('guichet_id')->constrained('guichets')->restrictOnDelete();
            $table->foreignId('cash_desk_id')->nullable()->constrained('cash_desks')->nullOnDelete();
            $table->string('status', 30)->default('DRAFT');
            $table->text('review_comment')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();

            // Snapshot contact
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('residential_zone', 150)->nullable();
            $table->string('address', 255)->nullable();

            // PP identity
            $table->date('date_of_birth')->nullable();
            $table->string('birth_place', 150)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('country_of_origin', 100)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('father_name', 150)->nullable();
            $table->string('mother_name', 150)->nullable();
            $table->string('marital_status', 30)->nullable();
            $table->string('profession', 150)->nullable();
            $table->string('activity_sector', 150)->nullable();

            // PP ID document
            $table->string('id_document_type', 50)->nullable();
            $table->string('id_document_number', 80)->nullable();
            $table->date('id_issued_at')->nullable();
            $table->date('id_expires_at')->nullable();
            $table->string('id_issued_place', 150)->nullable();

            // PP economic activity
            $table->string('economic_status', 100)->nullable();
            $table->string('employer_name', 150)->nullable();
            $table->string('employer_address', 255)->nullable();
            $table->decimal('estimated_monthly_income', 15, 2)->nullable();
            $table->string('funds_origin', 255)->nullable();
            $table->string('account_main_usage', 255)->nullable();

            // PP document checklist
            $table->boolean('has_certified_id_copy')->default(false);
            $table->boolean('has_domicile_proof')->default(false);
            $table->boolean('has_income_proof')->default(false);

            // PM company
            $table->string('company_name', 200)->nullable();
            $table->string('legal_form', 30)->nullable();
            $table->string('tax_id', 100)->nullable();
            $table->string('rccm_number', 100)->nullable();
            $table->string('receipt_number', 100)->nullable();
            $table->string('inps_number', 100)->nullable();
            $table->string('head_office_address', 255)->nullable();
            $table->string('company_email', 150)->nullable();
            $table->string('company_phone', 30)->nullable();
            $table->string('main_activity', 200)->nullable();
            $table->decimal('annual_turnover', 15, 2)->nullable();

            // PM UBO flag + funds
            $table->boolean('has_ubo_over_25')->nullable();
            $table->text('indirect_control_description')->nullable();
            $table->string('initial_contribution_origin', 255)->nullable();
            $table->string('planned_operations_nature', 255)->nullable();

            // PM document checklist
            $table->boolean('has_nif_copy')->default(false);
            $table->boolean('has_rccm_copy')->default(false);
            $table->boolean('has_approval_or_receipt_copy')->default(false);
            $table->boolean('has_statutes_copy')->default(false);
            $table->boolean('has_mandate_copy')->default(false);
            $table->boolean('has_directors_id_copies')->default(false);
            $table->boolean('has_ubo_id_copies')->default(false);

            $table->date('adhesion_date')->nullable();
            $table->string('adhesion_place', 150)->nullable();
            $table->string('client_signature_path', 255)->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_account_applications');
    }
};
