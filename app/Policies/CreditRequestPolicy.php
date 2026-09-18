<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\CreditRequest;
use App\Models\User;

class CreditRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === 'active';
    }

    public function view(User $user, CreditRequest $creditRequest): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->client?->id === $creditRequest->client_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Client) && $user->client !== null;
    }

    public function submit(User $user, CreditRequest $creditRequest): bool
    {
        if ($user->hasRole(RoleName::CreditAgent) || $user->hasRole(RoleName::Admin)) {
            return true;
        }

        return $user->hasRole(RoleName::Client) && $user->client?->id === $creditRequest->client_id;
    }

    public function uploadDocument(User $user, CreditRequest $creditRequest): bool
    {
        return $this->submit($user, $creditRequest) || $user->hasAnyRole(RoleName::Analyst);
    }

    public function score(User $user, CreditRequest $creditRequest): bool
    {
        return $user->hasAnyRole(RoleName::Admin, RoleName::Analyst, RoleName::CreditAgent);
    }

    public function review(User $user, CreditRequest $creditRequest): bool
    {
        return $user->hasAnyRole(RoleName::Admin, RoleName::Analyst);
    }

    public function decide(User $user, CreditRequest $creditRequest): bool
    {
        return $user->hasAnyRole(RoleName::Admin, RoleName::CommitteeMember);
    }

    public function update(User $user, CreditRequest $creditRequest): bool
    {
        return $this->submit($user, $creditRequest);
    }

    public function delete(User $user, CreditRequest $creditRequest): bool
    {
        return $this->update($user, $creditRequest);
    }

    public function addGuarantee(User $user, CreditRequest $creditRequest): bool
    {
        return $this->submit($user, $creditRequest);
    }

    /**
     * Renvoie le dossier au client pour pièces ou informations complémentaires.
     * Réservé au chargé de crédit et à l’administrateur (l’analyste peut aussi
     * le faire via la revue ou une validation TO_COMPLETE).
     */
    public function requestComplements(User $user, CreditRequest $creditRequest): bool
    {
        return $user->hasAnyRole(RoleName::Admin, RoleName::CreditAgent, RoleName::Analyst);
    }

    public function sendToAnalysis(User $user, CreditRequest $creditRequest): bool
    {
        return $user->hasAnyRole(RoleName::Admin, RoleName::CreditAgent, RoleName::Analyst);
    }
}
