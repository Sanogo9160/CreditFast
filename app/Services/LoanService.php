<?php

namespace App\Services;

use App\Enums\CreditRequestStatus;
use App\Enums\LoanRepaymentStatus;
use App\Enums\LoanStatus;
use App\Models\AuditLog;
use App\Models\CreditRequest;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoanService
{
    public function __construct(
        protected FinancialCalculationService $financialService,
        protected CreditWorkflowService $workflowService,
    ) {}

    /**
     * Persist the granted loan after a committee approval. The schedule is
     * generated only at disbursement so due dates follow the real value date.
     *
     * @param  array{approved_amount?: float|int|string, approved_duration_months?: int|string}  $decision
     */
    public function createApprovedLoan(CreditRequest $creditRequest, array $decision = []): Loan
    {
        $principal = (float) ($decision['approved_amount'] ?? $creditRequest->requested_amount);
        $duration = (int) ($decision['approved_duration_months'] ?? $creditRequest->duration_months);
        $rate = $this->financialService->defaultAnnualInterestRate();
        $monthlyPayment = $this->financialService->calculateEstimatedMonthlyPayment($principal, $duration, $rate);
        $totalInterest = round(($monthlyPayment * $duration) - $principal, 2);
        $totalAmount = round($principal + $totalInterest, 2);

        return Loan::updateOrCreate(
            ['credit_request_id' => $creditRequest->id],
            [
                'client_id' => $creditRequest->client_id,
                'principal_amount' => $principal,
                'interest_amount' => $totalInterest,
                'total_amount' => $totalAmount,
                'duration_months' => $duration,
                'monthly_payment' => $monthlyPayment,
                'disbursed_at' => null,
                'maturity_date' => null,
                'outstanding_amount' => $totalAmount,
                'status' => LoanStatus::Approved,
            ]
        );
    }

    public function disburse(Loan $loan, User $actor, ?CarbonInterface $disbursedAt = null, ?string $comment = null): Loan
    {
        return DB::transaction(function () use ($loan, $actor, $disbursedAt, $comment): Loan {
            $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $lockedLoan->loadMissing('client.user');

            $creditRequest = $lockedLoan->credit_request_id
                ? CreditRequest::query()->lockForUpdate()->findOrFail($lockedLoan->credit_request_id)
                : null;

            if ($lockedLoan->status !== LoanStatus::Approved) {
                throw new InvalidArgumentException('Seul un crédit déjà accordé peut être décaissé.');
            }

            if ($lockedLoan->repayments()->where('paid_amount', '>', 0)->exists()) {
                throw new InvalidArgumentException('Ce crédit a déjà des remboursements enregistrés.');
            }

            $valueDate = Carbon::parse($disbursedAt?->toDateString() ?? now()->toDateString());

            $lockedLoan->disbursed_at = $valueDate->toDateString();
            $lockedLoan->maturity_date = $valueDate->copy()->addMonths($lockedLoan->duration_months)->toDateString();
            $lockedLoan->status = LoanStatus::Active;
            $lockedLoan->save();

            $this->generateSchedule($lockedLoan, $valueDate);

            if ($creditRequest) {
                $creditRequest->loadMissing('client.user');
                $this->workflowService->transitionStatus(
                    $creditRequest,
                    CreditRequestStatus::Disbursed,
                    $actor,
                    $comment ?? 'Décaissement du crédit accordé'
                );
            }

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

            $notifyUserId = $creditRequest?->client?->user?->id ?? $lockedLoan->client?->user?->id;
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
        $loan->repayments()->delete();

        $duration = (int) $loan->duration_months;
        $monthlyPayment = (float) $loan->monthly_payment;

        for ($month = 1; $month <= $duration; $month++) {
            $loan->repayments()->create([
                'due_date' => $fromDate->copy()->addMonths($month)->toDateString(),
                'expected_amount' => $monthlyPayment,
                'paid_amount' => 0,
                'days_late' => 0,
                'status' => LoanRepaymentStatus::Pending,
            ]);
        }
    }
}
