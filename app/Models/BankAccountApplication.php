<?php

namespace App\Models;

use App\Enums\BankAccountApplicationStatus;
use App\Enums\BankAccountPartyRole;
use App\Enums\Gender;
use App\Enums\LegalForm;
use App\Enums\MaritalStatus;
use Database\Factories\BankAccountApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccountApplication extends Model
{
    /** @use HasFactory<BankAccountApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'caisse_id',
        'guichet_id',
        'cash_desk_id',
        'status',
        'identity_verified',
        'identity_verified_by',
        'identity_verified_at',
        'review_comment',
        'reviewed_by',
        'reviewed_at',
        'financial_account_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'city',
        'residential_zone',
        'address',
        'date_of_birth',
        'birth_place',
        'nationality',
        'country_of_origin',
        'gender',
        'father_name',
        'mother_name',
        'marital_status',
        'profession',
        'activity_sector',
        'id_document_type',
        'id_document_number',
        'id_issued_at',
        'id_expires_at',
        'id_issued_place',
        'economic_status',
        'employer_name',
        'employer_address',
        'estimated_monthly_income',
        'funds_origin',
        'account_main_usage',
        'has_certified_id_copy',
        'has_domicile_proof',
        'has_income_proof',
        'company_name',
        'legal_form',
        'tax_id',
        'rccm_number',
        'receipt_number',
        'inps_number',
        'head_office_address',
        'company_email',
        'company_phone',
        'main_activity',
        'annual_turnover',
        'has_ubo_over_25',
        'indirect_control_description',
        'initial_contribution_origin',
        'planned_operations_nature',
        'has_nif_copy',
        'has_rccm_copy',
        'has_approval_or_receipt_copy',
        'has_statutes_copy',
        'has_mandate_copy',
        'has_directors_id_copies',
        'has_ubo_id_copies',
        'adhesion_date',
        'adhesion_place',
        'client_signature_path',
    ];

    protected function casts(): array
    {
        return [
            'status' => BankAccountApplicationStatus::class,
            'identity_verified' => 'boolean',
            'identity_verified_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'date_of_birth' => 'date',
            'id_issued_at' => 'date',
            'id_expires_at' => 'date',
            'adhesion_date' => 'date',
            'gender' => Gender::class,
            'marital_status' => MaritalStatus::class,
            'legal_form' => LegalForm::class,
            'estimated_monthly_income' => 'decimal:2',
            'annual_turnover' => 'decimal:2',
            'has_certified_id_copy' => 'boolean',
            'has_domicile_proof' => 'boolean',
            'has_income_proof' => 'boolean',
            'has_ubo_over_25' => 'boolean',
            'has_nif_copy' => 'boolean',
            'has_rccm_copy' => 'boolean',
            'has_approval_or_receipt_copy' => 'boolean',
            'has_statutes_copy' => 'boolean',
            'has_mandate_copy' => 'boolean',
            'has_directors_id_copies' => 'boolean',
            'has_ubo_id_copies' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function caisse(): BelongsTo
    {
        return $this->belongsTo(Caisse::class);
    }

    public function guichet(): BelongsTo
    {
        return $this->belongsTo(Guichet::class);
    }

    public function cashDesk(): BelongsTo
    {
        return $this->belongsTo(CashDesk::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function parties(): HasMany
    {
        return $this->hasMany(BankAccountApplicationParty::class)->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BankAccountApplicationDocument::class);
    }

    public function signatories(): HasMany
    {
        return $this->parties()->where('role', BankAccountPartyRole::Signatory);
    }

    public function beneficialOwners(): HasMany
    {
        return $this->parties()->where('role', BankAccountPartyRole::BeneficialOwner);
    }
}
