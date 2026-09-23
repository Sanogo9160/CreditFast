<?php

namespace App\Services;

use App\Enums\LoanRepaymentStatus;
use App\Enums\LoanStatus;
use App\Models\AccountTransaction;
use App\Models\AuditLog;
use App\Models\CreditRequest;
use App\Models\FinancialAccount;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class LoanService
{
    public function __construct(
        protected FinancialCalculationService $financialService,
        protected CreditWorkflowService $workflowService,
        protected InterestRateService $interestRates,
        protected SimpleInterestService $simpleInterest,
        protected AccountCheckService $accountCheck,
    ) {}

    /**
     * Octroi comité : crée le prêt ACTIVE, verse le capital sur l’épargne, génère l’échéancier.
     *
     * @param  array{
     *     approved_amount?: float|int|string,
     *     approved_duration_months?: int|string
     * }  $decision
     */
    public function grantAndDisburse(CreditRequest $creditRequest, array $decision, User $actor): Loan
    {
        $creditRequest->loadMissing('client.financialAccounts');

        $savings = $this->accountCheck->activeSavingsAccount($creditRequest->client);

        if ($savings === null) {
            throw ValidationException::withMessages([
                'decision' => 'Le client n’a pas de compte épargne actif. L’octroi ne peut pas verser les fonds.',
            ]);
        }

        $principal = (float) ($decision['approved_amount'] ?? $creditRequest->requested_amount);
        $duration = (int) ($decision['approved_duration_months'] ?? $creditRequest->duration_months);
        $quote = $this->simpleInterest->quote($principal, $duration);
        $valueDate = now();

        return DB::transaction(function () use ($creditRequest, $savings, $principal, $duration, $quote, $valueDate, $actor): Loan {
            $account = FinancialAccount::query()->lockForUpdate()->findOrFail($savings->id);
            $newBalance = round((float) $account->balance + $principal, 2);
            $account->balance = $newBalance;
            $account->available_balance = $newBalance;
            $account->save();

            $loan = Loan::updateOrCreate(
                ['credit_request_id' => $creditRequest->id],
                [
                    'client_id' => $creditRequest->client_id,
                    'savings_account_id' => $account->id,
                    'principal_amount' => $principal,
                    'interest_amount' => $quote['total_interest'],
                    'total_amount' => $quote['total_amount'],
                    'duration_months' => $duration,
                    'annual_interest_rate_percent' => $quote['interest_rate'],
                    'monthly_payment' => $quote['monthly_payment'],
                    'disbursed_at' => $valueDate->toDateString(),
                    'maturity_date' => $valueDate->copy()->addMonths($duration)->toDateString(),
                    'outstanding_amount' => $quote['total_amount'],
                    'funds_received' => $principal,
                    'status' => LoanStatus::Active,
                ]
            );

            AccountTransaction::create([
                'account_id' => $account->id,
                'transaction_type' => 'LOAN_DISBURSEMENT',
                'type' => 'LOAN_DISBURSEMENT',
                'direction' => 'CREDIT',
                'amount' => $principal,
                'transaction_date' => $valueDate,
                'booked_at' => $valueDate,
                'reference' => 'EP-PRET-'.$creditRequest->id,
                'label' => 'Épargne + prêt #'.$creditRequest->id,
                'description' => 'Épargne + prêt #'.$creditRequest->id,
                'status' => 'COMPLETED',
                'channel' => 'Compte épargne',
                'balance_after' => $newBalance,
            ]);

            $this->generateScheduleFromQuote($loan, $valueDate, $quote['schedule']);

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'LOAN_DISBURSED',
                'entity_type' => Loan::class,
                'entity_id' => $loan->id,
                'details' => [
                    'credit_request_id' => $creditRequest->id,
                    'principal_amount' => $principal,
                    'disbursed_at' => $valueDate->toDateString(),
                    'savings_account_id' => $account->id,
                ],
                'ip_address' => request()->ip(),
            ]);

            if ($creditRequest->client?->user_id) {
                Notification::create([
                    'user_id' => $creditRequest->client->user_id,
                    'title' => 'Vos fonds ont été mis à disposition',
                    'message' => "Votre crédit #{$loan->id} a été versé sur votre compte épargne. Consultez votre échéancier.",
                    'type' => 'LOAN_DISBURSED',
                ]);
            }

            return $loan->load(['repayments', 'creditRequest', 'savingsAccount']);
        });
    }

    /**
     * @deprecated Prefer grantAndDisburse at committee approval. Kept for legacy APPROVED loans without funds.
     *
     * @param  array{
     *     approved_amount?: float|int|string,
     *     approved_duration_months?: int|string
     * }  $decision
     */
    public function createApprovedLoan(CreditRequest $creditRequest, array $decision = []): Loan
    {
        $principal = (float) ($decision['approved_amount'] ?? $creditRequest->requested_amount);
        $duration = (int) ($decision['approved_duration_months'] ?? $creditRequest->duration_months);
        $quote = $this->simpleInterest->quote($principal, $duration);

        return Loan::updateOrCreate(
            ['credit_request_id' => $creditRequest->id],
            [
                'client_id' => $creditRequest->client_id,
                'principal_amount' => $principal,
                'interest_amount' => $quote['total_interest'],
                'total_amount' => $quote['total_amount'],
                'duration_months' => $duration,
                'annual_interest_rate_percent' => $quote['interest_rate'],
                'monthly_payment' => $quote['monthly_payment'],
                'disbursed_at' => null,
                'maturity_date' => null,
                'outstanding_amount' => $quote['total_amount'],
                'funds_received' => 0,
                'status' => LoanStatus::Approved,
            ]
        );
    }

    public function disburse(Loan $loan, User $actor, ?CarbonInterface $disbursedAt = null, ?string $comment = null): Loan
    {
        return DB::transaction(function () use ($loan, $actor, $disbursedAt, $comment): Loan {
            $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $lockedLoan->loadMissing(['client.user', 'creditRequest.client']);

            if ($lockedLoan->disbursed_at !== null || (float) $lockedLoan->funds_received > 0) {
                throw new InvalidArgumentException('Les fonds sont déjà versés. Un second décaissement n’est pas possible.');
            }

            if ($lockedLoan->status !== LoanStatus::Approved) {
                throw new InvalidArgumentException('Seul un crédit déjà accordé peut être décaissé.');
            }

            if ($lockedLoan->repayments()->where('paid_amount', '>', 0)->exists()) {
                throw new InvalidArgumentException('Ce crédit a déjà des remboursements enregistrés.');
            }

            $client = $lockedLoan->client ?? $lockedLoan->creditRequest?->client;
            $savings = $client ? $this->accountCheck->activeSavingsAccount($client) : null;

            if ($savings === null) {
                throw ValidationException::withMessages([
                    'loan' => 'Le client n’a pas de compte épargne actif. L’octroi ne peut pas verser les fonds.',
                ]);
            }

            $valueDate = Carbon::parse($disbursedAt?->toDateString() ?? now()->toDateString());
            $principal = (float) $lockedLoan->principal_amount;
            $quote = $this->simpleInterest->quote($principal, (int) $lockedLoan->duration_months, (float) $lockedLoan->annual_interest_rate_percent);

            $account = FinancialAccount::query()->lockForUpdate()->findOrFail($savings->id);
            $newBalance = round((float) $account->balance + $principal, 2);
            $account->balance = $newBalance;
            $account->available_balance = $newBalance;
            $account->save();

            $lockedLoan->savings_account_id = $account->id;
            $lockedLoan->disbursed_at = $valueDate->toDateString();
            $lockedLoan->maturity_date = $valueDate->copy()->addMonths($lockedLoan->duration_months)->toDateString();
            $lockedLoan->funds_received = $principal;
            $lockedLoan->interest_amount = $quote['total_interest'];
            $lockedLoan->total_amount = $quote['total_amount'];
            $lockedLoan->monthly_payment = $quote['monthly_payment'];
            $lockedLoan->outstanding_amount = $quote['total_amount'];
            $lockedLoan->status = LoanStatus::Active;
            $lockedLoan->save();

            AccountTransaction::create([
                'account_id' => $account->id,
                'transaction_type' => 'LOAN_DISBURSEMENT',
                'type' => 'LOAN_DISBURSEMENT',
                'direction' => 'CREDIT',
                'amount' => $principal,
                'transaction_date' => $valueDate,
                'booked_at' => $valueDate,
                'reference' => 'EP-PRET-'.($lockedLoan->credit_request_id ?? $lockedLoan->id),
                'label' => 'Épargne + prêt #'.($lockedLoan->credit_request_id ?? $lockedLoan->id),
                'description' => 'Épargne + prêt #'.($lockedLoan->credit_request_id ?? $lockedLoan->id),
                'status' => 'COMPLETED',
                'channel' => 'Compte épargne',
                'balance_after' => $newBalance,
            ]);

            $this->generateScheduleFromQuote($lockedLoan, $valueDate, $quote['schedule']);

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'LOAN_DISBURSED',
                'entity_type' => Loan::class,
                'entity_id' => $lockedLoan->id,
                'details' => [
                    'credit_request_id' => $lockedLoan->credit_request_id,
                    'principal_amount' => $lockedLoan->principal_amount,
                    'disbursed_at' => $valueDate->toDateString(),
                    'comment' => $comment,
                ],
                'ip_address' => request()->ip(),
            ]);

            $notifyUserId = $lockedLoan->creditRequest?->client?->user_id ?? $lockedLoan->client?->user_id;
            if ($notifyUserId) {
                Notification::create([
                    'user_id' => $notifyUserId,
                    'title' => 'Vos fonds ont été mis à disposition',
                    'message' => "Votre crédit #{$lockedLoan->id} a été décaissé. Vous pouvez consulter le montant reçu et votre échéancier de remboursement.",
                    'type' => 'LOAN_DISBURSED',
                ]);
            }

            return $lockedLoan->load(['repayments', 'creditRequest', 'client.user']);
        });
    }

    public function recordRepayment(
        LoanRepayment $repayment,
        float $paidAmount,
        User $actor,
        ?CarbonInterface $paymentDate = null,
        ?string $comment = null
    ): LoanRepayment {
        return DB::transaction(function () use ($repayment, $paidAmount, $actor, $paymentDate, $comment): LoanRepayment {
            $lockedRepayment = LoanRepayment::query()->lockForUpdate()->findOrFail($repayment->id);
            $loan = Loan::query()->lockForUpdate()->findOrFail($lockedRepayment->loan_id);

            if ($loan->status !== LoanStatus::Active) {
                throw new InvalidArgumentException('Les remboursements s’enregistrent uniquement sur un crédit décaissé et actif.');
            }

            if ($lockedRepayment->status === LoanRepaymentStatus::Paid) {
                throw new InvalidArgumentException('Cette échéance est déjà soldée.');
            }

            $remainingOnInstallment = round((float) $lockedRepayment->expected_amount - (float) $lockedRepayment->paid_amount, 2);

            if ($paidAmount > $remainingOnInstallment) {
                throw new InvalidArgumentException("Le montant saisi dépasse le reste dû de l’échéance ({$remainingOnInstallment} FCFA). Merci d’ajuster le montant.");
            }

            $date = $paymentDate ?? now();
            $dueDate = $lockedRepayment->due_date->startOfDay();
            $paidOn = $date->copy()->startOfDay();
            $daysLate = $paidOn->greaterThan($dueDate) ? $dueDate->diffInDays($paidOn) : 0;

            $lockedRepayment->paid_amount = round((float) $lockedRepayment->paid_amount + $paidAmount, 2);
            $lockedRepayment->payment_date = $date->toDateString();
            $lockedRepayment->days_late = (int) $daysLate;
            $lockedRepayment->status = $lockedRepayment->paid_amount >= (float) $lockedRepayment->expected_amount
                ? LoanRepaymentStatus::Paid
                : ($daysLate > 0 ? LoanRepaymentStatus::Late : LoanRepaymentStatus::Pending);
            $lockedRepayment->save();

            $loan->outstanding_amount = max(0, round((float) $loan->outstanding_amount - $paidAmount, 2));

            $allPaid = $loan->repayments()->where('status', '!=', LoanRepaymentStatus::Paid)->doesntExist();
            if ($allPaid || (float) $loan->outstanding_amount <= 0) {
                $loan->status = LoanStatus::Closed;
                $loan->outstanding_amount = 0;
            }

            $loan->save();

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'LOAN_REPAYMENT_RECORDED',
                'entity_type' => LoanRepayment::class,
                'entity_id' => $lockedRepayment->id,
                'details' => [
                    'loan_id' => $loan->id,
                    'paid_amount' => $paidAmount,
                    'payment_date' => $date->toDateString(),
                    'days_late' => $daysLate,
                    'comment' => $comment,
                ],
                'ip_address' => request()->ip(),
            ]);

            return $lockedRepayment->load('loan');
        });
    }

    public function generateSchedule(Loan $loan, CarbonInterface $fromDate): void
    {
        $quote = $this->simpleInterest->quote(
            (float) $loan->principal_amount,
            (int) $loan->duration_months,
            (float) $loan->annual_interest_rate_percent
        );

        $this->generateScheduleFromQuote($loan, $fromDate, $quote['schedule']);
    }

    /**
     * @param  list<array{installment: int, expected_amount: float}>  $schedule
     */
    protected function generateScheduleFromQuote(Loan $loan, CarbonInterface $fromDate, array $schedule): void
    {
        $loan->repayments()->delete();

        foreach ($schedule as $row) {
            $loan->repayments()->create([
                'due_date' => $fromDate->copy()->addMonths($row['installment'])->toDateString(),
                'expected_amount' => $row['expected_amount'],
                'paid_amount' => 0,
                'days_late' => 0,
                'status' => LoanRepaymentStatus::Pending,
            ]);
        }
    }
}
