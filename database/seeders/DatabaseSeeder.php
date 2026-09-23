<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ScoringModelSeeder::class,
            CaisseSeeder::class,
        ]);

        if (app()->runningUnitTests()) {
            $this->call([
                StaffUserSeeder::class,
                DemoUserSeeder::class,
                HackathonInstitutionalClientsSeeder::class,
                TestUsersSeeder::class,
            ]);

            return;
        }

        if (app()->isProduction()) {
            $this->call(AdminUserSeeder::class);

            return;
        }

        // Local / hackathon : staff + clients IMF + utilisateurs d’essai atelier.
        $this->call([
            AdminUserSeeder::class,
            StaffUserSeeder::class,
            DemoUserSeeder::class,
            HackathonInstitutionalClientsSeeder::class,
            TestUsersSeeder::class,
        ]);
    }
}
