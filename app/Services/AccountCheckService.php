<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Models\Client;
use App\Models\FinancialAccount;
use App\Models\FinancialProfile;

class AccountCheckService
{
    /**
     * @return array{
     *     monthly_income: float,
     *     monthly_expenses: float,
     *     ongoing_credit_count: int
     * }
     */
    public function forClient(Client $client): array
    {
        $client->loadMissing(['financialProfile', 'loans']);

        $profile = $client->financialProfile;

        return [
            'monthly_income' => (float) ($profile?->monthly_income ?? 0),
            'monthly_expenses' => (float) ($profile?->monthly_expenses ?? 0),
            'ongoing_credit_count' => $this->ongoingCreditCount($client),
        ];
    }

    public function ongoingCreditCount(Client $client): int
    {
        return $client->loans()
            ->whereIn('status', [LoanStatus::Active, LoanStatus::Approved])
            ->count();
    }

    public function ongoingMonthlyDebt(Client $client): float
    {
        return (float) $client->loans()
            ->whereIn('status', [LoanStatus::Active, LoanStatus::Approved])
            ->sum('monthly_payment');
    }

    public function activeSavingsAccount(Client $client): ?FinancialAccount
    {
        return $client->financialAccounts()
            ->whereIn('account_type', ['EPARGNE', 'SAVINGS'])
            ->whereIn('status', ['ACTIF', 'ACTIVE'])
            ->orderByDesc('id')
            ->first();
    }

    public function financialProfile(Client $client): ?FinancialProfile
    {
        return $client->financialProfile;
    }
}
