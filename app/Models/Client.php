<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Enums\KycStatus;
use App\Enums\LegalForm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'client_number',
        'client_type',
        'company_name',
        'trade_name',
        'registration_number',
        'tax_id',
        'rccm_number',
        'receipt_number',
        'inps_number',
        'legal_form',
        'date_of_birth',
        'address',
        'city',
        'residential_zone',
        'occupation',
        'kyc_status',
        'institution_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'client_type' => ClientType::class,
            'legal_form' => LegalForm::class,
            'date_of_birth' => 'date',
            'kyc_status' => KycStatus::class,
            'institution_verified_at' => 'datetime',
        ];
    }

    public function isLegalEntity(): bool
    {
        return $this->client_type === ClientType::LegalEntity;
    }

    public function isPhysicalPerson(): bool
    {
        return $this->client_type === ClientType::PhysicalPerson;
    }

    /**
     * Libellé d’affichage : raison sociale (PM) ou nom du titulaire (PP).
     */
    public function displayName(): string
    {
        if ($this->isLegalEntity()) {
            return (string) ($this->trade_name ?: $this->company_name ?: 'Entreprise');
        }

        return trim(($this->user?->first_name ?? '').' '.($this->user?->last_name ?? '')) ?: 'Client';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function financialProfile(): HasOne
    {
        return $this->hasOne(FinancialProfile::class);
    }

    public function financialAccounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class);
    }

    public function bankAccountApplications(): HasMany
    {
        return $this->hasMany(BankAccountApplication::class);
    }

    public function savingsHistories(): HasMany
    {
        return $this->hasMany(SavingsHistory::class);
    }

    public function creditRequests(): HasMany
    {
        return $this->hasMany(CreditRequest::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
