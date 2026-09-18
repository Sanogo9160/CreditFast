<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function view(User $user, Client $client): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->client?->id === $client->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function manageInstitutionalHistory(User $user, Client $client): bool
    {
        return $user->hasAnyRole(RoleName::Admin, RoleName::CreditAgent);
    }
}
