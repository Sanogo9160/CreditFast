<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Anomaly;
use App\Models\User;

class AnomalyPolicy
{
    public function resolve(User $user, Anomaly $anomaly): bool
    {
        return $user->hasAnyRole(RoleName::Admin, RoleName::Analyst);
    }
}
