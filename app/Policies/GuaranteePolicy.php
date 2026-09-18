<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Guarantee;
use App\Models\User;

class GuaranteePolicy
{
    public function view(User $user, Guarantee $guarantee): bool
    {
        $guarantee->loadMissing('creditRequest');

        return $user->can('view', $guarantee->creditRequest);
    }

    public function update(User $user, Guarantee $guarantee): bool
    {
        $guarantee->loadMissing('creditRequest');

        return $user->can('addGuarantee', $guarantee->creditRequest);
    }

    public function delete(User $user, Guarantee $guarantee): bool
    {
        return $this->update($user, $guarantee);
    }

    public function verify(User $user, Guarantee $guarantee): bool
    {
        return $user->hasAnyRole(RoleName::Admin, RoleName::CreditAgent, RoleName::Analyst);
    }
}
