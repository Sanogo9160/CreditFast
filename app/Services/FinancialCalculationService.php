<?php

namespace App\Services;

use App\Enums\RepaymentCapacityStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class FinancialCalculationService
{
    public function __construct(
        protected InterestRateService $interestRates,
        protected SimpleInterestService $simpleInterest,
    ) {}

    /**
     * Calculate disposable income (Reste à vivre).
     * Disposable Income = (Monthly Income + Other Income) - (Monthly Expenses + Existing Debt Payments)
     */
    public function calculateDisposableIncome(
        float $monthlyIncome,
        float $otherIncome,
        float $monthlyExpenses,
        float $existingDebtPayment
    ): float {
        $totalIncome = $monthlyIncome + $otherIncome;
        $totalCharges = $monthlyExpenses + $existingDebtPayment;

        return max(0.0, $totalIncome - $totalCharges);
    }

    /**
     * Mensualité estimée — intérêt simple institutionnel (15 % / an).
     */
    public function calculateEstimatedMonthlyPayment(
        float $requestedAmount,
        int $durationMonths,
        ?float $annualInterestRatePercent = null
    ): float {
        return $this->simpleInterest->quote($requestedAmount, $durationMonths, $annualInterestRatePercent)['monthly_payment'];
    }

    /**
     * Evaluate repayment capacity status.
     * SUFFICIENT if Disposable Income >= Monthly Payment * (1 + marginPercentage/100)
     */
    public function evaluateRepaymentCapacity(
        float $disposableIncome,
        float $estimatedMonthlyPayment,
        ?float $marginPercentage = null
    ): RepaymentCapacityStatus {
        $marginPercentage = $marginPercentage ?? (float) config('credit.repayment_margin_percentage', 20.0);

        $requiredCapacity = $estimatedMonthlyPayment * (1 + ($marginPercentage / 100));

        if ($disposableIncome >= $requiredCapacity && $disposableIncome > 0) {
            return RepaymentCapacityStatus::Sufficient;
        }

        return RepaymentCapacityStatus::Insufficient;
    }

    /**
     * Calculate Debt-to-Income Ratio (Taux d'endettement).
     */
    public function calculateDebtToIncomeRatio(
        float $monthlyExpenses,
        float $existingDebtPayment,
        float $newMonthlyPayment,
        float $totalIncome
    ): float {
        if ($totalIncome <= 0) {
            return 100.0;
        }

        $totalDebt = $existingDebtPayment + $newMonthlyPayment;

        return round(($totalDebt / $totalIncome) * 100, 2);
    }

    public function defaultAnnualInterestRate(): float
    {
        return $this->interestRates->defaultRate();
    }

    /**
     * Preview an installment calendar without persisting a loan.
     *
     * @return list<array{installment: int, due_date: string, expected_amount: float}>
     */
    public function previewSchedule(
        float $requestedAmount,
        int $durationMonths,
        ?float $annualInterestRatePercent = null,
        ?CarbonInterface $fromDate = null
    ): array {
        $quote = $this->simpleInterest->quote($requestedAmount, $durationMonths, $annualInterestRatePercent);
        $start = $fromDate ? Carbon::parse($fromDate->toDateString()) : now()->startOfDay();
        $rows = [];

        foreach ($quote['schedule'] as $row) {
            $rows[] = [
                'installment' => $row['installment'],
                'due_date' => $start->copy()->addMonths($row['installment'])->toDateString(),
                'expected_amount' => $row['expected_amount'],
            ];
        }

        return $rows;
    }
}
