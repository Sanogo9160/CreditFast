<?php

namespace App\Services;

use App\Enums\RepaymentCapacityStatus;

class CreditSimulationService
{
    public function __construct(protected FinancialCalculationService $financialService) {}

    /**
     * Compare several amount/duration pairs against repayment capacity.
     * The interest rate is institutional (config), never supplied by the client.
     *
     * @param  list<array{requested_amount: float, duration_months: int}>  $scenarios
     * @return array{
     *     capacity: array<string, float|string>,
     *     annual_interest_rate_percent: float,
     *     disclaimer: string,
     *     scenarios: list<array<string, mixed>>
     * }
     */
    public function compareScenarios(
        float $monthlyIncome,
        float $otherIncome,
        float $monthlyExpenses,
        float $existingDebtPayment,
        array $scenarios
    ): array {
        $disposable = $this->financialService->calculateDisposableIncome(
            $monthlyIncome,
            $otherIncome,
            $monthlyExpenses,
            $existingDebtPayment
        );
        $totalIncome = $monthlyIncome + $otherIncome;
        $rate = $this->financialService->defaultAnnualInterestRate();
        $compared = [];

        foreach ($scenarios as $index => $scenario) {
            $amount = (float) $scenario['requested_amount'];
            $duration = (int) $scenario['duration_months'];
            $monthlyPayment = $this->financialService->calculateEstimatedMonthlyPayment($amount, $duration, $rate);
            $capacity = $this->financialService->evaluateRepaymentCapacity($disposable, $monthlyPayment);
            $dti = $this->financialService->calculateDebtToIncomeRatio(
                $monthlyExpenses,
                $existingDebtPayment,
                $monthlyPayment,
                $totalIncome
            );
            $remainingAfterPayment = round($disposable - $monthlyPayment, 2);
            $totalCost = round($monthlyPayment * $duration, 2);

            $compared[] = [
                'index' => $index + 1,
                'requested_amount' => $amount,
                'duration_months' => $duration,
                'estimated_monthly_payment' => $monthlyPayment,
                'estimated_total_cost' => $totalCost,
                'estimated_interest_amount' => round($totalCost - $amount, 2),
                'remaining_after_payment' => $remainingAfterPayment,
                'repayment_capacity_status' => $capacity->value,
                'debt_to_income_ratio' => $dti,
                'is_coherent' => $capacity === RepaymentCapacityStatus::Sufficient,
                'schedule_preview' => $this->financialService->previewSchedule($amount, $duration, $rate),
            ];
        }

        return [
            'capacity' => [
                'monthly_income' => $monthlyIncome,
                'other_income' => $otherIncome,
                'monthly_expenses' => $monthlyExpenses,
                'existing_debt_payment' => $existingDebtPayment,
                'disposable_income' => $disposable,
            ],
            'annual_interest_rate_percent' => $rate,
            'disclaimer' => 'Simulation d’aide à la décision. Le taux est un prototype institutionnel. Le score et cette simulation ne décident pas de l’octroi.',
            'scenarios' => $compared,
        ];
    }
}
