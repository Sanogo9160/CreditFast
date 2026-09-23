<?php

namespace App\Http\Controllers\Api;

use App\Enums\BankAccountApplicationStatus;
use App\Enums\ClientType;
use App\Enums\CreditRequestStatus;
use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\BankAccount\StoreLegalEntityBankAccountApplicationRequest;
use App\Http\Requests\BankAccount\StorePhysicalPersonBankAccountApplicationRequest;
use App\Http\Requests\Profile\StoreActivityRequest;
use App\Http\Requests\Profile\StoreFinancialProfileRequest;
use App\Http\Requests\Profile\StoreKycDocumentRequest;
use App\Http\Requests\Profile\UpdateActivityRequest;
use App\Http\Requests\Profile\UpdateClientProfileRequest;
use App\Http\Resources\BankAccountApplicationResource;
use App\Http\Resources\ClientResource;
use App\Models\Activity;
use App\Models\FinancialAccount;
use App\Models\FinancialProfile;
use App\Models\KycDocument;
use App\Services\AccountCheckService;
use App\Services\BankAccountApplicationService;
use App\Services\FinancialCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ClientProfileController extends Controller
{
    #[OA\Get(
        path: '/api/profile',
        operationId: 'clientProfileShow',
        tags: ['Profil client'],
        summary: '[Lire] Consulter son profil client',
        description: '**Rôles :** Client (`client`).',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profil',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'client', ref: '#/components/schemas/Client'),
                ])
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $client = $request->user()->client()->firstOrFail();
        $client->load(['user', 'kycDocuments', 'financialProfile', 'activities', 'financialAccounts', 'savingsHistories']);

        return response()->json([
            'client' => new ClientResource($client),
        ]);
    }

    #[OA\Get(
        path: '/api/profile/account-check',
        operationId: 'clientProfileAccountCheck',
        tags: ['Profil client'],
        summary: '[Lire] Contrôle du compte (revenus, dépenses, crédits en cours)',
        description: '**Rôles :** Client. Appelé à l’ouverture de la demande de prêt. Les montants viennent du profil financier, pas du formulaire.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contrôle',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'monthly_income', type: 'number', example: 450000),
                    new OA\Property(property: 'monthly_expenses', type: 'number', example: 150000),
                    new OA\Property(property: 'ongoing_credit_count', type: 'integer', example: 0),
                ])
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function accountCheck(Request $request, AccountCheckService $accountCheck): JsonResponse
    {
        $client = $request->user()->client()->firstOrFail();

        return response()->json($accountCheck->forClient($client));
    }

    #[OA\Get(
        path: '/api/profile/financial-accounts/{financialAccount}/transactions',
        operationId: 'clientProfileAccountTransactions',
        tags: ['Profil client'],
        summary: '[Lister] Historique du compte épargne',
        description: '**Rôles :** Client propriétaire du compte uniquement.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/FinancialAccountId')],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée des opérations'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function accountTransactions(Request $request, FinancialAccount $financialAccount): JsonResponse
    {
        $client = $request->user()->client()->firstOrFail();
        abort_unless((int) $financialAccount->client_id === (int) $client->id, 403);

        $transactions = $financialAccount->transactions()
            ->orderByDesc('booked_at')
            ->orderByDesc('id')
            ->paginate(30);

        return response()->json([
            'data' => $transactions->getCollection()->map(fn ($tx) => [
                'id' => $tx->id,
                'reference' => $tx->reference,
                'booked_at' => ($tx->booked_at ?? $tx->transaction_date)?->toIso8601String(),
                'label' => $tx->label ?? $tx->description,
                'type' => $tx->type ?? $tx->transaction_type,
                'direction' => $tx->direction,
                'amount' => abs((float) $tx->amount),
                'status' => $tx->status,
                'channel' => $tx->channel,
                'balance_after' => $tx->balance_after !== null ? (float) $tx->balance_after : null,
            ]),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/profile/savings-onboarding',
        operationId: 'clientProfileSavingsOnboarding',
        tags: ['Profil client'],
        summary: '[Lire] État de la pré-demande d’épargne',
        description: '**Rôles :** Client. Indique si un compte épargne actif existe et si une pré-demande est en cours.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'État d’adhésion épargne'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function savingsOnboarding(Request $request): JsonResponse
    {
        $client = $request->user()->client()->firstOrFail();
        $activeSavings = $client->financialAccounts()
            ->whereIn('account_type', ['EPARGNE', 'SAVINGS'])
            ->whereIn('status', ['ACTIF', 'ACTIVE'])
            ->exists();

        $pending = $client->bankAccountApplications()
            ->whereNotIn('status', [
                BankAccountApplicationStatus::Approved->value,
                BankAccountApplicationStatus::Rejected->value,
            ])
            ->latest()
            ->first();

        return response()->json([
            'has_active_savings_account' => $activeSavings,
            'pending_application' => $pending ? new BankAccountApplicationResource($pending) : null,
            'can_create_pre_application' => ! $activeSavings && $pending === null,
        ]);
    }

    #[OA\Post(
        path: '/api/profile/savings-pre-applications',
        operationId: 'clientProfileStoreSavingsPreApplication',
        tags: ['Profil client'],
        summary: '[Créer] Pré-demande d’épargne',
        description: '**Rôles :** Client. Ne crée pas un compte. Une demande en cours ne se duplique pas. Corps = fiche PP ou PM selon le profil.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Pré-demande enregistrée'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function storeSavingsPreApplication(
        Request $request,
        BankAccountApplicationService $applications
    ): JsonResponse {
        $client = $request->user()->client()->firstOrFail();

        $hasActive = $client->financialAccounts()
            ->whereIn('account_type', ['EPARGNE', 'SAVINGS'])
            ->whereIn('status', ['ACTIF', 'ACTIVE'])
            ->exists();

        if ($hasActive) {
            throw ValidationException::withMessages([
                'application' => 'Vous avez déjà un compte épargne actif.',
            ]);
        }

        $pending = $client->bankAccountApplications()
            ->whereNotIn('status', [
                BankAccountApplicationStatus::Approved->value,
                BankAccountApplicationStatus::Rejected->value,
            ])
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'application' => 'Une demande d’épargne est déjà en cours. Elle ne peut pas être dupliquée.',
            ]);
        }

        $formRequest = $client->client_type === ClientType::LegalEntity
            ? StoreLegalEntityBankAccountApplicationRequest::createFrom($request)
            : StorePhysicalPersonBankAccountApplicationRequest::createFrom($request);

        $formRequest->setContainer(app())->setRedirector(app('redirect'));
        $formRequest->validateResolved();

        $application = $applications->createDraft($client, $formRequest->validated());

        return response()->json([
            'message' => 'Votre pré-demande d’épargne a été enregistrée. Elle ne crée pas encore de compte.',
            'application' => new BankAccountApplicationResource($application),
        ], 201);
    }

    #[OA\Put(
        path: '/api/profile',
        operationId: 'clientProfileUpdate',
        tags: ['Profil client'],
        summary: '[Modifier] Mettre à jour le profil',
        description: '**Rôles :** Client (`client`).',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateClientProfileRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Profil mis à jour'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function updateProfile(UpdateClientProfileRequest $request): JsonResponse
    {
        $client = $request->user()->client()->firstOrFail();
        $client->update($request->validated());

        return response()->json([
            'message' => 'Votre profil a bien été mis à jour.',
            'client' => new ClientResource($client->fresh(['user', 'financialProfile', 'activities', 'kycDocuments'])),
        ]);
    }

    #[OA\Post(
        path: '/api/profile/activities',
        operationId: 'clientActivitiesStore',
        tags: ['Profil client'],
        summary: '[Créer] Enregistrer une activité économique',
        description: '**Rôles :** Client (`client`).',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreActivityRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Activité enregistrée'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function storeActivity(StoreActivityRequest $request): JsonResponse
    {
        $client = $request->user()->client()->firstOrFail();
        $activity = $client->activities()->create($request->validated());

        return response()->json([
            'message' => 'Votre activité économique a bien été enregistrée.',
            'activity' => $activity,
        ], 201);
    }

    #[OA\Post(
        path: '/api/profile/financial-profile',
        operationId: 'clientProfileStoreFinancial',
        tags: ['Profil client'],
        summary: '[Créer / Modifier] Enregistrer le profil financier',
        description: '**Rôles :** Client (`client`).',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreFinancialProfileRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Profil financier mis à jour'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function storeFinancialProfile(StoreFinancialProfileRequest $request, FinancialCalculationService $finService): JsonResponse
    {
        $client = $request->user()->client()->firstOrFail();
        $validated = $request->validated();

        $monthlyIncome = (float) $validated['monthly_income'];
        $otherIncome = (float) ($validated['other_income'] ?? 0);
        $monthlyExpenses = (float) $validated['monthly_expenses'];
        $existingDebt = (float) ($validated['existing_debt_payment'] ?? 0);

        $disposable = $finService->calculateDisposableIncome(
            $monthlyIncome,
            $otherIncome,
            $monthlyExpenses,
            $existingDebt
        );

        $profile = FinancialProfile::updateOrCreate(
            ['client_id' => $client->id],
            array_merge($validated, ['disposable_income' => $disposable])
        );

        return response()->json([
            'message' => 'Votre profil financier a bien été mis à jour.',
            'financial_profile' => $profile,
        ]);
    }

    #[OA\Post(
        path: '/api/profile/kyc-documents',
        operationId: 'clientProfileStoreKyc',
        tags: ['Profil client'],
        summary: '[Créer] Déposer une pièce d’identité',
        description: '**Rôles :** Client (`client`). Corps **multipart/form-data**.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(ref: '#/components/requestBodies/UploadKycDocument'),
        responses: [
            new OA\Response(response: 201, description: 'Pièce reçue'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function storeKycDocument(StoreKycDocumentRequest $request): JsonResponse
    {
        $client = $request->user()->client()->firstOrFail();
        $file = $request->file('file');
        $path = $file->store("kyc_documents/{$client->id}");

        $document = $client->kycDocuments()->create([
            'document_type' => $request->validated('document_type'),
            'document_number' => $request->validated('document_number'),
            'file_path' => $path,
            'status' => KycStatus::Pending,
        ]);

        return response()->json([
            'message' => 'Votre pièce d’identité a bien été reçue. L’équipe procédera à sa vérification.',
            'kyc_document' => $document,
        ], 201);
    }

    #[OA\Get(
        path: '/api/profile/activities',
        operationId: 'clientActivitiesIndex',
        tags: ['Profil client'],
        summary: '[Lister] Les activités économiques',
        description: '**Rôles :** Client (`client`).',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste des activités'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function indexActivities(Request $request): JsonResponse
    {
        $activities = $request->user()->client()->firstOrFail()
            ->activities()
            ->latest()
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $activities]);
    }

    #[OA\Get(
        path: '/api/profile/activities/{activity}',
        operationId: 'clientActivitiesShow',
        tags: ['Profil client'],
        summary: '[Lire] Consulter une activité',
        description: '**Rôles :** Client propriétaire.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/ActivityId')],
        responses: [
            new OA\Response(response: 200, description: 'Activité'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function showActivity(Request $request, Activity $activity): JsonResponse
    {
        $this->assertOwnedActivity($request, $activity);

        return response()->json(['activity' => $activity]);
    }

    #[OA\Put(
        path: '/api/profile/activities/{activity}',
        operationId: 'clientActivitiesUpdate',
        tags: ['Profil client'],
        summary: '[Modifier] Une activité économique',
        description: '**Rôles :** Client propriétaire.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/ActivityId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreActivityRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Activité mise à jour'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function updateActivity(UpdateActivityRequest $request, Activity $activity): JsonResponse
    {
        $activity->update($request->validated());

        return response()->json([
            'message' => 'Votre activité a bien été mise à jour.',
            'activity' => $activity->fresh(),
        ]);
    }

    #[OA\Delete(
        path: '/api/profile/activities/{activity}',
        operationId: 'clientActivitiesDestroy',
        tags: ['Profil client'],
        summary: '[Supprimer] Une activité économique',
        description: '**Rôles :** Client propriétaire. Impossible si l’activité est liée à un dossier déjà transmis.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/ActivityId')],
        responses: [
            new OA\Response(response: 200, description: 'Activité supprimée', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function destroyActivity(Request $request, Activity $activity): JsonResponse
    {
        $this->assertOwnedActivity($request, $activity);

        $linked = $activity->creditRequests()
            ->where('status', '!=', CreditRequestStatus::Draft)
            ->exists();

        if ($linked) {
            return response()->json([
                'message' => 'Cette activité est liée à un dossier déjà transmis. Elle ne peut pas être retirée.',
            ], 422);
        }

        $activity->delete();

        return response()->json([
            'message' => 'L’activité a bien été retirée.',
        ]);
    }

    #[OA\Get(
        path: '/api/profile/financial-profile',
        operationId: 'clientFinancialShow',
        tags: ['Profil client'],
        summary: '[Lire] Consulter le profil financier',
        description: '**Rôles :** Client (`client`).',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Profil financier'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function showFinancialProfile(Request $request): JsonResponse
    {
        $profile = $request->user()->client()->firstOrFail()->financialProfile;

        if (! $profile) {
            return response()->json([
                'message' => 'Aucun profil financier n’est encore renseigné.',
            ], 404);
        }

        return response()->json(['financial_profile' => $profile]);
    }

    #[OA\Put(
        path: '/api/profile/financial-profile',
        operationId: 'clientFinancialUpdate',
        tags: ['Profil client'],
        summary: '[Modifier] Le profil financier',
        description: '**Rôles :** Client (`client`).',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreFinancialProfileRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Profil financier mis à jour'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function updateFinancialProfile(StoreFinancialProfileRequest $request, FinancialCalculationService $finService): JsonResponse
    {
        return $this->storeFinancialProfile($request, $finService);
    }

    #[OA\Get(
        path: '/api/profile/kyc-documents',
        operationId: 'clientKycIndex',
        tags: ['Profil client'],
        summary: '[Lister] Les pièces d’identité',
        description: '**Rôles :** Client (`client`).',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste des pièces'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function indexKycDocuments(Request $request): JsonResponse
    {
        $documents = $request->user()->client()->firstOrFail()
            ->kycDocuments()
            ->latest()
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $documents]);
    }

    #[OA\Get(
        path: '/api/profile/kyc-documents/{kycDocument}',
        operationId: 'clientKycShow',
        tags: ['Profil client'],
        summary: '[Lire] Consulter une pièce d’identité',
        description: '**Rôles :** Client propriétaire.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/KycDocumentId')],
        responses: [
            new OA\Response(response: 200, description: 'Pièce'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function showKycDocument(KycDocument $kycDocument): JsonResponse
    {
        $this->authorize('view', $kycDocument);

        return response()->json(['kyc_document' => $kycDocument]);
    }

    #[OA\Delete(
        path: '/api/profile/kyc-documents/{kycDocument}',
        operationId: 'clientKycDestroy',
        tags: ['Profil client'],
        summary: '[Supprimer] Une pièce d’identité en attente',
        description: '**Rôles :** Client propriétaire. Uniquement si la pièce n’a pas encore été vérifiée.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/KycDocumentId')],
        responses: [
            new OA\Response(response: 200, description: 'Pièce retirée', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function destroyKycDocument(KycDocument $kycDocument): JsonResponse
    {
        $this->authorize('delete', $kycDocument);

        if ($kycDocument->status !== KycStatus::Pending) {
            return response()->json([
                'message' => 'Cette pièce a déjà été examinée. Elle ne peut plus être retirée de cette façon.',
            ], 422);
        }

        if ($kycDocument->file_path) {
            Storage::delete($kycDocument->file_path);
        }

        $kycDocument->delete();

        return response()->json([
            'message' => 'La pièce a bien été retirée.',
        ]);
    }

    private function assertOwnedActivity(Request $request, Activity $activity): void
    {
        abort_unless($activity->client_id === $request->user()->client?->id, 404);
    }
}
