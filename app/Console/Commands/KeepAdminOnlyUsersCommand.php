<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\KeepAdminOnlyUsers;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('users:keep-admin-only {--force : Run without confirmation}')]
#[Description('Delete all users and clients except the bootstrap admin account')]
class KeepAdminOnlyUsersCommand extends Command
{
    public function handle(KeepAdminOnlyUsers $keepAdminOnlyUsers): int
    {
        if (! $this->option('force') && ! $this->confirm('Supprimer tous les comptes sauf l’admin ?')) {
            $this->components->warn('Annulé.');

            return self::FAILURE;
        }

        $deleted = $keepAdminOnlyUsers();
        $admin = User::query()->where('email', config('credit.staff.admin_email'))->first();

        $this->components->info("Comptes supprimés : {$deleted}");
        $this->components->info('Admin conservé : '.($admin?->email ?? 'aucun'));

        return self::SUCCESS;
    }
}
