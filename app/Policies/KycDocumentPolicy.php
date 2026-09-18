<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\KycDocument;
use App\Models\User;

class KycDocumentPolicy
{
    public function view(User $user, KycDocument $document): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->client?->id === $document->client_id;
    }

    public function verify(User $user, KycDocument $document): bool
    {
        return $user->hasAnyRole(RoleName::Admin, RoleName::CreditAgent, RoleName::Analyst);
    }

    public function delete(User $user, KycDocument $document): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->client?->id === $document->client_id;
    }
}
