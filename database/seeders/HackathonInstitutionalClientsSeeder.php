<?php

namespace Database\Seeders;

use App\Enums\ClientType;
use App\Enums\KycStatus;
use App\Enums\LegalForm;
use App\Models\Activity;
use App\Models\Caisse;
use App\Models\CashDesk;
use App\Models\Client;
use App\Models\FinancialProfile;
use App\Models\Guichet;
use App\Models\Role;
use App\Models\User;
use App\Services\FinancialCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Jeu de données hackathon : simule la base clients/comptes de l’IMF.
 *
 * Avec compte → peuvent enchaîner une demande de crédit.
 * Sans compte → 422 orienté vers la fiche d’adhésion PP/PM.
 */
class HackathonInstitutionalClientsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $clientRole = Role::query()->where('name', 'client')->firstOrFail();
        $finService = app(FinancialCalculationService::class);

        $bko = Caisse::query()->where('code', 'BKO')->firstOrFail();
        $bkoGuichet = Guichet::query()->where('caisse_id', $bko->id)->where('code', 'G01')->firstOrFail();
        $bkoCase = CashDesk::query()->where('guichet_id', $bkoGuichet->id)->where('code', 'C01')->firstOrFail();

        $sko = Caisse::query()->where('code', 'SKO')->firstOrFail();
        $skoGuichet = Guichet::query()->where('caisse_id', $sko->id)->where('code', 'G01')->firstOrFail();
        $skoCase = CashDesk::query()->where('guichet_id', $skoGuichet->id)->where('code', 'C01')->firstOrFail();

        $this->seedPhysicalPersonWithAccount(
            $clientRole->id,
            $finService,
            email: 'awa.pp.banked@creditfast.com',
            phone: '+22370111101',
            firstName: 'Awa',
            lastName: 'Diarra',
            clientNumber: 'CLI-HK-0001',
            accountNumber: 'BKO-G01-2025900001',
            balance: 1_250_000,
            caisseId: $bko->id,
            guichetId: $bkoGuichet->id,
            cashDeskId: $bkoCase->id,
            occupation: 'Commerçante',
            activityType: 'Commerce de détail',
            sector: 'Commerce',
            monthlyRevenue: 650_000,
        );

        $this->seedLegalEntityWithAccount(
            $clientRole->id,
            $finService,
            email: 'sarl.textile@creditfast.com',
            phone: '+22370222201',
            firstName: 'Fatou',
            lastName: 'Traore',
            clientNumber: 'CLI-HK-0010',
            companyName: 'SARL Textile Bamako',
            tradeName: 'TexBa',
            registrationNumber: 'MA.BKO.2024.B.88001',
            legalForm: LegalForm::Sarl,
            accountNumber: 'BKO-G01-2025900010',
            balance: 4_500_000,
            accountType: 'CURRENT',
            caisseId: $bko->id,
            guichetId: $bkoGuichet->id,
            cashDeskId: $bkoCase->id,
            activityType: 'Production textile',
            sector: 'Industrie légère',
            monthlyRevenue: 3_200_000,
        );

        $this->seedLegalEntityWithAccount(
            $clientRole->id,
            $finService,
            email: 'gie.maraichers@creditfast.com',
            phone: '+22370333301',
            firstName: 'Moussa',
            lastName: 'Keita',
            clientNumber: 'CLI-HK-0011',
            companyName: 'GIE Maraîchers Sud',
            tradeName: 'Maraîchers Sud',
            registrationNumber: 'MA.SKO.2023.B.44002',
            legalForm: LegalForm::Gie,
            accountNumber: 'SKO-G01-2025900001',
            balance: 1_800_000,
            accountType: 'CURRENT',
            caisseId: $sko->id,
            guichetId: $skoGuichet->id,
            cashDeskId: $skoCase->id,
            activityType: 'Maraîchage',
            sector: 'Agriculture',
            monthlyRevenue: 900_000,
        );

        $this->seedPhysicalPersonWithoutAccount(
            $clientRole->id,
            email: 'binta.noaccount@creditfast.com',
            phone: '+22370444401',
            firstName: 'Binta',
            lastName: 'Coulibaly',
            clientNumber: 'CLI-HK-0002',
        );

        $this->seedLegalEntityWithoutAccount(
            $clientRole->id,
            email: 'sa.cereales.noaccount@creditfast.com',
            phone: '+22370555501',
            firstName: 'Seydou',
            lastName: 'Sangare',
            clientNumber: 'CLI-HK-0012',
            companyName: 'SA Céréales Nord',
            tradeName: 'Céréales Nord',
            registrationNumber: 'MA.BKO.2021.B.55003',
            legalForm: LegalForm::Sa,
        );
    }

    private function seedPhysicalPersonWithAccount(
        int $roleId,
        FinancialCalculationService $finService,
        string $email,
        string $phone,
        string $firstName,
        string $lastName,
        string $clientNumber,
        string $accountNumber,
        float $balance,
        int $caisseId,
        int $guichetId,
        int $cashDeskId,
        string $occupation,
        string $activityType,
        string $sector,
        float $monthlyRevenue,
    ): void {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'password' => Hash::make('password'),
                'role_id' => $roleId,
                'status' => 'active',
            ]
        );

        $client = Client::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'client_number' => $clientNumber,
                'client_type' => ClientType::PhysicalPerson,
                'date_of_birth' => '1992-04-18',
                'address' => 'ACI 2000, Bamako',
                'city' => 'Bamako',
                'residential_zone' => match (Caisse::query()->find($caisseId)?->code) {
                    'SKO' => 'SKO-CENTRE',
                    'SGO' => 'SGO-CENTRE',
                    default => 'BKO-CENTRE',
                },
                'occupation' => $occupation,
                'kyc_status' => KycStatus::Verified,
                'institution_verified_at' => now(),
            ]
        );

        $expenses = (int) round($monthlyRevenue * 0.35);
        $disposable = $finService->calculateDisposableIncome($monthlyRevenue, 0, $expenses, 0);

        FinancialProfile::query()->updateOrCreate(
            ['client_id' => $client->id],
            [
                'monthly_income' => $monthlyRevenue,
                'other_income' => 0,
                'monthly_expenses' => $expenses,
                'existing_debt_payment' => 0,
                'dependents_count' => 2,
                'disposable_income' => $disposable,
            ]
        );

        Activity::query()->updateOrCreate(
            ['client_id' => $client->id, 'activity_type' => $activityType],
            [
                'sector' => $sector,
                'description' => $activityType,
                'start_date' => '2021-01-15',
                'location' => 'Bamako',
                'monthly_revenue' => $monthlyRevenue,
                'status' => 'ACTIVE',
            ]
        );

        $account = $client->financialAccounts()->updateOrCreate(
            ['account_number' => $accountNumber],
            [
                'caisse_id' => $caisseId,
                'guichet_id' => $guichetId,
                'cash_desk_id' => $cashDeskId,
                'account_type' => 'SAVINGS',
                'balance' => $balance,
                'available_balance' => $balance,
                'blocked_balance' => 0,
                'agency_code' => Caisse::query()->find($caisseId)?->code,
                'opened_at' => '2024-01-10',
                'status' => 'ACTIVE',
            ]
        );

        $client->savingsHistories()->updateOrCreate(
            ['account_id' => $account->id],
            [
                'period_start' => now()->subMonths(6)->toDateString(),
                'period_end' => now()->toDateString(),
                'total_deposits' => $balance * 1.6,
                'total_withdrawals' => $balance * 0.6,
                'deposit_count' => 12,
                'withdrawal_count' => 6,
                'average_balance' => $balance * 0.85,
                'closing_balance' => $balance,
            ]
        );
    }

    private function seedLegalEntityWithAccount(
        int $roleId,
        FinancialCalculationService $finService,
        string $email,
        string $phone,
        string $firstName,
        string $lastName,
        string $clientNumber,
        string $companyName,
        string $tradeName,
        string $registrationNumber,
        LegalForm $legalForm,
        string $accountNumber,
        float $balance,
        string $accountType,
        int $caisseId,
        int $guichetId,
        int $cashDeskId,
        string $activityType,
        string $sector,
        float $monthlyRevenue,
    ): void {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'password' => Hash::make('password'),
                'role_id' => $roleId,
                'status' => 'active',
            ]
        );

        $client = Client::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'client_number' => $clientNumber,
                'client_type' => ClientType::LegalEntity,
                'company_name' => $companyName,
                'trade_name' => $tradeName,
                'registration_number' => $registrationNumber,
                'legal_form' => $legalForm,
                'address' => 'Zone industrielle',
                'city' => 'Bamako',
                'residential_zone' => match (Caisse::query()->find($caisseId)?->code) {
                    'SKO' => 'SKO-CENTRE',
                    'SGO' => 'SGO-CENTRE',
                    default => 'BKO-CENTRE',
                },
                'occupation' => 'Représentant légal',
                'kyc_status' => KycStatus::Verified,
                'institution_verified_at' => now(),
            ]
        );

        $expenses = (int) round($monthlyRevenue * 0.4);
        $disposable = $finService->calculateDisposableIncome($monthlyRevenue, 0, $expenses, 0);

        FinancialProfile::query()->updateOrCreate(
            ['client_id' => $client->id],
            [
                'monthly_income' => $monthlyRevenue,
                'other_income' => 0,
                'monthly_expenses' => $expenses,
                'existing_debt_payment' => 0,
                'dependents_count' => 0,
                'disposable_income' => $disposable,
            ]
        );

        Activity::query()->updateOrCreate(
            ['client_id' => $client->id, 'activity_type' => $activityType],
            [
                'sector' => $sector,
                'description' => $companyName,
                'start_date' => '2019-06-01',
                'location' => 'Mali',
                'monthly_revenue' => $monthlyRevenue,
                'status' => 'ACTIVE',
            ]
        );

        $account = $client->financialAccounts()->updateOrCreate(
            ['account_number' => $accountNumber],
            [
                'caisse_id' => $caisseId,
                'guichet_id' => $guichetId,
                'cash_desk_id' => $cashDeskId,
                'account_type' => $accountType === 'CURRENT' ? 'SAVINGS' : $accountType,
                'balance' => $balance,
                'available_balance' => $balance,
                'blocked_balance' => 0,
                'agency_code' => Caisse::query()->find($caisseId)?->code,
                'opened_at' => '2023-05-20',
                'status' => 'ACTIVE',
            ]
        );

        $client->savingsHistories()->updateOrCreate(
            ['account_id' => $account->id],
            [
                'period_start' => now()->subMonths(6)->toDateString(),
                'period_end' => now()->toDateString(),
                'total_deposits' => $balance * 1.4,
                'total_withdrawals' => $balance * 0.5,
                'deposit_count' => 10,
                'withdrawal_count' => 4,
                'average_balance' => $balance * 0.9,
                'closing_balance' => $balance,
            ]
        );
    }

    private function seedPhysicalPersonWithoutAccount(
        int $roleId,
        string $email,
        string $phone,
        string $firstName,
        string $lastName,
        string $clientNumber,
    ): void {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'password' => Hash::make('password'),
                'role_id' => $roleId,
                'status' => 'active',
            ]
        );

        Client::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'client_number' => $clientNumber,
                'client_type' => ClientType::PhysicalPerson,
                'date_of_birth' => '1998-09-02',
                'address' => 'Badalabougou',
                'city' => 'Bamako',
                'residential_zone' => 'BKO-CENTRE',
                'occupation' => 'Commerçante débutante',
                'kyc_status' => KycStatus::Pending,
            ]
        );
    }

    private function seedLegalEntityWithoutAccount(
        int $roleId,
        string $email,
        string $phone,
        string $firstName,
        string $lastName,
        string $clientNumber,
        string $companyName,
        string $tradeName,
        string $registrationNumber,
        LegalForm $legalForm,
    ): void {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'password' => Hash::make('password'),
                'role_id' => $roleId,
                'status' => 'active',
            ]
        );

        Client::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'client_number' => $clientNumber,
                'client_type' => ClientType::LegalEntity,
                'company_name' => $companyName,
                'trade_name' => $tradeName,
                'registration_number' => $registrationNumber,
                'legal_form' => $legalForm,
                'city' => 'Bamako',
                'occupation' => 'Représentant légal',
                'kyc_status' => KycStatus::Pending,
            ]
        );
    }
}
