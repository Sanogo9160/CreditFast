<?php

namespace App\Services;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyStatus;
use App\Enums\CreditRequestStatus;
use App\Enums\FactorType;
use App\Enums\LoanStatus;
use App\Enums\RepaymentCapacityStatus;
use App\Enums\ScoringMode;
use App\Enums\ScoringRecommendation;
use App\Models\CreditAnalysis;
use App\Models\CreditRequest;
use App\Models\CreditScoreFactor;
use App\Models\ScoringModel;
use App\Models\ScoringRule;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Moteur de scoring V1 — aide à la décision, jamais décision d’octroi.
 *
 * Architecture  :
 * SCORING MODEL → SCORING RULES → CREDIT ANALYSIS → SCORE FACTORS
 *
 * ## Prérequis métier (IMF)
 * Une demande n’est éligible que si le client dispose d’au moins un compte
 * en banque ou en institution (table `financial_accounts`). Le mode Cold Start
 * a été retiré : absence de compte ≠ dossier scoré autrement.
 *
 * ## Mode
 * STANDARD uniquement : historique d’épargne / crédit / compte institutionnel.
 *
 * ## Sous-notes (0 à 100) — barèmes de prototype, non officiels IMF
 * - repayment_capacity, income_consistency, expense, activity, activity_vitality,
 *   document, savings, credit_history, guarantee, residential_zone (souvent inactif).
 *
 * ## Pondérations V1 (règles ACTIVE — zone résidentielle INACTIVE, poids 0)
 * STANDARD — somme utile = 100 % :
 *   repayment_capacity 25 %, activity_vitality 15 %, income_consistency 10 %,
 *   activity 5 %, expense 10 %, document 10 %, savings 10 %, credit_history 10 %, guarantee 5 %.
 *
 * ## Note globale
 * Chaque facteur INCLUS contribue : score × (poids / 100), renormalisé si besoin.
 * Puis **plafond de prudence** (`config('credit.scoring.overall_score_ceiling')`, défaut 95) :
 * le score global n’atteint jamais 100 %.
 *
 * ## Recommandation (aide seulement)
 * FAVORABLE si score ≥ 70 et capacité SUFFICIENT ; RESERVED si ≥ 50 et SUFFICIENT ;
 * sinon UNFAVORABLE. Le comité décide.
 */
class CreditScoringEngine
{
    public function __construct(
        protected AnomalyDetectionService $anomalyService,
        protected CreditWorkflowService $workflowService,
        protected ActivityVitalityScorer $activityVitalityScorer,
        protected InterestRateService $interestRates,
    ) {}

