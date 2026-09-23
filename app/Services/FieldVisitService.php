<?php

namespace App\Services;

use App\Enums\CreditRequestStatus;
use App\Enums\FieldVisitOutcome;
use App\Enums\FieldVisitStatus;
use App\Enums\FieldVisitType;
use App\Models\CreditRequest;
use App\Models\FieldVisit;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FieldVisitService
{
    public function __construct(protected AuditLogger $auditLogger) {}

    /**
     * @param  array{
     *     visit_type: string,
     *     scheduled_at: string,
     *     location_label?: string|null,
     *     latitude?: float|null,
     *     longitude?: float|null,
     *     purpose?: string|null
     * }  $data
     */
    public function schedule(CreditRequest $creditRequest, User $agent, array $data): FieldVisit
    {
        $this->assertSchedulable($creditRequest);

        $visit = FieldVisit::query()->create([
            'credit_request_id' => $creditRequest->id,
            'client_id' => $creditRequest->client_id,
            'agent_id' => $agent->id,
            'visit_type' => FieldVisitType::from($data['visit_type']),
            'status' => FieldVisitStatus::Scheduled,
            'scheduled_at' => $data['scheduled_at'],
            'location_label' => $data['location_label'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'purpose' => $data['purpose'] ?? null,
        ]);

        $this->auditLogger->record($agent, 'FIELD_VISIT_SCHEDULED', FieldVisit::class, $visit->id, [
            'credit_request_id' => $creditRequest->id,
            'visit_type' => $visit->visit_type->value,
            'scheduled_at' => $visit->scheduled_at?->toIso8601String(),
        ]);

        $this->notifyClient(
            $creditRequest,
            'Visite terrain planifiée',
            'Un rendez-vous de vérification sur le terrain a été planifié pour votre dossier #'.$creditRequest->id.'.',
            'FIELD_VISIT'
        );

        return $visit->load(['agent', 'client.user', 'creditRequest']);
    }

    /**
     * @param  array{
     *     visit_type?: string,
     *     scheduled_at?: string,
     *     location_label?: string|null,
     *     latitude?: float|null,
     *     longitude?: float|null,
     *     purpose?: string|null
     * }  $data
     */
    public function update(FieldVisit $visit, User $actor, array $data): FieldVisit
    {
        if (! $visit->isOpen()) {
            throw new InvalidArgumentException('Seule une visite planifiée ou en cours peut être modifiée.');
        }

        if (isset($data['visit_type'])) {
            $visit->visit_type = FieldVisitType::from($data['visit_type']);
        }

        foreach (['scheduled_at', 'location_label', 'latitude', 'longitude', 'purpose'] as $field) {
            if (array_key_exists($field, $data)) {
                $visit->{$field} = $data[$field];
            }
        }

        $visit->save();

        $this->auditLogger->record($actor, 'FIELD_VISIT_UPDATED', FieldVisit::class, $visit->id, [
            'status' => $visit->status->value,
        ]);

        return $visit->fresh(['agent', 'client.user', 'creditRequest']);
    }

    public function start(FieldVisit $visit, User $actor): FieldVisit
    {
        if ($visit->status !== FieldVisitStatus::Scheduled) {
            throw new InvalidArgumentException('Seule une visite planifiée peut être démarrée.');
        }

        $visit->update([
            'status' => FieldVisitStatus::InProgress,
            'started_at' => now(),
        ]);

        $this->auditLogger->record($actor, 'FIELD_VISIT_STARTED', FieldVisit::class, $visit->id);

        return $visit->fresh(['agent', 'client.user', 'creditRequest']);
    }

    /**
     * @param  array{
     *     outcome: string,
     *     findings: string,
     *     recommendations?: string|null,
     *     location_label?: string|null,
     *     latitude?: float|null,
     *     longitude?: float|null
     * }  $data
     */
    public function complete(FieldVisit $visit, User $actor, array $data): FieldVisit
    {
        if (! in_array($visit->status, [FieldVisitStatus::Scheduled, FieldVisitStatus::InProgress], true)) {
            throw new InvalidArgumentException('Cette visite ne peut plus être clôturée.');
        }

        return DB::transaction(function () use ($visit, $actor, $data): FieldVisit {
            $visit->fill([
                'status' => FieldVisitStatus::Completed,
                'outcome' => FieldVisitOutcome::from($data['outcome']),
                'findings' => $data['findings'],
                'recommendations' => $data['recommendations'] ?? null,
                'location_label' => $data['location_label'] ?? $visit->location_label,
                'latitude' => $data['latitude'] ?? $visit->latitude,
                'longitude' => $data['longitude'] ?? $visit->longitude,
                'started_at' => $visit->started_at ?? now(),
                'completed_at' => now(),
            ])->save();

            $this->auditLogger->record($actor, 'FIELD_VISIT_COMPLETED', FieldVisit::class, $visit->id, [
                'outcome' => $visit->outcome?->value,
                'credit_request_id' => $visit->credit_request_id,
            ]);

            $this->notifyClient(
                $visit->creditRequest()->with('client.user')->first() ?? $visit->creditRequest,
                'Visite terrain terminée',
                'Le rapport de visite terrain de votre dossier #'.$visit->credit_request_id.' a été enregistré.',
                'FIELD_VISIT'
            );

            return $visit->fresh(['agent', 'client.user', 'creditRequest']);
        });
    }

    /**
     * @param  array{reason: string, as_no_show?: bool}  $data
     */
    public function cancel(FieldVisit $visit, User $actor, array $data): FieldVisit
    {
        if (! $visit->isOpen()) {
            throw new InvalidArgumentException('Cette visite est déjà clôturée.');
        }

        $asNoShow = (bool) ($data['as_no_show'] ?? false);

        $visit->update([
            'status' => $asNoShow ? FieldVisitStatus::NoShow : FieldVisitStatus::Cancelled,
            'cancel_reason' => $data['reason'],
            'completed_at' => now(),
        ]);

        $this->auditLogger->record($actor, $asNoShow ? 'FIELD_VISIT_NO_SHOW' : 'FIELD_VISIT_CANCELLED', FieldVisit::class, $visit->id, [
            'reason' => $data['reason'],
        ]);

        return $visit->fresh(['agent', 'client.user', 'creditRequest']);
    }

    protected function assertSchedulable(CreditRequest $creditRequest): void
    {
        $allowed = [
            CreditRequestStatus::Submitted,
            CreditRequestStatus::VerificationRequired,
            CreditRequestStatus::InAnalysis,
            CreditRequestStatus::PendingCommittee,
        ];

        if (! in_array($creditRequest->status, $allowed, true)) {
            throw new InvalidArgumentException(
                'Une visite terrain n’est possible que pour un dossier soumis, en complément, en analyse ou en revue.'
            );
        }

        if ($creditRequest->client_id === null) {
            throw new InvalidArgumentException('Le dossier n’a pas de client associé.');
        }
    }

    protected function notifyClient(?CreditRequest $creditRequest, string $title, string $message, string $type): void
    {
        $userId = $creditRequest?->client?->user?->id;

        if ($userId === null) {
            return;
        }

        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
        ]);
    }
}
