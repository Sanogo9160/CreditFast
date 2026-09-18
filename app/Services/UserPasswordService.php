<?php

namespace App\Services;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class UserPasswordService
{
    public function change(User $user, string $password, mixed $currentToken = null): void
    {
        $user->update(['password' => $password]);

        $query = $user->tokens();

        if ($currentToken instanceof PersonalAccessToken) {
            $query->where('id', '!=', $currentToken->id);
        }

        $query->delete();
    }

    public function reset(User $user, string $password): void
    {
        $this->change($user, $password);
    }
}
