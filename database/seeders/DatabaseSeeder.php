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
            StaffUserSeeder::class,
        ]);

        if (app()->runningUnitTests()) {
            $this->call(DemoUserSeeder::class);
        }
    }
}
