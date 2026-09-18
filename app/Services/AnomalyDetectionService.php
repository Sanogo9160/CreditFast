<?php

namespace App\Services;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyStatus;
use App\Models\Anomaly;
use App\Models\CreditRequest;

class AnomalyDetectionService
{
    public function __construct(protected FinancialCalculationService $financialService) {}

    /**
     * Run automated anomaly detection on a credit request.
     * An anomaly triggers human verification; it does not prove fraud.
     *
     * @return array<int, Anomaly>
     */
    public function detectAnomalies(CreditRequest $creditRequest): array
    {
        $creditRequest->loadMissing([
            'client.financialProfile',
            'activity',
            'documents.extraction',
        ]);

        $detected = [];
        $client = $creditRequest->client;
        $declaredIncome = (float) $creditRequest->declared_monthly_income;
        $activityRevenue = (float) ($creditRequest->activity?->monthly_revenue ?? 0);

        if ($activityRevenue > 0 && abs($declaredIncome - $activityRevenue) > ($declaredIncome * 0.20)) {
            $detected[] = Anomaly::updateOrCreate(
                [
                    'credit_request_id' => $creditRequest->id,
                    'anomaly_type' => 'INCOME_DISCREPANCY',
                    'document_id' => null,
                ],
                [
                    'severity' => AnomalySeverity::High,
                    'description' => 'Écart notable (>20 %) entre le revenu mensuel indiqué et le chiffre d’affaires de l’activité. À rapprocher avec le client.',
                    'detected_value' => number_format($declaredIncome, 2, '.', ' ').' FCFA (Déclaré)',
                    'expected_value' => number_format($activityRevenue, 2, '.', ' ').' FCFA (Activité)',
                    'status' => AnomalyStatus::Open,
                ]
            );
        }

        foreach ($creditRequest->documents as $doc) {
            $data = $doc->extraction?->extracted_data ?? [];

            if (! isset($data['verified_monthly_income'])) {
                continue;
            }

            $ocrIncome = (float) $data['verified_monthly_income'];
            if (abs($declaredIncome - $ocrIncome) > ($declaredIncome * 0.15)) {
                $detected[] = Anomaly::updateOrCreate(
                    [
                        'credit_request_id' => $creditRequest->id,
                        'document_id' => $doc->id,
                        'anomaly_type' => 'OCR_INCOME_MISMATCH',
                    ],
                    [
                        'severity' => AnomalySeverity::High,
                        'description' => 'Le revenu indiqué et le montant lu sur le justificatif diffèrent. La lecture automatique ne prouve pas l’authenticité du document ; un contrôle humain est prévu.',
                        'detected_value' => number_format($declaredIncome, 2, '.', ' ').' FCFA (Déclaré)',
                        'expected_value' => number_format($ocrIncome, 2, '.', ' ').' FCFA (Extrait OCR)',
                        'status' => AnomalyStatus::Open,
                    ]
                );
            }
        }

        $documentTypes = $creditRequest->documents
            ->pluck('document_type')
            ->map(fn (string $type): string => strtoupper($type))
            ->all();
        $mandatoryTypes = ['PIECE_IDENTITE', 'JUSTIFICATIF_DOMICILE', 'PREUVE_REVENU'];
        $missing = array_values(array_diff($mandatoryTypes, $documentTypes));

        if ($missing !== []) {
            $detected[] = Anomaly::updateOrCreate(
                [
                    'credit_request_id' => $creditRequest->id,
                    'anomaly_type' => 'MISSING_MANDATORY_DOCUMENTS',
                    'document_id' => null,
                ],
                [
                    'severity' => AnomalySeverity::Medium,
                    'description' => 'Certaines pièces utiles n’ont pas encore été transmises : '.implode(', ', $missing).'. Le client peut les ajouter pour faciliter l’examen.',
                    'detected_value' => 'Documents présents : '.implode(', ', $documentTypes),
                    'expected_value' => 'Requis : '.implode(', ', $mandatoryTypes),
                    'status' => AnomalyStatus::Open,
                ]
            );
        }

        $totalIncome = (float) ($client->financialProfile?->monthly_income ?? $declaredIncome);
        $existingDebt = (float) ($client->financialProfile?->existing_debt_payment ?? 0);
        $newPayment = (float) $creditRequest->estimated_monthly_payment;
        $expenses = (float) ($client->financialProfile?->monthly_expenses ?? $creditRequest->declared_monthly_expenses);

        $dti = $this->financialService->calculateDebtToIncomeRatio($expenses, $existingDebt, $newPayment, $totalIncome);

        if ($dti > 50.0) {
            $detected[] = Anomaly::updateOrCreate(
                [
                    'credit_request_id' => $creditRequest->id,
                    'anomaly_type' => 'HIGH_DEBT_RATIO',
                    'document_id' => null,
                ],
                [
                    'severity' => AnomalySeverity::Critical,
                    'description' => "Le ratio d’engagement ({$dti} %) dépasse le seuil de prototype de 50 %. Point à examiner avec le client, sans préjuger de la décision.",
                    'detected_value' => "Ratio d'endettement : {$dti}%",
                    'expected_value' => 'Seuil conseillé (prototype) : <= 50%',
                    'status' => AnomalyStatus::Open,
                ]
            );
        }

        return $detected;
    }
}
