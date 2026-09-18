<?php

namespace App\Services;

use App\Enums\RepaymentCapacityStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class FinancialCalculationService
{
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
     * Calculate estimated monthly payment (Mensualité estimée).
     * Uses constant payment formula (amortization schedule) or simple linear interest.
     */
    public function calculateEstimatedMonthlyPayment(
        float $requestedAmount,
        int $durationMonths,
        ?float $annualInterestRatePercent = null
    ): float {
        $annualInterestRatePercent ??= $this->defaultAnnualInterestRate();
        if ($durationMonths <= 0) {
            return $requestedAmount;
        }

        $monthlyInterestRate = ($annualInterestRatePercent / 100) / 12;

        if ($monthlyInterestRate <= 0) {
            return round($requestedAmount / $durationMonths, 2);
        }

        // Formula: P * r * (1 + r)^n / ((1 + r)^n - 1)
        $factor = pow(1 + $monthlyInterestRate, $durationMonths);
        $monthlyPayment = $requestedAmount * ($monthlyInterestRate * $factor) / ($factor - 1);

        return round($monthlyPayment, 2);
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
        return (float) config('credit.annual_interest_rate_percent', 12.0);
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
        $monthlyPayment = $this->calculateEstimatedMonthlyPayment($requestedAmount, $durationMonths, $annualInterestRatePercent);
        $start = $fromDate ? Carbon::parse($fromDate->toDateString()) : now()->startOfDay();
        $rows = [];

        for ($month = 1; $month <= $durationMonths; $month++) {
            $rows[] = [
                'installment' => $month,
                'due_date' => $start->copy()->addMonths($month)->toDateString(),
                'expected_amount' => $monthlyPayment,
            ];
        }

        return $rows;
    }
}
