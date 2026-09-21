<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\KeepAdminOnlyUsers;
use Database\Seeders\StaffUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeepAdminOnlyUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_keeps_only_the_bootstrap_admin_account(): void
    {
        $this->seed(StaffUserSeeder::class);

        $this->assertGreaterThan(1, User::query()->count());

        $deleted = app(KeepAdminOnlyUsers::class)();

        $this->assertGreaterThan(0, $deleted);
        $this->assertSame(1, User::query()->count());
        $this->assertDatabaseHas('users', [
            'email' => config('credit.staff.admin_email'),
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => config('credit.staff.agent_email'),
        ]);
    }

    public function test_artisan_command_purges_non_admin_users(): void
    {
        $this->seed(StaffUserSeeder::class);

        $this->artisan('users:keep-admin-only', ['--force' => true])
            ->assertSuccessful();

        $this->assertSame(1, User::query()->count());
        $this->assertDatabaseHas('users', [
            'email' => config('credit.staff.admin_email'),
        ]);
    }
}
