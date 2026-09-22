<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\FieldVisit;
use App\Models\User;

class FieldVisitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, FieldVisit $fieldVisit): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->hasRole(RoleName::Client)
            && $user->client?->id === $fieldVisit->client_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(RoleName::CreditAgent, RoleName::Admin);
    }

    public function update(User $user, FieldVisit $fieldVisit): bool
    {
        return $user->hasAnyRole(RoleName::CreditAgent, RoleName::Admin);
    }

    public function complete(User $user, FieldVisit $fieldVisit): bool
    {
        return $this->update($user, $fieldVisit);
    }

    public function cancel(User $user, FieldVisit $fieldVisit): bool
    {
        return $this->update($user, $fieldVisit);
    }
}
