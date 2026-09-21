<?php

namespace App\Services;

use App\Models\CreditRequest;
use App\Models\Document;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ActivityVitalityScorer
{
    /**
     * @var list<string>
     */
    public const REVENUE_DOCUMENT_TYPES = [
        'PREUVE_REVENU',
        'ATTESTATION_REVENU',
        'BULLETIN_PAIE',
        'RELEVE_BANCAIRE',
    ];

    /**
     * @return array{score: float, explanation: string}
     */
    public function score(CreditRequest $request): array
    {
        $recency = $this->recencyScore($request);
        $rhythm = $this->rhythmScore($request);
        $fit = $this->fitScore($request);

        $score = round((0.40 * $recency) + (0.35 * $rhythm) + (0.25 * $fit), 2);

        return [
            'score' => min(100.0, max(0.0, $score)),
            'explanation' => "Vitalité du cycle : récence {$recency}/100, rythme {$rhythm}/100, adéquation durée/métier {$fit}/100. Absence de preuve récente ≠ 0 : note neutre 50 sur le signal manquant.",
        ];
    }

    /**
     * Preuve économique la plus récente (pièce de revenu ou mouvement de compte).
     * Aucune preuve → 50, jamais 0.
     */
    public function recencyScore(CreditRequest $request): float
    {
        $latest = $this->evidenceDates($request)->max();

        if (! $latest instanceof CarbonInterface) {
            return 50.0;
        }

        $days = (int) round(abs($latest->diffInDays(now())));

        return match (true) {
            $days <= 30 => 95.0,
            $days <= 60 => 80.0,
            $days <= 90 => 60.0,
            default => 35.0,
        };
    }

    /**
     * Nombre d’événements économiques sur 90 jours (pièces revenu + mouvements).
     * Zéro événement → 50, jamais 0.
     */
    public function rhythmScore(CreditRequest $request): float
    {
        $cutoff = now()->subDays(90);
        $events = $this->evidenceDates($request)
            ->filter(fn (CarbonInterface $date): bool => $date->greaterThanOrEqualTo($cutoff))
            ->count();

        return match (true) {
            $events >= 4 => 90.0,
            $events >= 2 => 70.0,
            $events === 1 => 55.0,
            default => 50.0,
        };
    }

    /**
     * Durée du crédit vs cycle du métier (stock 3–6 mois, équipement 10–18, sinon 6–12).
     * Sans objet ni activité → 50.
     */
    public function fitScore(CreditRequest $request): float
    {
        $haystack = $this->activityHaystack($request);

        if ($haystack === '') {
            return 50.0;
        }

        $duration = (int) $request->duration_months;
        [$minMonths, $maxMonths] = $this->idealCycleMonths($haystack);

        if ($duration >= $minMonths && $duration <= $maxMonths) {
            return 90.0;
        }

        if ($duration >= ($minMonths - 3) && $duration <= ($maxMonths + 3)) {
            return 70.0;
        }

        return 40.0;
    }

    /**
     * @return Collection<int, CarbonInterface>
     */
    protected function evidenceDates(CreditRequest $request): Collection
    {
        $documentDates = $request->documents
            ->filter(fn (Document $document): bool => in_array(Str::upper($document->document_type), self::REVENUE_DOCUMENT_TYPES, true))
            ->map(fn (Document $document): ?CarbonInterface => $document->uploaded_at ?? $document->created_at)
            ->filter();

        $transactionDates = $request->client?->financialAccounts
            ->flatMap(fn ($account): Collection => $account->transactions)
            ->map(fn ($transaction): ?CarbonInterface => $transaction->transaction_date)
            ->filter() ?? collect();

        return $documentDates->concat($transactionDates)->values();
    }

    protected function activityHaystack(CreditRequest $request): string
    {
        $activity = $request->activity;
        $parts = array_filter([
            $request->purpose,
            $activity?->activity_type,
            $activity?->sector,
            $activity?->description,
        ]);

        if ($parts === []) {
            return '';
        }

        return Str::lower(Str::ascii(implode(' ', $parts)));
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function idealCycleMonths(string $haystack): array
    {
        if (Str::contains($haystack, ['stock', 'recolte', 'cereale', 'saison', 'inventaire'])) {
            return [3, 6];
        }

        if (Str::contains($haystack, ['machine', 'equipement', 'atelier', 'materiel', 'couture', 'outillage'])) {
            return [10, 18];
        }

        return [6, 12];
    }
}
