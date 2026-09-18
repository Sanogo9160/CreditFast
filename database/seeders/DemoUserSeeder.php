<?php

namespace Database\Seeders;

use App\Enums\CreditRequestStatus;
use App\Enums\KycStatus;
use App\Models\Activity;
use App\Models\Client;
use App\Models\CreditRequest;
use App\Models\FinancialProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\CreditScoringEngine;
use App\Services\FinancialCalculationService;
use App\Services\OcrExtractionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $adminRole = Role::where('name', 'admin')->first();
        $analystRole = Role::where('name', 'analyst')->first();
        $committeeRole = Role::where('name', 'committee_member')->first();
        $agentRole = Role::where('name', 'credit_agent')->first();
        $clientRole = Role::where('name', 'client')->first();

        // 1. Staff Users
        User::updateOrCreate(
            ['email' => 'admin@creditfast.com'],
            [
                'first_name' => 'Amadou',
                'last_name' => 'Diallo',
                'phone' => '+22371910001',
                'password' => Hash::make('password'),
                'role_id' => $adminRole?->id,
                'status' => 'active',
            ]
        );

        $analyst = User::updateOrCreate(
            ['email' => 'analyste@creditfast.com'],
            [
                'first_name' => 'Fatoumata',
                'last_name' => 'Traoré',
                'phone' => '+22371910002',
                'password' => Hash::make('password'),
                'role_id' => $analystRole?->id,
                'status' => 'active',
            ]
        );

        $committee = User::updateOrCreate(
            ['email' => 'comite@creditfast.com'],
            [
                'first_name' => 'Moussa',
                'last_name' => 'Coulibaly',
                'phone' => '+22371910003',
                'password' => Hash::make('password'),
                'role_id' => $committeeRole?->id,
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'agent@creditfast.com'],
            [
                'first_name' => 'Ousmane',
                'last_name' => 'Sow',
                'phone' => '+22371910004',
                'password' => Hash::make('password'),
                'role_id' => $agentRole?->id,
                'status' => 'active',
            ]
        );

        // 2. Client Standard (with historical banking & credit history)
        $userStandard = User::updateOrCreate(
            ['email' => 'client.standard@creditfast.com'],
            [
                'first_name' => 'Ibrahim',
                'last_name' => 'Keita',
                'phone' => '+22375112233',
                'password' => Hash::make('password'),
                'role_id' => $clientRole?->id,
                'status' => 'active',
            ]
        );

        $clientStandard = Client::updateOrCreate(
            ['user_id' => $userStandard->id],
            [
                'client_number' => 'CLI-000101',
                'date_of_birth' => '1988-05-14',
                'address' => 'Commune IV, Hamdallaye ACI 2000',
                'city' => 'Bamako',
                'residential_zone' => 'ACI 2000',
                'occupation' => 'Commerçant Grossiste',
                'kyc_status' => KycStatus::Verified,
                'institution_verified_at' => now(),
            ]
        );

        $activityStandard = Activity::updateOrCreate(
            ['client_id' => $clientStandard->id, 'activity_type' => 'Commerce de Cereales'],
            [
                'sector' => 'Agro-alimentaire',
                'description' => 'Grossiste en céréales au marché de Medine',
                'start_date' => '2019-03-01',
                'location' => 'Marché de Medine, Bamako',
                'monthly_revenue' => 850000,
                'status' => 'ACTIVE',
            ]
        );

        $finService = new FinancialCalculationService;
        $disposableStandard = $finService->calculateDisposableIncome(850000, 100000, 300000, 50000);

        FinancialProfile::updateOrCreate(
            ['client_id' => $clientStandard->id],
            [
                'monthly_income' => 850000,
                'other_income' => 100000,
                'monthly_expenses' => 300000,
                'existing_debt_payment' => 50000,
                'dependents_count' => 4,
                'disposable_income' => $disposableStandard,
            ]
        );

        // Add Financial Account & Savings History
        $account = $clientStandard->financialAccounts()->updateOrCreate(
            ['account_number' => 'ACC-ML-2024-8891'],
            [
                'account_type' => 'SAVINGS',
                'balance' => 1250000,
                'opened_at' => '2020-01-15',
                'status' => 'ACTIVE',
            ]
        );

        $clientStandard->savingsHistories()->updateOrCreate(
            ['account_id' => $account->id],
            [
                'period_start' => now()->subMonths(6)->toDateString(),
                'period_end' => now()->toDateString(),
                'total_deposits' => 2400000,
                'total_withdrawals' => 1150000,
                'deposit_count' => 18,
                'withdrawal_count' => 8,
                'average_balance' => 950000,
                'closing_balance' => 1250000,
            ]
        );

        // 3. Client Cold Start (New Applicant without banking history)
        $userColdStart = User::updateOrCreate(
            ['email' => 'client.coldstart@creditfast.com'],
            [
                'first_name' => 'Aminata',
                'last_name' => 'Maïga',
                'phone' => '+22376998877',
                'password' => Hash::make('password'),
                'role_id' => $clientRole?->id,
                'status' => 'active',
            ]
        );

        $clientColdStart = Client::updateOrCreate(
            ['user_id' => $userColdStart->id],
            [
                'client_number' => 'CLI-000102',
                'date_of_birth' => '1995-11-20',
                'address' => 'Badalabougou, Rue 110',
                'city' => 'Bamako',
                'residential_zone' => 'Badalabougou',
                'occupation' => 'Couturière / Styliste',
                'kyc_status' => KycStatus::Pending,
            ]
        );

        $activityColdStart = Activity::updateOrCreate(
            ['client_id' => $clientColdStart->id, 'activity_type' => 'Atelier de Couture'],
            [
                'sector' => 'Artisanat & Textile',
                'description' => 'Confection de tenues traditionnelles et modernes',
                'start_date' => '2022-06-10',
                'location' => 'Badalabougou, Bamako',
                'monthly_revenue' => 450000,
                'status' => 'ACTIVE',
            ]
        );

        $disposableColdStart = $finService->calculateDisposableIncome(450000, 0, 180000, 0);

        FinancialProfile::updateOrCreate(
            ['client_id' => $clientColdStart->id],
            [
                'monthly_income' => 450000,
                'other_income' => 0,
                'monthly_expenses' => 180000,
                'existing_debt_payment' => 0,
                'dependents_count' => 2,
                'disposable_income' => $disposableColdStart,
            ]
        );

        // 4. Create Sample Credit Requests & Run Scoring Engine
        $ocrService = app(OcrExtractionService::class);
        $scoringEngine = app(CreditScoringEngine::class);

        // Standard Credit Request
        $reqStandard = CreditRequest::updateOrCreate(
            ['client_id' => $clientStandard->id, 'purpose' => 'Achat de stock céréales récolte'],
            [
                'activity_id' => $activityStandard->id,
                'requested_amount' => 1500000,
                'duration_months' => 12,
                'declared_monthly_income' => 850000,
                'declared_monthly_expenses' => 300000,
                'estimated_monthly_payment' => $finService->calculateEstimatedMonthlyPayment(1500000, 12),
                'disposable_income' => $disposableStandard,
                'repayment_capacity_status' => $finService->evaluateRepaymentCapacity($disposableStandard, 133000),
                'status' => CreditRequestStatus::Submitted,
                'submitted_at' => now()->subDays(2),
            ]
        );

        $docStandard = $reqStandard->documents()->updateOrCreate(
            ['original_filename' => 'releve_bancaire_keita.pdf'],
            [
                'document_type' => 'RELEVE_BANCAIRE',
                'file_path' => 'demo/releve_bancaire_keita.pdf',
                'mime_type' => 'application/pdf',
                'uploaded_by' => $userStandard->id,
                'uploaded_at' => now()->subDays(2),
                'status' => 'PROCESSED',
            ]
        );

        $ocrService->processDocumentExtraction($docStandard, [
            'confidence' => 96.0,
            'text' => 'Relevé officiel BOA Mali - Solde moyen 950,000 FCFA',
            'fields' => [
                'verified_monthly_income' => 850000,
                'average_balance' => 950000,
            ],
        ]);

        $scoringEngine->evaluateCreditRequest($reqStandard);

        // Cold Start Credit Request
        $reqColdStart = CreditRequest::updateOrCreate(
            ['client_id' => $clientColdStart->id, 'purpose' => 'Achat de 2 machines à coudre industrielles'],
            [
                'activity_id' => $activityColdStart->id,
                'requested_amount' => 600000,
                'duration_months' => 10,
                'declared_monthly_income' => 450000,
                'declared_monthly_expenses' => 180000,
                'estimated_monthly_payment' => $finService->calculateEstimatedMonthlyPayment(600000, 10),
                'disposable_income' => $disposableColdStart,
                'repayment_capacity_status' => $finService->evaluateRepaymentCapacity($disposableColdStart, 63000),
                'status' => CreditRequestStatus::Submitted,
                'submitted_at' => now()->subDays(1),
            ]
        );

        $docColdStart = $reqColdStart->documents()->updateOrCreate(
            ['original_filename' => 'registre_recettes_maiga.pdf'],
            [
                'document_type' => 'ATTESTATION_REVENU',
                'file_path' => 'demo/registre_recettes_maiga.pdf',
                'mime_type' => 'application/pdf',
                'uploaded_by' => $userColdStart->id,
                'uploaded_at' => now()->subDays(1),
                'status' => 'PROCESSED',
            ]
        );

        $ocrService->processDocumentExtraction($docColdStart, [
            'confidence' => 88.0,
            'text' => 'Carnet de recettes hebdomadaires atelier couture',
            'fields' => [
                'verified_monthly_income' => 420000,
            ],
        ]);

        $scoringEngine->evaluateCreditRequest($reqColdStart);
    }
}
