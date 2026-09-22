<?php

namespace App\Services;

/**
 * Institutional annual interest rate — fixed for every dossier.
 * Clients never set the rate; the committee does not adjust it.
 */
class InterestRateService
{
    /**
     * Unique institutional rate applied to simulations, scoring proposals and loans.
     */
    public function institutionalRate(): float
    {
        return round((float) config('credit.interest.annual_percent', 15.0), 2);
    }

    public function defaultRate(): float
    {
        return $this->institutionalRate();
    }

    public function proposeFromScore(float $overallScore): float
    {
        return $this->institutionalRate();
    }

    public function clamp(float $rate): float
    {
        return $this->institutionalRate();
    }

    public function min(): float
    {
        return $this->institutionalRate();
    }

    public function max(): float
    {
        return $this->institutionalRate();
    }

    public function isWithinBand(float $rate): bool
    {
        return abs($rate - $this->institutionalRate()) < 0.0001;
    }
}
