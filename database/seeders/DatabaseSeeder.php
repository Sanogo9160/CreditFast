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
            $this->call($this->demoSeeders());

            return;
        }

        if (app()->isProduction()) {
            $this->call(AdminUserSeeder::class);

            if (config('credit.seed_demo_users')) {
                $this->call($this->demoSeeders());
            }

            return;
        }

        // Local / hackathon : staff + clients IMF + utilisateurs d’essai atelier.
        $this->call([
            AdminUserSeeder::class,
            ...$this->demoSeeders(),
        ]);
    }

    /**
     * @return list<class-string<Seeder>>
     */
    protected function demoSeeders(): array
    {
        return [
            StaffUserSeeder::class,
            DemoUserSeeder::class,
            HackathonInstitutionalClientsSeeder::class,
            TestUsersSeeder::class,
        ];
    }
}
