<?php

namespace App\Services;

use App\Models\AccountTransaction;
use App\Models\Client;
use App\Models\FinancialAccount;
use App\Models\SavingsHistory;
use App\Models\User;

class InstitutionalHistoryService
{
    public function __construct(protected AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createAccount(Client $client, array $attributes, User $actor): FinancialAccount
    {
        $account = $client->financialAccounts()->create($attributes);

        $this->auditLogger->record($actor, 'FINANCIAL_ACCOUNT_CREATED', FinancialAccount::class, $account->id, [
            'client_id' => $client->id,
            'account_number' => $account->account_number,
        ]);

        return $account;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordTransaction(FinancialAccount $account, array $attributes, User $actor): AccountTransaction
    {
        $transaction = $account->transactions()->create($attributes);

        $direction = strtoupper((string) $transaction->transaction_type);
        $amount = (float) $transaction->amount;
        $balance = (float) $account->balance;

        $account->balance = in_array($direction, ['WITHDRAWAL', 'DEBIT'], true)
            ? $balance - $amount
            : $balance + $amount;
        $account->save();

        $this->auditLogger->record($actor, 'ACCOUNT_TRANSACTION_RECORDED', AccountTransaction::class, $transaction->id, [
            'account_id' => $account->id,
            'amount' => $amount,
            'transaction_type' => $transaction->transaction_type,
        ]);

        return $transaction;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordSavingsHistory(Client $client, array $attributes, User $actor): SavingsHistory
    {
        $history = $client->savingsHistories()->create($attributes);

        $this->auditLogger->record($actor, 'SAVINGS_HISTORY_RECORDED', SavingsHistory::class, $history->id, [
            'client_id' => $client->id,
            'account_id' => $history->account_id,
        ]);

        return $history;
    }
}
