<?php

namespace Database\Seeders;

use App\Enums\ClientType;
use App\Enums\KycStatus;
use App\Enums\RoleName;
use App\Models\AccountTransaction;
use App\Models\Activity;
use App\Models\Caisse;
use App\Models\CashDesk;
use App\Models\Client;
use App\Models\FinancialAccount;
use App\Models\FinancialProfile;
use App\Models\Guichet;
use App\Models\KycDocument;
use App\Models\Role;
use App\Models\RoutingZone;
use App\Models\SavingsHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Charge les utilisateurs d’essai du magasin local (test-users.md).
 * Mot de passe atelier : demo-local
 *
 * Clients scénario crédit (login téléphone) :
 * - +22370000001 — compte EPARGNE/ACTIF BKO-HAM + zone HAM → create + submit OK
 * - +22370000002 — aucun compte financier → 422 InstitutionalAccountRequirement à la création
 */
class TestUsersSeeder extends Seeder
{
    public const PASSWORD = 'demo-local';

    public function run(): void
    {
        $this->seedAgenciesAndZones();
        $this->seedStaff();
        $this->seedClients();
    }

    protected function seedAgenciesAndZones(): void
    {
        $agencies = [
            ['code' => 'BKO-HAM', 'name' => 'Agence Hamdallaye', 'city' => 'Bamako', 'zones' => [
                ['code' => 'HAM', 'name' => 'Hamdallaye'],
            ]],
            ['code' => 'SEG-CEN', 'name' => 'Agence Ségou Centre', 'city' => 'Ségou', 'zones' => [
                ['code' => 'SEG', 'name' => 'Ségou Centre'],
            ]],
            ['code' => 'BKO-KAL', 'name' => 'Agence Kalabancoura', 'city' => 'Bamako', 'zones' => [
                ['code' => 'KAL', 'name' => 'Kalabancoura'],
            ]],
            ['code' => 'BKO-FAL', 'name' => 'Agence Faladié', 'city' => 'Bamako', 'zones' => [
                ['code' => 'FAL', 'name' => 'Faladié'],
            ]],
        ];

        foreach ($agencies as $agency) {
            $caisse = Caisse::query()->updateOrCreate(
                ['code' => $agency['code']],
                [
                    'name' => $agency['name'],
                    'city' => $agency['city'],
                    'is_active' => true,
                ]
            );

            $guichet = Guichet::query()->updateOrCreate(
                ['caisse_id' => $caisse->id, 'code' => 'G01'],
                ['name' => 'Guichet principal', 'is_active' => true]
            );

            CashDesk::query()->updateOrCreate(
                ['guichet_id' => $guichet->id, 'code' => 'C01'],
                ['label' => 'Case 1', 'is_active' => true]
            );

            foreach ($agency['zones'] as $zone) {
                RoutingZone::query()->updateOrCreate(
                    ['agency_code' => $agency['code'], 'code' => $zone['code']],
                    [
                        'name' => $zone['name'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    protected function seedStaff(): void
    {
        $staff = [
            [
                'email' => 'admin@demo.creditfast',
                'first_name' => 'Administrateur',
                'last_name' => 'Demo',
                'role' => RoleName::Admin,
                'agency_code' => null,
                'zone_codes' => null,
                'phone' => '+22371920000',
            ],
            [
                'email' => 'agent@demo.creditfast',
                'first_name' => 'Agent',
                'last_name' => 'Hamdallaye',
                'role' => RoleName::CreditAgent,
                'agency_code' => 'BKO-HAM',
                'zone_codes' => ['HAM'],
                'phone' => '+22371920001',
            ],
            [
                'email' => 'analyst@demo.creditfast',
                'first_name' => 'Analyste',
                'last_name' => 'Hamdallaye',
                'role' => RoleName::Analyst,
                'agency_code' => 'BKO-HAM',
                'zone_codes' => ['HAM'],
                'phone' => '+22371920002',
            ],
            [
                'email' => 'committee@demo.creditfast',
                'first_name' => 'Comité',
                'last_name' => 'Hamdallaye',
                'role' => RoleName::CommitteeMember,
                'agency_code' => 'BKO-HAM',
                'zone_codes' => ['HAM'],
                'phone' => '+22371920003',
            ],
            [
                'email' => 'agent.segou@demo.creditfast',
                'first_name' => 'Agent',
                'last_name' => 'Ségou',
                'role' => RoleName::CreditAgent,
                'agency_code' => 'SEG-CEN',
                'zone_codes' => ['SEG'],
                'phone' => '+22371920004',
            ],
            [
                'email' => 'analyst.segou@demo.creditfast',
                'first_name' => 'Analyste',
                'last_name' => 'Ségou',
                'role' => RoleName::Analyst,
                'agency_code' => 'SEG-CEN',
                'zone_codes' => ['SEG'],
                'phone' => '+22371920005',
            ],
            [
                'email' => 'committee.segou@demo.creditfast',
                'first_name' => 'Comité',
                'last_name' => 'Ségou',
                'role' => RoleName::CommitteeMember,
                'agency_code' => 'SEG-CEN',
                'zone_codes' => ['SEG'],
                'phone' => '+22371920006',
            ],
            [
                'email' => 'agent.kalabancoura@demo.creditfast',
                'first_name' => 'Agent',
                'last_name' => 'Kalabancoura',
                'role' => RoleName::CreditAgent,
                'agency_code' => 'BKO-KAL',
                'zone_codes' => ['KAL'],
                'phone' => '+22371920007',
            ],
            [
                'email' => 'analyst.kalabancoura@demo.creditfast',
                'first_name' => 'Analyste',
                'last_name' => 'Kalabancoura',
                'role' => RoleName::Analyst,
                'agency_code' => 'BKO-KAL',
                'zone_codes' => ['KAL'],
                'phone' => '+22371920008',
            ],
            [
                'email' => 'committee.kalabancoura@demo.creditfast',
                'first_name' => 'Comité',
                'last_name' => 'Kalabancoura',
                'role' => RoleName::CommitteeMember,
                'agency_code' => 'BKO-KAL',
                'zone_codes' => ['KAL'],
                'phone' => '+22371920009',
            ],
            [
                'email' => 'agent.faladie@demo.creditfast',
                'first_name' => 'Agent',
                'last_name' => 'Faladié',
                'role' => RoleName::CreditAgent,
                'agency_code' => 'BKO-FAL',
                'zone_codes' => ['FAL'],
                'phone' => '+22371920010',
            ],
            [
                'email' => 'analyst.faladie@demo.creditfast',
                'first_name' => 'Analyste',
                'last_name' => 'Faladié',
                'role' => RoleName::Analyst,
                'agency_code' => 'BKO-FAL',
                'zone_codes' => ['FAL'],
                'phone' => '+22371920011',
            ],
            [
                'email' => 'committee.faladie@demo.creditfast',
                'first_name' => 'Comité',
                'last_name' => 'Faladié',
                'role' => RoleName::CommitteeMember,
                'agency_code' => 'BKO-FAL',
                'zone_codes' => ['FAL'],
                'phone' => '+22371920012',
            ],
        ];

        foreach ($staff as $row) {
            $role = Role::query()->where('name', $row['role']->value)->firstOrFail();

            User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'phone' => $row['phone'],
                    'password' => Hash::make(self::PASSWORD),
                    'role_id' => $role->id,
                    'status' => 'active',
                    'agency_code' => $row['agency_code'],
                    'zone_codes' => $row['zone_codes'],
                    'available' => true,
                ]
            );
        }
    }

    protected function seedClients(): void
    {
        $clientRole = Role::query()->where('name', RoleName::Client->value)->firstOrFail();

        $clients = [
            [
                'phone' => '+22370000001',
                'first_name' => 'Amadou',
                'last_name' => 'Koné',
                'client_number' => 'DEMO-1',
                'agency_code' => 'BKO-HAM',
                'zone' => 'HAM',
                'city' => 'Bamako',
                'address' => 'Hamdallaye ACI, Bamako',
                'occupation' => 'Commerçant',
                'activity_type' => 'Commerce de détail',
                'sector' => 'Commerce',
                'account_number' => 'DEMO-EP-1',
                'balance' => 500000,
                'has_cni' => true,
                'income' => 450000,
                'expenses' => 150000,
            ],
            [
                'phone' => '+22370000002',
                'first_name' => 'Fatoumata',
                'last_name' => 'Diallo',
                'client_number' => 'DEMO-2',
                'agency_code' => 'BKO-HAM',
                'zone' => 'HAM',
                'city' => 'Bamako',
                'address' => 'Hamdallaye, Bamako',
                'occupation' => 'Commerçante',
                'activity_type' => 'Commerce de détail',
                'sector' => 'Commerce',
                'account_number' => null,
                'balance' => 0,
                'has_cni' => true,
                'income' => 450000,
                'expenses' => 150000,
            ],
            [
                'phone' => '+22370000003',
                'first_name' => 'Profil',
                'last_name' => 'à compléter',
                'client_number' => 'DEMO-3',
                'agency_code' => 'BKO-HAM',
                'zone' => 'HAM',
                'city' => 'Bamako',
                'address' => 'Bamako',
                'occupation' => 'Commerce',
                'activity_type' => null,
                'sector' => null,
                'account_number' => 'DEMO-EP-3',
                'balance' => 500000,
                'has_cni' => false,
                'income' => 450000,
                'expenses' => 150000,
            ],
            [
                'phone' => '+22370000004',
                'first_name' => 'Ibrahim',
                'last_name' => 'Touré',
                'client_number' => 'DEMO-4',
                'agency_code' => 'SEG-CEN',
                'zone' => 'SEG',
                'city' => 'Ségou',
                'address' => 'Ségou Centre',
                'occupation' => 'Commerçant',
                'activity_type' => 'Commerce de détail',
                'sector' => 'Commerce',
                'account_number' => 'DEMO-EP-SEGOU',
                'balance' => 500000,
                'has_cni' => true,
                'income' => 450000,
                'expenses' => 150000,
            ],
        ];

        foreach ($clients as $row) {
            $user = User::query()->updateOrCreate(
                ['phone' => $row['phone']],
                [
                    'email' => null,
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'password' => Hash::make(self::PASSWORD),
                    'role_id' => $clientRole->id,
                    'status' => 'active',
                    'agency_code' => null,
                    'zone_codes' => null,
                    'available' => true,
                ]
            );

            $client = Client::query()->updateOrCreate(
                ['client_number' => $row['client_number']],
                [
                    'user_id' => $user->id,
                    'client_type' => ClientType::PhysicalPerson,
                    'date_of_birth' => '1990-01-15',
                    'address' => $row['address'],
                    'city' => $row['city'],
                    'residential_zone' => $row['zone'],
                    'occupation' => $row['occupation'],
                    'kyc_status' => $row['has_cni'] ? KycStatus::Verified : KycStatus::Pending,
                    'institution_verified_at' => $row['account_number'] ? now() : null,
                ]
            );

            FinancialProfile::query()->updateOrCreate(
                ['client_id' => $client->id],
                [
                    'monthly_income' => $row['income'],
                    'other_income' => 0,
                    'monthly_expenses' => $row['expenses'],
                    'existing_debt_payment' => 0,
                    'dependents_count' => 0,
                    'disposable_income' => $row['income'] - $row['expenses'],
                ]
            );

            if (filled($row['activity_type'])) {
                Activity::query()->updateOrCreate(
                    [
                        'client_id' => $client->id,
                        'activity_type' => $row['activity_type'],
                    ],
                    [
                        'sector' => $row['sector'],
                        'description' => $row['activity_type'],
                        'start_date' => '2021-03-01',
                        'location' => $row['city'],
                        'monthly_revenue' => $row['income'],
                        'status' => 'ACTIVE',
                    ]
                );
            }

            if ($row['has_cni']) {
                KycDocument::query()->updateOrCreate(
                    [
                        'client_id' => $client->id,
                        'document_type' => 'CNI',
                        'document_number' => 'CNI-'.$row['client_number'],
                    ],
                    [
                        'file_path' => 'demo/kyc/'.$row['client_number'].'-cni.pdf',
                        'status' => KycStatus::Verified,
                        'verified_at' => now(),
                    ]
                );
            } else {
                KycDocument::query()
                    ->where('client_id', $client->id)
                    ->delete();
            }

            if ($row['account_number']) {
                $this->seedActiveSavingsAccount($client, $row);
            } else {
                FinancialAccount::query()
                    ->where('client_id', $client->id)
                    ->delete();

                SavingsHistory::query()
                    ->where('client_id', $client->id)
                    ->delete();
            }
        }
    }

    /**
     * @param  array{
     *     agency_code: string,
     *     account_number: string,
     *     balance: float|int,
     * }  $row
     */
    protected function seedActiveSavingsAccount(Client $client, array $row): void
    {
        $caisse = Caisse::query()->where('code', $row['agency_code'])->first();
        $guichet = $caisse
            ? Guichet::query()->where('caisse_id', $caisse->id)->where('code', 'G01')->first()
            : null;
        $cashDesk = $guichet
            ? CashDesk::query()->where('guichet_id', $guichet->id)->where('code', 'C01')->first()
            : null;

        $balance = (float) $row['balance'];

        $account = FinancialAccount::query()->updateOrCreate(
            ['account_number' => $row['account_number']],
            [
                'client_id' => $client->id,
                'caisse_id' => $caisse?->id,
                'guichet_id' => $guichet?->id,
                'cash_desk_id' => $cashDesk?->id,
                'account_type' => 'EPARGNE',
                'balance' => $balance,
                'available_balance' => $balance,
                'blocked_balance' => 0,
                'agency_code' => $row['agency_code'],
                'opened_at' => now()->subYear()->toDateString(),
                'status' => 'ACTIF',
            ]
        );

        SavingsHistory::query()->updateOrCreate(
            [
                'client_id' => $client->id,
                'account_id' => $account->id,
            ],
            [
                'period_start' => now()->subMonths(6)->toDateString(),
                'period_end' => now()->toDateString(),
                'total_deposits' => $balance * 1.5,
                'total_withdrawals' => $balance * 0.5,
                'deposit_count' => 10,
                'withdrawal_count' => 4,
                'average_balance' => $balance * 0.9,
                'closing_balance' => $balance,
            ]
        );

        AccountTransaction::query()->updateOrCreate(
            [
                'account_id' => $account->id,
                'reference' => 'DEMO-DEP-'.$row['account_number'],
            ],
            [
                'transaction_type' => 'DEPOSIT',
                'type' => 'CREDIT',
                'direction' => 'IN',
                'amount' => 75000,
                'transaction_date' => now()->subMonths(2),
                'booked_at' => now()->subMonths(2),
                'label' => 'Versement épargne',
                'description' => 'Versement épargne atelier',
                'status' => 'COMPLETED',
                'channel' => 'AGENCY',
                'balance_after' => $balance,
            ]
        );

        AccountTransaction::query()->updateOrCreate(
            [
                'account_id' => $account->id,
                'reference' => 'DEMO-DEP2-'.$row['account_number'],
            ],
            [
                'transaction_type' => 'DEPOSIT',
                'type' => 'CREDIT',
                'direction' => 'IN',
                'amount' => 50000,
                'transaction_date' => now()->subMonth(),
                'booked_at' => now()->subMonth(),
                'label' => 'Versement épargne',
                'description' => 'Versement épargne atelier',
                'status' => 'COMPLETED',
                'channel' => 'AGENCY',
                'balance_after' => $balance,
            ]
        );
    }
}
