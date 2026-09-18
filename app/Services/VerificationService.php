<?php

namespace App\Services;

use App\Enums\GuaranteeVerificationStatus;
use App\Enums\KycStatus;
use App\Models\Client;
use App\Models\Guarantee;
use App\Models\KycDocument;
use App\Models\User;
use InvalidArgumentException;

class VerificationService
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function verifyKycDocument(
        KycDocument $document,
        User $actor,
        KycStatus $decision,
        ?string $rejectionReason = null
    ): KycDocument {
        if ($decision === KycStatus::Rejected && blank($rejectionReason)) {
            throw new InvalidArgumentException('Merci d’indiquer un motif pour cette décision, afin que le client puisse la comprendre.');
        }

        $document->update([
            'status' => $decision,
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'rejection_reason' => $decision === KycStatus::Rejected ? $rejectionReason : null,
        ]);

        $this->refreshClientKycStatus($document->client()->firstOrFail());

        $this->auditLogger->record($actor, 'KYC_DOCUMENT_VERIFIED', KycDocument::class, $document->id, [
            'decision' => $decision->value,
            'client_id' => $document->client_id,
        ]);

        return $document->fresh(['client', 'verifier']);
    }

    public function refreshClientKycStatus(Client $client): Client
    {
        $documents = $client->kycDocuments()->get();

        if ($documents->isEmpty()) {
            $client->update([
                'kyc_status' => KycStatus::Pending,
                'institution_verified_at' => null,
            ]);

            return $client->fresh();
        }

        if ($documents->contains(fn (KycDocument $document): bool => $document->status === KycStatus::Rejected)) {
            $client->update([
                'kyc_status' => KycStatus::Rejected,
                'institution_verified_at' => null,
            ]);

            return $client->fresh();
        }

        if ($documents->every(fn (KycDocument $document): bool => $document->status === KycStatus::Verified)) {
            $client->update([
                'kyc_status' => KycStatus::Verified,
                'institution_verified_at' => now(),
            ]);

            return $client->fresh();
        }

        $client->update([
            'kyc_status' => KycStatus::Pending,
            'institution_verified_at' => null,
        ]);

        return $client->fresh();
    }

    public function verifyGuarantee(
        Guarantee $guarantee,
        User $actor,
        GuaranteeVerificationStatus $status,
        ?float $verifiedValue = null
    ): Guarantee {
        $guarantee->update([
            'verification_status' => $status->value,
            'verified_value' => $status === GuaranteeVerificationStatus::Verified
                ? ($verifiedValue ?? $guarantee->declared_value)
                : $verifiedValue,
            'verified_by' => $actor->id,
            'verified_at' => now(),
        ]);

        $this->auditLogger->record($actor, 'GUARANTEE_VERIFIED', Guarantee::class, $guarantee->id, [
            'status' => $status->value,
            'verified_value' => $guarantee->verified_value,
            'credit_request_id' => $guarantee->credit_request_id,
        ]);

        return $guarantee->fresh(['verifier', 'creditRequest']);
    }
}