    /**
     * Calcule l’analyse explicable d’une demande : anomalies, modèle STANDARD,
     * sous-notes 0–100, agrégation pondérée plafonnée, confiance et recommandation.
     */
    public function evaluateCreditRequest(CreditRequest $creditRequest): CreditAnalysis
    {
        $creditRequest->loadMissing([
            'client.user',
            'client.financialProfile',
            'client.financialAccounts.transactions',
            'client.savingsHistories',
            'client.loans.repayments',
            'activity',
            'documents.extraction',
            'documents.validations',
            'anomalies',
            'guarantees',
        ]);

        if ($creditRequest->client->financialAccounts->isEmpty()) {
            throw new RuntimeException(
                'Scoring impossible : le client doit disposer d’un compte en banque ou en institution.'
            );
        }

        $this->anomalyService->detectAnomalies($creditRequest);
        $creditRequest->load('anomalies');

        $scoringMode = $this->resolveScoringMode($creditRequest);
        $model = $this->resolveActiveModel($scoringMode);

        return DB::transaction(function () use ($creditRequest, $model, $scoringMode): CreditAnalysis {
            $factorResults = $this->calculateFactorScores($creditRequest, $model, $scoringMode);

            $overallScore = $this->aggregateOverallScore($factorResults);
            $proposedRate = $this->interestRates->proposeFromScore($overallScore);
            $confidenceScore = $this->calculateConfidenceScore($creditRequest);
            $repaymentCapacity = $creditRequest->repayment_capacity_status;
            $recommendation = match (true) {
                $overallScore >= 70.0 && $repaymentCapacity === RepaymentCapacityStatus::Sufficient => ScoringRecommendation::Favorable,
                $overallScore >= 50.0 && $repaymentCapacity === RepaymentCapacityStatus::Sufficient => ScoringRecommendation::Reserved,
                default => ScoringRecommendation::Unfavorable,
            };

            $analysis = CreditAnalysis::create([
                'credit_request_id' => $creditRequest->id,
                'scoring_model_id' => $model->id,
                'declared_income' => $creditRequest->declared_monthly_income,
                'documented_income' => $this->resolveDocumentedIncome($creditRequest),
                'income_consistency_score' => $factorResults[FactorType::IncomeConsistency->value]['score'] ?? 0,
                'expense_score' => $factorResults[FactorType::Expense->value]['score'] ?? 0,
                'activity_score' => $factorResults[FactorType::Activity->value]['score'] ?? 0,
                'activity_vitality_score' => $factorResults[FactorType::ActivityVitality->value]['score'] ?? 0,
                'document_score' => $factorResults[FactorType::Document->value]['score'] ?? 0,
                'savings_score' => $factorResults[FactorType::Savings->value]['score'] ?? 0,
                'credit_history_score' => $factorResults[FactorType::CreditHistory->value]['score'] ?? 0,
                'guarantee_score' => $factorResults[FactorType::Guarantee->value]['score'] ?? 0,
                'repayment_capacity_score' => $factorResults[FactorType::RepaymentCapacity->value]['score'] ?? 0,
                'residential_zone_score' => $factorResults[FactorType::ResidentialZone->value]['score'] ?? 0,
                'overall_score' => $overallScore,
                'proposed_annual_interest_rate' => $proposedRate,
                'confidence_score' => $confidenceScore,
                'recommendation' => $recommendation,
                'analysis_summary' => $this->generateAnalysisSummary($creditRequest, $model, $overallScore, $recommendation, $proposedRate),
            ]);

            foreach ($factorResults as $type => $data) {
                if (! ($data['included'] ?? false)) {
                    continue;
                }

                CreditScoreFactor::create([
                    'credit_analysis_id' => $analysis->id,
                    'scoring_rule_id' => $data['rule_id'] ?? null,
                    'factor_name' => $data['name'],
                    'factor_type' => $type,
                    'score' => $data['score'],
                    'weight' => $data['weight'],
                    'explanation' => $data['explanation'],
                ]);
            }

            if ($creditRequest->status === CreditRequestStatus::Submitted) {
                $this->workflowService->transitionStatus(
                    $creditRequest,
                    CreditRequestStatus::Analysis,
                    $creditRequest->client?->user,
                    'Passage en analyse automatique après scoring'
                );
            }

            return $analysis->load('factors', 'scoringModel');
        });
    }

    /**
     * Unique mode conservé : STANDARD (compte institutionnel obligatoire en amont).
     */
    public function resolveScoringMode(CreditRequest $creditRequest): ScoringMode
    {
        return ScoringMode::Standard;
    }

    /**
     * Charge le modèle ACTIVE STANDARD et ses règles ACTIVE.
     */
    protected function resolveActiveModel(ScoringMode $scoringMode): ScoringModel
    {
        $model = ScoringModel::query()
            ->active()
            ->where('scoring_mode', $scoringMode)
            ->with(['rules' => function ($query): void {
                $query->where('status', 'ACTIVE')->orderBy('priority')->orderBy('id');
            }])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if (! $model) {
            throw new RuntimeException("Aucun modèle de scoring actif pour le mode {$scoringMode->value}.");
        }

        return $model;
    }

