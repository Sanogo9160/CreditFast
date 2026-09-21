<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class KeepAdminOnlyUsers
{
    /**
     * Remove every account except the bootstrap admin, then ensure that admin exists.
     */
    public function __invoke(): int
    {
        $adminEmail = (string) config('credit.staff.admin_email');

        return (int) DB::transaction(function () use ($adminEmail): int {
            PersonalAccessToken::query()->delete();

            Client::query()
                ->orderBy('id')
                ->each(function (Client $client): void {
                    $client->delete();
                });

            $deleted = User::query()
                ->where('email', '!=', $adminEmail)
                ->delete();

            if (Role::query()->where('name', RoleName::Admin->value)->exists()) {
                (new AdminUserSeeder)->run();
            }

            return $deleted;
        });
    }
}
