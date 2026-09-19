<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::Admin);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRole(RoleName::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Admin);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRole(RoleName::Admin);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRole(RoleName::Admin) && $user->id !== $model->id;
    }

    public function resetPassword(User $user, User $model): bool
    {
        return $user->hasRole(RoleName::Admin) && $user->id !== $model->id;
    }

    public function viewProfilePhoto(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->isStaff();
    }
}
