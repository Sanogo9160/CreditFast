<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\ScoringModel;
use App\Models\User;

class ScoringModelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Admin);
    }

    public function update(User $user, ScoringModel $scoringModel): bool
    {
        return $user->hasRole(RoleName::Admin);
    }
}
