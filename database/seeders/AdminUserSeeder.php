<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::query()->where('name', RoleName::Admin->value)->firstOrFail();
        $password = (string) config('credit.staff.password');
        $email = (string) config('credit.staff.admin_email');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'first_name' => 'Admin',
                'last_name' => 'Crédit Fast',
                'phone' => '+22370000001',
                'password' => $password,
                'role_id' => $role->id,
                'status' => 'active',
            ]
        );
    }
}
