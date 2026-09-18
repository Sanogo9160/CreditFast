<?php

namespace App\Services;

use App\Enums\CreditRequestStatus;
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
            CreditRequestStatus::Draft->value => [CreditRequestStatus::Submitted->value, CreditRequestStatus::Rejected->value],
            CreditRequestStatus::Submitted->value => [CreditRequestStatus::Analysis->value, CreditRequestStatus::VerificationRequired->value, CreditRequestStatus::CreditReview->value, CreditRequestStatus::Committee->value, CreditRequestStatus::Rejected->value],
            CreditRequestStatus::Analysis->value => [CreditRequestStatus::VerificationRequired->value, CreditRequestStatus::CreditReview->value, CreditRequestStatus::Committee->value, CreditRequestStatus::Rejected->value],
            CreditRequestStatus::VerificationRequired->value => [CreditRequestStatus::Analysis->value, CreditRequestStatus::CreditReview->value, CreditRequestStatus::Committee->value, CreditRequestStatus::Rejected->value],
            CreditRequestStatus::CreditReview->value => [CreditRequestStatus::Committee->value, CreditRequestStatus::VerificationRequired->value, CreditRequestStatus::Rejected->value],
            CreditRequestStatus::Committee->value => [CreditRequestStatus::Approved->value, CreditRequestStatus::Rejected->value],
            CreditRequestStatus::Approved->value => [CreditRequestStatus::Disbursed->value],
            CreditRequestStatus::Rejected->value => [],
            CreditRequestStatus::Disbursed->value => [],
        ];

        return in_array($new->value, $allowedMap[$old->value] ?? []);
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
                "Votre demande #{$id} vous a été renvoyée afin que vous puissiez transmettre les pièces ou informations manquantes (par exemple un justificatif de domicile). Merci de vous connecter pour compléter votre dossier : cela nous permettra de poursuivre l’examen.",
                'COMPLEMENTS_REQUESTED',
            ],
            CreditRequestStatus::Analysis => [
                'Votre demande est en cours d’examen',
                "Votre demande #{$id} est actuellement étudiée par notre équipe. Nous vous tiendrons informé(e) de la suite.",
                'STATUS_UPDATE',
            ],
            CreditRequestStatus::CreditReview => [
                'Examen approfondi en cours',
                "Votre demande #{$id} fait l’objet d’un examen détaillé. Aucune décision n’est encore prise.",
                'STATUS_UPDATE',
            ],
            CreditRequestStatus::Committee => [
                'Votre demande est présentée au comité',
                "Votre demande #{$id} a été transmise au comité. La décision d’octroi reste humaine et vous sera communiquée.",
                'STATUS_UPDATE',
            ],
            CreditRequestStatus::Approved => [
                'Bonne nouvelle : votre crédit est accordé',
                "Votre demande #{$id} a été acceptée. Les fonds seront mis à disposition après le décaissement par l’agence.",
                'STATUS_UPDATE',
            ],
            CreditRequestStatus::Rejected => [
                'Décision concernant votre demande',
                "Après examen, votre demande #{$id} n’a pas pu être retenue cette fois-ci. Notre équipe reste à votre écoute pour en discuter et vous accompagner.",
                'STATUS_UPDATE',
            ],
            CreditRequestStatus::Disbursed => [
                'Vos fonds ont été mis à disposition',
                "Votre crédit lié à la demande #{$id} a été décaissé. Vous pouvez consulter le montant reçu et votre échéancier.",
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
            CreditRequestStatus::VerificationRequired => 'Dossier renvoyé au client pour pièces ou informations complémentaires.',
            default => "Passage à l’étape {$newStatus->value}",
        };
    }
}
