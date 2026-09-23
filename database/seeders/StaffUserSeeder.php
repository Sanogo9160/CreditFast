<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaffUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) config('credit.staff.password');

        $accounts = [
            [
                'email' => (string) config('credit.staff.admin_email'),
                'role' => RoleName::Admin,
                'first_name' => 'Admin',
                'last_name' => 'Crédit Fast',
                'phone' => '+22371910001',
            ],
            [
                'email' => (string) config('credit.staff.agent_email'),
                'role' => RoleName::CreditAgent,
                'first_name' => 'Chargé',
                'last_name' => 'Crédit',
                'phone' => '+22371910002',
            ],
            [
                'email' => (string) config('credit.staff.analyst_email'),
                'role' => RoleName::Analyst,
                'first_name' => 'Analyste',
                'last_name' => 'Risques',
                'phone' => '+22371910003',
            ],
            [
                'email' => (string) config('credit.staff.committee_email'),
                'role' => RoleName::CommitteeMember,
                'first_name' => 'Membre',
                'last_name' => 'Comité',
                'phone' => '+22371910004',
            ],
        ];

        foreach ($accounts as $account) {
            $role = Role::query()->where('name', $account['role']->value)->firstOrFail();

            User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'first_name' => $account['first_name'],
                    'last_name' => $account['last_name'],
                    'phone' => $account['phone'],
                    'password' => $password,
                    'role_id' => $role->id,
                    'status' => 'active',
                ]
            );
        }
    }
}
