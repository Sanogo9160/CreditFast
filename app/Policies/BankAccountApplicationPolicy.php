<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\BankAccountApplication;
use App\Models\BankAccountApplicationDocument;
use App\Models\User;

class BankAccountApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::Client) || $user->hasAnyRole(RoleName::CreditAgent, RoleName::Admin);
    }

    public function view(User $user, BankAccountApplication $application): bool
    {
        if ($user->hasAnyRole(RoleName::CreditAgent, RoleName::Admin)) {
            return true;
        }

        return $user->hasRole(RoleName::Client)
            && $user->client?->id === $application->client_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Client) && $user->client !== null;
    }

    public function update(User $user, BankAccountApplication $application): bool
    {
        return $user->hasRole(RoleName::Client)
            && $user->client?->id === $application->client_id
            && $application->status->isEditableByClient();
    }

    public function submit(User $user, BankAccountApplication $application): bool
    {
        return $this->update($user, $application);
    }

    public function review(User $user, BankAccountApplication $application): bool
    {
        return $user->hasAnyRole(RoleName::CreditAgent, RoleName::Admin);
    }

    public function uploadDocument(User $user, BankAccountApplication $application): bool
    {
        if ($user->hasAnyRole(RoleName::CreditAgent, RoleName::Admin)) {
            return true;
        }

        return $this->update($user, $application);
    }

    public function viewDocument(User $user, BankAccountApplicationDocument $document): bool
    {
        return $this->view($user, $document->application);
    }
}
