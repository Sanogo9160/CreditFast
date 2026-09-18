<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'admin', 'description' => 'Administrateur système et gestionnaire des modèles de scoring'],
            ['name' => 'credit_agent', 'description' => 'Chargé de crédit / Agent de saisie et de suivi'],
            ['name' => 'analyst', 'description' => 'Analyste de risques et vérificateur des anomalies'],
            ['name' => 'committee_member', 'description' => 'Membre du Comité de décision d’octroi de crédit'],
            ['name' => 'client', 'description' => 'Demandeur de crédit / Client microfinance'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