    /**
     * Combine les sous-notes incluses puis applique le plafond de prudence (< 100).
     *
     * @param  array<string, array<string, mixed>>  $factorResults
     */
    protected function aggregateOverallScore(array $factorResults): float
    {
        $weighted = 0.0;
        $totalWeight = 0.0;

        foreach ($factorResults as $factor) {
            if (! ($factor['included'] ?? false)) {
                continue;
            }

            $weight = (float) $factor['weight'];
            $weighted += (float) $factor['score'] * ($weight / 100);
            $totalWeight += $weight;
        }

        if ($totalWeight > 0 && abs($totalWeight - 100.0) > 0.01) {
            $weighted = $weighted / ($totalWeight / 100);
        }

        $ceiling = (float) config('credit.scoring.overall_score_ceiling', 95.0);
        $ceiling = min(99.99, max(1.0, $ceiling));

        return round(min($ceiling, max(0.0, $weighted)), 2);
    }

    /**
     * Applique les poids du modèle actif : un facteur n’entre dans la note globale
     * que s’il existe une règle ACTIVE de même `factor_type`.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function calculateFactorScores(CreditRequest $request, ScoringModel $model, ScoringMode $mode): array
    {
        $computed = $this->computeRawFactorScores($request);
        $results = [];

        foreach ($computed as $type => $data) {
            $rule = $model->rules->first(
                fn (ScoringRule $scoringRule): bool => $scoringRule->factor_type->value === $type
            );

            $results[$type] = array_merge($data, [
                'included' => $rule !== null,
                'weight' => $rule ? (float) $rule->weight : 0.0,
                'rule_id' => $rule?->id,
            ]);
        }

        return $results;
    }

    /**
     * Calcule chaque sous-note 0–100 selon les barèmes de prototype.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function computeRawFactorScores(CreditRequest $request): array
    {
        $client = $request->client;
        $profile = $client->financialProfile;
        $activity = $request->activity;
        $vitality = $this->activityVitalityScorer->score($request);

        $disposable = (float) ($request->disposable_income ?? $profile?->disposable_income ?? 0);
        $monthlyPayment = (float) $request->estimated_monthly_payment;
        $capRatio = $monthlyPayment > 0 ? ($disposable / $monthlyPayment) : 1.0;

        $capacityScore = match (true) {
            $capRatio >= 2.0 => 95.0,
            $capRatio >= 1.5 => 85.0,
            $capRatio >= 1.2 => 70.0,
            $capRatio >= 1.0 => 55.0,
            default => 25.0,
        };

        $declaredIncome = (float) $request->declared_monthly_income;
        $activityRevenue = (float) ($activity?->monthly_revenue ?? 0);
        $ocrIncome = $this->resolveOcrDocumentedIncome($request);
        $referenceIncome = $ocrIncome ?? ($activityRevenue > 0 ? $activityRevenue : 0.0);
        $diffPercent = ($declaredIncome > 0 && $referenceIncome > 0)
            ? abs($declaredIncome - $referenceIncome) / $declaredIncome
            : 0;

        $incomeConsistencyScore = match (true) {
            $referenceIncome <= 0 => 50.0,
            $diffPercent <= 0.05 => 95.0,
            $diffPercent <= 0.15 => 80.0,
            $diffPercent <= 0.25 => 60.0,
            default => 35.0,
        };
        $incomeExplanation = $ocrIncome !== null
            ? 'Écart entre revenu déclaré et montant lu sur justificatif (OCR ≠ authenticité).'
            : 'Écart entre revenu déclaré et revenu d’activité.';

        $totalIncome = (float) ($profile?->monthly_income ?? $declaredIncome);
        $totalExpenses = (float) ($profile?->monthly_expenses ?? $request->declared_monthly_expenses);
        $expenseRatio = $totalIncome > 0 ? ($totalExpenses / $totalIncome) : 0.8;

        $expenseScore = match (true) {
            $expenseRatio <= 0.30 => 95.0,
            $expenseRatio <= 0.50 => 80.0,
            $expenseRatio <= 0.70 => 60.0,
            default => 30.0,
        };

        $activityScore = 60.0;
        if ($activity) {
            $seniorityMonths = $activity->start_date ? now()->diffInMonths($activity->start_date) : 0;
            $activityScore = match (true) {
                $seniorityMonths >= 36 => 95.0,
                $seniorityMonths >= 24 => 85.0,
                $seniorityMonths >= 12 => 70.0,
                default => 50.0,
            };
        }

        $docCount = $request->documents->count();
        $openAnomaliesCount = $request->anomalies->where('status', AnomalyStatus::Open)->count();
        $criticalAnomalies = $request->anomalies
            ->where('status', AnomalyStatus::Open)
            ->where('severity', AnomalySeverity::Critical)
            ->count();
        $documentScore = max(10.0, 90.0 - ($openAnomaliesCount * 15.0) - ($criticalAnomalies * 10.0) + ($docCount * 5.0));
        $documentScore = min(100.0, $documentScore);

        $savingsHistories = $client->savingsHistories;
        $savingsAvailable = $savingsHistories->isNotEmpty();
        $savingsScore = 0.0;
        $savingsExplanation = 'Aucune donnée d’épargne institutionnelle exploitée pour ce calcul (compte requis, historique d’épargne encore mince).';

        if ($savingsAvailable) {
            $avgBalance = (float) $savingsHistories->avg('average_balance');
            $depositCount = (int) $savingsHistories->sum('deposit_count');
            $requestedAmount = (float) $request->requested_amount;
            $ratio = $requestedAmount > 0 ? ($avgBalance / $requestedAmount) : 0;

            $balanceScore = match (true) {
                $ratio >= 0.5 => 95.0,
                $ratio >= 0.2 => 80.0,
                $ratio >= 0.1 => 65.0,
                default => 45.0,
            };

            $regularityBonus = $depositCount >= 12 ? 5.0 : ($depositCount >= 6 ? 2.0 : 0.0);
            $savingsScore = min(100.0, $balanceScore + $regularityBonus);
            $savingsExplanation = "Historique d’épargne disponible (solde moyen {$avgBalance} FCFA, {$depositCount} dépôts).";
        }

        $loans = $client->loans;
        $creditHistoryAvailable = $loans->isNotEmpty();
        $creditHistoryScore = 0.0;
        $creditHistoryExplanation = 'Aucun crédit antérieur connu — le compte institutionnel existe, sans historique de remboursement.';

        if ($creditHistoryAvailable) {
            $hasDefault = $loans->contains(fn ($loan): bool => $loan->status === LoanStatus::Defaulted);
            if ($hasDefault) {
                $creditHistoryScore = 10.0;
                $creditHistoryExplanation = 'Présence d’au moins un crédit en défaut.';
            } else {
                $totalRepayments = $loans->pluck('repayments')->flatten();
                $lateCount = $totalRepayments->where('days_late', '>', 0)->count();
                $creditHistoryScore = max(20.0, 95.0 - ($lateCount * 15.0));
                $creditHistoryExplanation = "Historique des crédits passés et ponctualité ({$lateCount} échéance(s) en retard).";
            }
        }

        $guaranteeScore = 50.0;
        $guarantee = $request->guarantees->first();
        if ($guarantee) {
            $val = (float) ($guarantee->verified_value ?? $guarantee->declared_value);
            $req = (float) $request->requested_amount;
            $gRatio = $req > 0 ? ($val / $req) : 0;

            $guaranteeScore = match (true) {
                $gRatio >= 1.0 => 95.0,
                $gRatio >= 0.7 => 80.0,
                $gRatio >= 0.5 => 65.0,
                default => 45.0,
            };
        }

        $zoneFilled = filled($client->residential_zone) && filled($client->city);
        $zoneScore = $zoneFilled ? 80.0 : 55.0;

        return [
            FactorType::RepaymentCapacity->value => [
                'name' => 'Capacité de Remboursement',
                'score' => $capacityScore,
                'explanation' => "Capacité disponible comparée à la mensualité ({$disposable} FCFA / {$monthlyPayment} FCFA)",
            ],
            FactorType::IncomeConsistency->value => [
                'name' => 'Cohérence des Revenus',
                'score' => $incomeConsistencyScore,
                'explanation' => $incomeExplanation,
            ],
            FactorType::Expense->value => [
                'name' => 'Structure des Charges',
                'score' => $expenseScore,
                'explanation' => 'Proportion des charges personnelles par rapport au revenu global',
            ],
            FactorType::Activity->value => [
                'name' => 'Stabilité de l’Activité',
                'score' => $activityScore,
                'explanation' => 'Ancienneté et localisation de l’activité économique',
            ],
            FactorType::ActivityVitality->value => [
                'name' => 'Vitalité du cycle d’activité',
                'score' => $vitality['score'],
                'explanation' => $vitality['explanation'],
            ],
            FactorType::Document->value => [
                'name' => 'Qualité des Justificatifs',
                'score' => $documentScore,
                'explanation' => 'Présence de pièces et anomalies ouvertes à vérifier (OCR ≠ authenticité)',
            ],
            FactorType::Savings->value => [
                'name' => 'Niveau et Régularité d’Épargne',
                'score' => $savingsScore,
                'explanation' => $savingsExplanation,
            ],
            FactorType::CreditHistory->value => [
                'name' => 'Historique de Crédit Antérieur',
                'score' => $creditHistoryScore,
                'explanation' => $creditHistoryExplanation,
            ],
            FactorType::Guarantee->value => [
                'name' => 'Garantie proposée',
                'score' => $guaranteeScore,
                'explanation' => 'Couverture de la garantie déclarée/vérifiée par rapport au montant sollicité',
            ],
            FactorType::ResidentialZone->value => [
                'name' => 'Zone d’Habitation',
                'score' => $zoneScore,
                'explanation' => $zoneFilled
                    ? 'Localisation renseignée (facteur contextuel, sans barème discriminatoire de quartier).'
                    : 'Zone d’habitation incomplète — information à compléter.',
            ],
        ];
    }

    protected function resolveDocumentedIncome(CreditRequest $request): float
    {
        $ocrIncome = $this->resolveOcrDocumentedIncome($request);
        if ($ocrIncome !== null) {
            return $ocrIncome;
        }

        return (float) ($request->client->financialProfile?->monthly_income ?? $request->declared_monthly_income);
    }

    protected function resolveOcrDocumentedIncome(CreditRequest $request): ?float
    {
        $ocrIncomes = $request->documents
            ->map(function ($document): ?float {
                $value = $document->extraction?->extracted_data['verified_monthly_income'] ?? null;

                return $value !== null ? (float) $value : null;
            })
            ->filter()
            ->values();

        if ($ocrIncomes->isEmpty()) {
            return null;
        }

        return round((float) $ocrIncomes->avg(), 2);
    }

    /**
     * Score de confiance (30–100), distinct de la note métier.
     */
    protected function calculateConfidenceScore(CreditRequest $request): float
    {
        $docs = $request->documents;
        $base = $docs->isEmpty() ? 45.0 : 70.0;

        if ($docs->isNotEmpty()) {
            $confidences = $docs->map(fn ($doc): float => (float) ($doc->extraction?->extraction_confidence ?? 70.0));
            $base = (float) $confidences->avg();
        }

        $openAnomalies = $request->anomalies->where('status', AnomalyStatus::Open)->count();
        $base -= min(25.0, $openAnomalies * 5.0);

        return round(min(100.0, max(30.0, $base)), 2);
    }

    protected function generateAnalysisSummary(
        CreditRequest $request,
        ScoringModel $model,
        float $score,
        ScoringRecommendation $recommendation,
        float $proposedRate
    ): string {
        $ceiling = (float) config('credit.scoring.overall_score_ceiling', 95.0);
        $rate = $this->interestRates->institutionalRate();

        return "Évaluation du dossier #{$request->id} — {$model->name} {$model->version} (mode STANDARD) : score global = {$score}/100 (plafond de prudence {$ceiling}). Taux d’intérêt institutionnel = {$rate} % (taux unique pour tous les dossiers). Recommandation d’aide à la décision : {$recommendation->value}. La décision d’octroi reste humaine.";
    }
}
