<?php

namespace App\Services;

use App\Enums\CreditRequestStatus;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\CreditRequest;
use App\Models\CreditStatusHistory;
use App\Models\Notification;
use App\Models\User;
use InvalidArgumentException;

class CreditWorkflowService
{
    /**
     * Transition a credit request to a new status.
     */
    public function transitionStatus(
        CreditRequest $creditRequest,
        CreditRequestStatus $newStatus,
        ?User $actor = null,
        ?string $comment = null
    ): CreditRequest {
        $oldStatus = $creditRequest->status;

        if ($oldStatus === $newStatus) {
            return $creditRequest;
        }

        if (! $this->isTransitionAllowed($oldStatus, $newStatus)) {
            throw new InvalidArgumentException(
                "Cette étape n’est pas possible pour le dossier dans son état actuel ({$oldStatus->value} → {$newStatus->value})."
            );
        }

        $creditRequest->status = $newStatus;
        if ($newStatus === CreditRequestStatus::Submitted && ! $creditRequest->submitted_at) {
            $creditRequest->submitted_at = now();
        }
        $creditRequest->save();

        CreditStatusHistory::create([
            'credit_request_id' => $creditRequest->id,
            'changed_by' => $actor?->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'comment' => $comment ?? $this->statusHistoryComment($newStatus),
        ]);

        if ($actor) {
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'CREDIT_REQUEST_STATUS_CHANGE',
                'entity_type' => CreditRequest::class,
                'entity_id' => $creditRequest->id,
                'details' => [
                    'old_status' => $oldStatus->value,
                    'new_status' => $newStatus->value,
                    'comment' => $comment,
                    'returned_to_client' => $newStatus === CreditRequestStatus::VerificationRequired,
                ],
                'ip_address' => request()->ip(),
            ]);
        }

        if ($creditRequest->client && $creditRequest->client->user) {
            [$title, $message, $type] = $this->clientNotificationCopy($creditRequest, $newStatus);

            Notification::create([
                'user_id' => $creditRequest->client->user->id,
                'title' => $title,
                'message' => $message,
                'type' => $type,
            ]);
        }

        return $creditRequest;
    }

    public function isTransitionAllowed(CreditRequestStatus $old, CreditRequestStatus $new): bool
    {
        $allowedMap = [
            CreditRequestStatus::Draft->value => [
                CreditRequestStatus::Submitted->value,
                CreditRequestStatus::Rejected->value,
            ],
            CreditRequestStatus::Submitted->value => [
                CreditRequestStatus::Received->value,
                CreditRequestStatus::UnderReview->value,
                CreditRequestStatus::InAnalysis->value,
                CreditRequestStatus::VerificationRequired->value,
                CreditRequestStatus::Rejected->value,
            ],
            CreditRequestStatus::Received->value => [
                CreditRequestStatus::UnderReview->value,
                CreditRequestStatus::InAnalysis->value,
                CreditRequestStatus::VerificationRequired->value,
                CreditRequestStatus::Rejected->value,
            ],
            CreditRequestStatus::UnderReview->value => [
                CreditRequestStatus::InAnalysis->value,
                CreditRequestStatus::VerificationRequired->value,
                CreditRequestStatus::Rejected->value,
            ],
            CreditRequestStatus::VerificationRequired->value => [
                CreditRequestStatus::Submitted->value,
                CreditRequestStatus::UnderReview->value,
                CreditRequestStatus::InAnalysis->value,
                CreditRequestStatus::Rejected->value,
            ],
            CreditRequestStatus::InAnalysis->value => [
                CreditRequestStatus::PendingAnalysis->value,
                CreditRequestStatus::PendingCommittee->value,
                CreditRequestStatus::VerificationRequired->value,
                CreditRequestStatus::Rejected->value,
            ],
            CreditRequestStatus::PendingAnalysis->value => [
                CreditRequestStatus::PendingCommittee->value,
                CreditRequestStatus::VerificationRequired->value,
                CreditRequestStatus::Rejected->value,
            ],
            CreditRequestStatus::PendingCommittee->value => [
                CreditRequestStatus::Committee->value,
                CreditRequestStatus::Approved->value,
                CreditRequestStatus::Amended->value,
                CreditRequestStatus::Adjourned->value,
                CreditRequestStatus::VerificationRequired->value,
                CreditRequestStatus::Rejected->value,
            ],
            CreditRequestStatus::Committee->value => [
                CreditRequestStatus::Approved->value,
                CreditRequestStatus::Amended->value,
                CreditRequestStatus::Adjourned->value,
                CreditRequestStatus::VerificationRequired->value,
                CreditRequestStatus::Rejected->value,
            ],
            CreditRequestStatus::Approved->value => [],
            CreditRequestStatus::Amended->value => [],
            CreditRequestStatus::Rejected->value => [],
            CreditRequestStatus::Adjourned->value => [],
        ];

        return in_array($new->value, $allowedMap[$old->value] ?? [], true);
    }

    public function assertActorOwnsStep(User $user, CreditRequest $creditRequest): void
    {
        if ($user->hasRole(RoleName::Admin)) {
            return;
        }

        if ($creditRequest->status->isClosed()) {
            abort(403, 'Ce dossier est clos. Cette étape est verrouillée.');
        }

        if ($creditRequest->status === CreditRequestStatus::Adjourned) {
            abort(403, 'Ce dossier est ajourné. Le vote est verrouillé.');
        }

        if ($user->hasRole(RoleName::CreditAgent)) {
            $owns = in_array($creditRequest->status, CreditRequestStatus::agentOwned(), true)
                && (int) $creditRequest->assigned_agent_id === (int) $user->id;

            if (! $owns) {
                abort(403, 'Ce dossier est verrouillé. Il n’est pas à votre étape ou ne vous est pas affecté.');
            }

            return;
        }

        if ($user->hasRole(RoleName::Analyst)) {
            if (! in_array($creditRequest->status, CreditRequestStatus::analystOwned(), true)) {
                abort(403, 'Ce dossier est verrouillé. Il n’est pas à l’étape d’analyse.');
            }

            if ($user->agency_code && $creditRequest->agency_code && $user->agency_code !== $creditRequest->agency_code) {
                abort(403, 'Ce dossier appartient à une autre agence.');
            }

            return;
        }

        if ($user->hasRole(RoleName::CommitteeMember)) {
            if (! in_array($creditRequest->status, CreditRequestStatus::committeeOwned(), true)) {
                abort(403, 'Ce dossier est verrouillé. Il n’est pas à l’étape du comité.');
            }

            if ($user->agency_code && $creditRequest->agency_code && $user->agency_code !== $creditRequest->agency_code) {
                abort(403, 'Ce dossier appartient à une autre agence.');
            }
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    protected function clientNotificationCopy(CreditRequest $creditRequest, CreditRequestStatus $newStatus): array
    {
        $id = $creditRequest->id;

        return match ($newStatus) {
            CreditRequestStatus::Submitted => [
                'Votre demande a bien été reçue',
                "Merci. Votre demande #{$id} a été transmise à notre équipe, qui va l’examiner.",
                'STATUS_UPDATE',
            ],
            CreditRequestStatus::VerificationRequired => [
                'Votre dossier vous est renvoyé',
                "Votre demande #{$id} vous a été renvoyée afin que vous puissiez transmettre les pièces ou informations manquantes. Merci de vous connecter pour compléter votre dossier.",
                'COMPLEMENTS_REQUESTED',
            ],
            CreditRequestStatus::InAnalysis, CreditRequestStatus::PendingAnalysis => [
                'Votre demande est en cours d’examen',
                "Votre demande #{$id} est actuellement étudiée par notre équipe. Nous vous tiendrons informé(e) de la suite.",
                'STATUS_UPDATE',
            ],
            CreditRequestStatus::PendingCommittee, CreditRequestStatus::Committee => [
                'Votre demande est présentée au comité',
                "Votre demande #{$id} a été transmise au comité. La décision d’octroi reste humaine et vous sera communiquée.",
                'STATUS_UPDATE',
            ],
            CreditRequestStatus::Approved, CreditRequestStatus::Amended => [
                'Bonne nouvelle : votre crédit est accordé',
                "Votre demande #{$id} a été acceptée. Les fonds ont été versés sur votre compte épargne.",
                'STATUS_UPDATE',
            ],
            CreditRequestStatus::Adjourned => [
                'Votre demande a été ajournée',
                "Votre demande #{$id} a été ajournée. Consultez le dossier pour savoir pourquoi et quoi faire ensuite.",
                'STATUS_UPDATE',
            ],
            CreditRequestStatus::Rejected => [
                'Décision concernant votre demande',
                "Après examen, votre demande #{$id} n’a pas pu être retenue cette fois-ci.",
                'STATUS_UPDATE',
            ],
            default => [
                'Mise à jour de votre demande',
                "Votre demande #{$id} a été mise à jour. Merci de vous connecter pour en prendre connaissance.",
                'STATUS_UPDATE',
            ],
        };
    }

    protected function statusHistoryComment(CreditRequestStatus $newStatus): string
    {
        return match ($newStatus) {
            CreditRequestStatus::VerificationRequired => 'Dossier renvoyé pour pièces ou informations complémentaires.',
            CreditRequestStatus::Adjourned => 'Dossier ajourné par le comité.',
            default => "Passage à l’étape {$newStatus->value}",
        };
    }
}
