<?php

namespace App\Services;

/**
 * Barème institutionnel : intérêt simple à 15 % / an.
 * intérêt = arrondi(capital × 0,15 × mois / 12)
 * total = capital + intérêt
 * mensualité = arrondi(total / mois) — la dernière échéance absorbe le reste.
 */
class SimpleInterestService
{
    public function __construct(protected InterestRateService $interestRates) {}

    /**
     * @return array{
     *     principal: float,
     *     interest_rate: float,
     *     total_interest: float,
     *     total_amount: float,
     *     monthly_payment: float,
     *     duration_months: int,
     *     schedule: list<array{installment: int, expected_amount: float}>
     * }
     */
    public function quote(float $principal, int $durationMonths, ?float $annualRatePercent = null): array
    {
        $rate = $annualRatePercent ?? $this->interestRates->institutionalRate();
        $durationMonths = max(1, $durationMonths);
        $totalInterest = round($principal * ($rate / 100) * ($durationMonths / 12), 0);
        $totalAmount = round($principal + $totalInterest, 0);
        $monthlyPayment = (int) round($totalAmount / $durationMonths, 0);
        $schedule = [];
        $allocated = 0.0;

        for ($month = 1; $month <= $durationMonths; $month++) {
            $amount = $month === $durationMonths
                ? round($totalAmount - $allocated, 0)
                : (float) $monthlyPayment;
            $allocated += $amount;
            $schedule[] = [
                'installment' => $month,
                'expected_amount' => $amount,
            ];
        }

        return [
            'principal' => $principal,
            'interest_rate' => $rate,
            'total_interest' => $totalInterest,
            'total_amount' => $totalAmount,
            'monthly_payment' => (float) $monthlyPayment,
            'duration_months' => $durationMonths,
            'schedule' => $schedule,
        ];
    }
}
