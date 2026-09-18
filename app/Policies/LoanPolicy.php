<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LoanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === 'active';
    }

    public function view(User $user, Loan $loan): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->client?->id === $loan->client_id;
    }

    /**
     * Décaissement : chargé de crédit ou admin uniquement. Le client consulte seulement.
     */
    public function disburse(User $user, Loan $loan): Response
    {
        if ($user->hasAnyRole(RoleName::Admin, RoleName::CreditAgent)) {
            return Response::allow();
        }

        return Response::deny(
            'Le décaissement est réalisé par l’équipe de crédit. Vous pouvez consulter l’état de votre prêt, le montant reçu et votre échéancier.'
        );
    }

    /**
     * Enregistrement d’un remboursement : chargé de crédit ou admin uniquement.
     */
    public function recordRepayment(User $user, Loan $loan): Response
    {
        if ($user->hasAnyRole(RoleName::Admin, RoleName::CreditAgent)) {
            return Response::allow();
        }

        return Response::deny(
            'L’enregistrement d’un remboursement est réalisé par l’équipe de crédit. Vous pouvez consulter votre échéancier et le montant restant dû.'
        );
    }
}
