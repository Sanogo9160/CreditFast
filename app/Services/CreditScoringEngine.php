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
 * ## Choix du mode
 * - STANDARD : le client a un historique d’épargne institutionnel et/ou un crédit
 *   déjà accordé (`loans` / `savings_histories`). Les facteurs épargne et historique
 *   de crédit sont alors inclus s’ils existent dans le modèle actif.
 * - COLD_START : aucun de ces historiques n’est disponible. Absence d’historique
 *   ≠ mauvais historique : les facteurs `savings` et `credit_history` ne sont pas
 *   dans le modèle Cold Start (poids 0 / non inclus). La note globale est
 *   renormalisée sur les facteurs restants.
 *
 * ## Sous-notes (0 à 100) — barèmes de prototype, non officiels IMF
 * - repayment_capacity : ratio reste à vivre / mensualité estimée
 *   (≥2 → 95, ≥1,5 → 85, ≥1,2 → 70, ≥1 → 55, sinon 25).
 * - income_consistency : écart déclaré vs OCR ou CA d’activité
 *   (≤5 % → 95, ≤15 % → 80, ≤25 % → 60, sinon 35 ; pas de référence → 50).
 * - expense : charges / revenu total (≤30 % → 95, ≤50 % → 80, ≤70 % → 60, sinon 30).
 * - activity : ancienneté en mois (≥36 → 95, ≥24 → 85, ≥12 → 70, sinon 50 ; sans activité → 60).
 * - document : base 90, −15 par anomalie ouverte, −10 par anomalie critique, +5 par pièce (borné 10–100).
 * - savings (STANDARD seulement) : solde moyen / montant demandé
 *   (≥50 % → 95, ≥20 % → 80, ≥10 % → 65, sinon 45) + bonus régularité (6 dépôts +2, 12 dépôts +5).
 * - credit_history (STANDARD seulement) : défaut → 10 ; sinon 95 − 15 × échéances en retard (plancher 20).
 * - guarantee : valeur vérifiée (ou déclarée) / montant demandé
 *   (≥100 % → 95, ≥70 % → 80, ≥50 % → 65, sinon 45 ; sans garantie → 50).
 * - residential_zone : localisation ville+zone renseignée → 80, sinon 55 (facteur contextuel, souvent inactif).
 *
 * ## Pondérations V1 (règles ACTIVE du seeder — la zone résidentielle est INACTIVE, poids 0)
 * STANDARD (historique d’épargne et/ou de crédit) — somme utile = 100 % :
 *   repayment_capacity 25 %, income_consistency 15 %, activity 15 %,
 *   expense 10 %, document 10 %, savings 10 %, credit_history 10 %, guarantee 5 %.
 * COLD_START (ni épargne ni crédit antérieur dans le modèle) — somme utile = 100 % :
 *   repayment_capacity 30 %, income_consistency 20 %, activity 20 %,
 *   expense 10 %, document 10 %, guarantee 10 %.
 * Les facteurs `savings` et `credit_history` n’ont pas de règle Cold Start : ils sont
 * calculés en interne pour l’explicabilité mais `included = false`, donc hors note globale.
 *
 * ## Note globale
 * Chaque facteur INCLUS (règle ACTIVE du modèle) contribue : score × (poids / 100).
 * Si la somme des poids actifs ≠ 100, le total est renormalisé. Résultat borné 0–100.
 * Les poids concrets vivent dans `scoring_rules` et peuvent être ajustés par l’admin
 * sans modifier ce moteur.
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
    ) {}

    /**
     * Calcule l’analyse explicable d’une demande : anomalies, mode STANDARD/COLD_START,
     * sous-notes 0–100, agrégation pondérée, score de confiance et recommandation d’aide.
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

        $this->anomalyService->detectAnomalies($creditRequest);
        $creditRequest->load('anomalies');

        $scoringMode = $this->resolveScoringMode($creditRequest);
        $model = $this->resolveActiveModel($scoringMode);

        return DB::transaction(function () use ($creditRequest, $model, $scoringMode): CreditAnalysis {
            $factorResults = $this->calculateFactorScores($creditRequest, $model, $scoringMode);

            $overallScore = $this->aggregateOverallScore($factorResults);
            $confidenceScore = $this->calculateConfidenceScore($creditRequest, $scoringMode);
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
                'document_score' => $factorResults[FactorType::Document->value]['score'] ?? 0,
                'savings_score' => $factorResults[FactorType::Savings->value]['score'] ?? 0,
                'credit_history_score' => $factorResults[FactorType::CreditHistory->value]['score'] ?? 0,
                'guarantee_score' => $factorResults[FactorType::Guarantee->value]['score'] ?? 0,
                'repayment_capacity_score' => $factorResults[FactorType::RepaymentCapacity->value]['score'] ?? 0,
                'residential_zone_score' => $factorResults[FactorType::ResidentialZone->value]['score'] ?? 0,
                'overall_score' => $overallScore,
                'confidence_score' => $confidenceScore,
                'recommendation' => $recommendation,
                'analysis_summary' => $this->generateAnalysisSummary($creditRequest, $model, $overallScore, $scoringMode, $recommendation),
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
     * STANDARD dès qu’un historique d’épargne ou de crédit institutionnel existe ;
     * sinon COLD_START. On ne pénalise pas l’absence d’historique.
     */
    public function resolveScoringMode(CreditRequest $creditRequest): ScoringMode
    {
        $hasCreditHistory = $creditRequest->client->loans->isNotEmpty();
        $hasSavingsHistory = $creditRequest->client->savingsHistories->isNotEmpty();

        return ($hasCreditHistory || $hasSavingsHistory)
            ? ScoringMode::Standard
            : ScoringMode::ColdStart;
    }

    /**
     * Charge le modèle ACTIVE du mode choisi (STANDARD ou COLD_START) et ses règles ACTIVE.
     * Sans modèle, le calcul s’arrête : on ne mélange jamais les deux barèmes.
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
     * Combine les sous-notes incluses : Σ (score × poids/100), renormalisé si besoin.
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

        return round(min(100.0, max(0.0, $weighted)), 2);
    }

    /**
     * Applique les poids du modèle actif : un facteur n’entre dans la note globale
     * que s’il existe une règle ACTIVE de même `factor_type` (ex. pas d’épargne en Cold Start).
     *
     * @return array<string, array<string, mixed>>
     */
    protected function calculateFactorScores(CreditRequest $request, ScoringModel $model, ScoringMode $mode): array
    {
        $computed = $this->computeRawFactorScores($request, $mode);
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
     * Calcule chaque sous-note 0–100 selon les barèmes de prototype ci-dessus.
     * Ne persiste rien : l’inclusion et le poids sont tranchés ensuite par le modèle.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function computeRawFactorScores(CreditRequest $request, ScoringMode $mode): array
    {
        $client = $request->client;
        $profile = $client->financialProfile;
        $activity = $request->activity;

        $disposable = (float) ($request->disposable_income ?? $profile?->disposable_income ?? 0);
        $monthlyPayment = (float) $request->estimated_monthly_payment;
        $capRatio = $monthlyPayment > 0 ? ($disposable / $monthlyPayment) : 1.0;

        // Capacité de remboursement : reste à vivre / mensualité estimée → 25 / 55 / 70 / 85 / 95.
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

        // Cohérence des revenus : écart déclaré vs OCR (prioritaire) ou CA d’activité.
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

        // Charges : dépenses / revenu total. Sans revenu, ratio par défaut 80 % (sous-note 30).
        $expenseScore = match (true) {
            $expenseRatio <= 0.30 => 95.0,
            $expenseRatio <= 0.50 => 80.0,
            $expenseRatio <= 0.70 => 60.0,
            default => 30.0,
        };

        // Activité : 60 sans fiche ; sinon ancienneté en mois (12 / 24 / 36).
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
        // Justificatifs : base 90, −15 / anomalie ouverte, −10 / anomalie critique, +5 / pièce (10–100).
        $documentScore = max(10.0, 90.0 - ($openAnomaliesCount * 15.0) - ($criticalAnomalies * 10.0) + ($docCount * 5.0));
        $documentScore = min(100.0, $documentScore);

        $savingsHistories = $client->savingsHistories;
        $savingsAvailable = $savingsHistories->isNotEmpty();
        $savingsScore = 0.0;
        $savingsExplanation = 'Aucune donnée d’épargne institutionnelle exploitable — non assimilé à un mauvais historique.';

        // Épargne (STANDARD seulement à l’agrégation) : solde moyen / montant + bonus de régularité.
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
        } elseif ($mode === ScoringMode::ColdStart) {
            $savingsExplanation = 'Cold Start : l’épargne n’est pas un facteur du modèle — absence d’historique ≠ mauvais historique.';
        }

        $loans = $client->loans;
        $creditHistoryAvailable = $loans->isNotEmpty();
        $creditHistoryScore = 0.0;
        $creditHistoryExplanation = 'Aucun crédit antérieur connu — non assimilé à un mauvais historique.';

        // Historique de crédit (STANDARD seulement à l’agrégation) : défaut = 10, sinon 95 − 15 × retards.
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
        } elseif ($mode === ScoringMode::ColdStart) {
            $creditHistoryExplanation = 'Cold Start : l’historique de crédit n’est pas un facteur du modèle.';
        }

        // Garantie : 50 sans bien ; sinon valeur vérifiée (à défaut déclarée) / montant demandé.
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

        // Zone d’habitation : facteur contextuel (souvent INACTIVE / poids 0 dans le seeder V1).
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
     * Score de confiance (30–100), distinct de la note métier :
     * moyenne des confiances OCR (ou 45/70 sans pièce), −8 en Cold Start, −5 par anomalie ouverte (plafond −25).
     */
    protected function calculateConfidenceScore(CreditRequest $request, ScoringMode $mode): float
    {
        $docs = $request->documents;
        $base = $docs->isEmpty() ? 45.0 : 70.0;

        if ($docs->isNotEmpty()) {
            $confidences = $docs->map(fn ($doc): float => (float) ($doc->extraction?->extraction_confidence ?? 70.0));
            $base = (float) $confidences->avg();
        }

        if ($mode === ScoringMode::ColdStart) {
            $base -= 8.0;
        }

        $openAnomalies = $request->anomalies->where('status', AnomalyStatus::Open)->count();
        $base -= min(25.0, $openAnomalies * 5.0);

        return round(min(100.0, max(30.0, $base)), 2);
    }

    protected function generateAnalysisSummary(
        CreditRequest $request,
        ScoringModel $model,
        float $score,
        ScoringMode $mode,
        ScoringRecommendation $recommendation
    ): string {
        $modeText = $mode === ScoringMode::ColdStart
            ? 'Cold Start (sans historique bancaire exploitable)'
            : 'Standard (historique institutionnel disponible)';

        return "Évaluation du dossier #{$request->id} — {$model->name} {$model->version} (mode {$modeText}) : score global = {$score}/100. Recommandation d’aide à la décision : {$recommendation->value}. Cette note oriente l’équipe ; la décision d’octroi reste humaine.";
    }
}
