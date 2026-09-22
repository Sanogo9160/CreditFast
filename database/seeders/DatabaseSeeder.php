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
            // Full staff + demo clients for the test suite only.
            $this->call([
                StaffUserSeeder::class,
                DemoUserSeeder::class,
            ]);

            return;
        }

        // Local / production bootstrap: admin account only.
        $this->call(AdminUserSeeder::class);
    }
}
