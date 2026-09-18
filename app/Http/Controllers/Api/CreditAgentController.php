<?php

namespace App\Http\Controllers\Api;

use App\Enums\CreditRequestStatus;
use App\Enums\GuaranteeVerificationStatus;
use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\RequestComplementsRequest;
use App\Http\Requests\Institutional\StoreAccountTransactionRequest;
use App\Http\Requests\Institutional\StoreFinancialAccountRequest;
use App\Http\Requests\Institutional\StoreSavingsHistoryRequest;
use App\Http\Requests\Verification\VerifyGuaranteeRequest;
use App\Http\Requests\Verification\VerifyKycDocumentRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\CreditRequestResource;
use App\Models\Client;
use App\Models\CreditRequest;
use App\Models\FinancialAccount;
use App\Models\Guarantee;
use App\Models\KycDocument;
use App\Services\CreditWorkflowService;
use App\Services\InstitutionalHistoryService;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CreditAgentController extends Controller
{
    public function __construct(
        protected CreditWorkflowService $workflowService,
        protected VerificationService $verificationService,
        protected InstitutionalHistoryService $historyService,
    ) {}

    #[OA\Get(
        path: '/api/agent/requests',
        operationId: 'agentRequestsIndex',
        tags: ['Chargé de crédit'],
        summary: '[Lister] La file des dossiers',
        description: '**Rôles :** Chargé (`credit_agent`), Admin (`admin`).',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', CreditRequest::class);

        $requests = CreditRequest::query()
            ->with(['client.user', 'activity', 'guarantees', 'anomalies', 'documents', 'latestAnalysis'])
            ->whereIn('status', [
                CreditRequestStatus::Submitted,
                CreditRequestStatus::VerificationRequired,
                CreditRequestStatus::Analysis,
            ])
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'data' => CreditRequestResource::collection($requests),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Endpoint 8.4 — le chargé de crédit ou l’admin renvoie officiellement le dossier
     * au client (statut VERIFICATION_REQUIRED) pour pièces ou informations manquantes
     * (ex. justificatif de domicile).
     */
    #[OA\Post(
        path: '/api/agent/requests/{creditRequest}/request-complements',
        operationId: 'agentRequestComplements',
        tags: ['Chargé de crédit'],
        summary: 'Renvoyer le dossier au client (compléments)',
        description: '**Rôles :** Chargé (`credit_agent`), Admin (`admin`). Endpoint 8.4 : le dossier est officiellement renvoyé au **client** (statut VERIFICATION_REQUIRED) pour pièces manquantes (ex. justificatif de domicile).',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RequestComplementsRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Dossier renvoyé au client', content: new OA\JsonContent(ref: '#/components/schemas/CreditRequestEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function requestComplements(RequestComplementsRequest $request, CreditRequest $creditRequest): JsonResponse
    {
        $updated = $this->workflowService->transitionStatus(
            $creditRequest,
            CreditRequestStatus::VerificationRequired,
            $request->user(),
            $request->validated('comment')
        );

        return response()->json([
            'message' => 'Le dossier a été renvoyé au client afin qu’il puisse transmettre les pièces ou informations manquantes (par exemple un justificatif de domicile).',
            'returned_to_client' => true,
            'next_actor' => 'client',
            'credit_request' => new CreditRequestResource($updated->fresh(['statusHistory', 'anomalies'])),
        ]);
    }

    #[OA\Post(
        path: '/api/agent/requests/{creditRequest}/send-to-analysis',
        operationId: 'agentSendToAnalysis',
        tags: ['Chargé de crédit'],
        summary: 'Transmettre le dossier à l’analyse',
        description: '**Rôles :** Chargé (`credit_agent`), Admin (`admin`).',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        responses: [
            new OA\Response(response: 200, description: 'Dossier transmis'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, description: 'Transition de statut impossible'),
        ]
    )]
    public function sendToAnalysis(Request $request, CreditRequest $creditRequest): JsonResponse
    {
        $this->authorize('sendToAnalysis', $creditRequest);

        $updated = $this->workflowService->transitionStatus(
            $creditRequest,
            CreditRequestStatus::Analysis,
            $request->user(),
            'Premières vérifications effectuées — dossier transmis à l’analyse'
        );

        return response()->json([
            'message' => 'Dossier transmis à l’équipe d’analyse. Merci pour cette première relecture.',
            'credit_request' => new CreditRequestResource($updated->fresh(['statusHistory'])),
        ]);
    }

    #[OA\Post(
        path: '/api/agent/clients/{client}/kyc-documents/{kycDocument}/verify',
        operationId: 'agentVerifyKyc',
        tags: ['Chargé de crédit'],
        summary: 'Vérifier une pièce d’identité',
        description: '**Rôles :** Chargé (`credit_agent`), Analyste (`analyst`), Admin (`admin`). L’analyste appelle le même contrat via `/api/analyst/clients/{client}/kyc-documents/{kycDocument}/verify`.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/ClientId'),
            new OA\Parameter(ref: '#/components/parameters/KycDocumentId'),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/VerifyKycRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Vérification enregistrée'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function verifyKycDocument(VerifyKycDocumentRequest $request, Client $client, KycDocument $kycDocument): JsonResponse
    {
        abort_unless($kycDocument->client_id === $client->id, 404);

        $document = $this->verificationService->verifyKycDocument(
            $kycDocument,
            $request->user(),
            KycStatus::from($request->validated('decision')),
            $request->validated('rejection_reason')
        );

        return response()->json([
            'message' => 'La vérification d’identité a bien été enregistrée.',
            'kyc_document' => $document,
            'client' => new ClientResource($document->client->load(['kycDocuments', 'user'])),
        ]);
    }

    #[OA\Post(
        path: '/api/agent/guarantees/{guarantee}/verify',
        operationId: 'agentVerifyGuarantee',
        tags: ['Chargé de crédit'],
        summary: 'Examiner une garantie',
        description: '**Rôles :** Chargé (`credit_agent`), Analyste (`analyst`), Admin (`admin`). L’analyste appelle le même contrat via `/api/analyst/guarantees/{guarantee}/verify`.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/GuaranteeId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/VerifyGuaranteeRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Garantie examinée'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function verifyGuarantee(VerifyGuaranteeRequest $request, Guarantee $guarantee): JsonResponse
    {
        $validated = $request->validated();
        $status = GuaranteeVerificationStatus::from($validated['verification_status']);

        $updated = $this->verificationService->verifyGuarantee(
            $guarantee,
            $request->user(),
            $status,
            isset($validated['verified_value']) ? (float) $validated['verified_value'] : null
        );

        return response()->json([
            'message' => 'La garantie a bien été examinée et enregistrée.',
            'guarantee' => $updated,
        ]);
    }

    #[OA\Post(
        path: '/api/agent/clients/{client}/financial-accounts',
        operationId: 'agentStoreFinancialAccount',
        tags: ['Chargé de crédit'],
        summary: 'Saisir un compte institutionnel',
        description: '**Rôles :** Chargé (`credit_agent`), Admin (`admin`). Non saisi par le client.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/ClientId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreFinancialAccountRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Compte enregistré'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function storeFinancialAccount(StoreFinancialAccountRequest $request, Client $client): JsonResponse
    {
        $account = $this->historyService->createAccount($client, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Compte enregistré. Cette information provient de l’institution et n’est pas saisie par le client.',
            'financial_account' => $account,
        ], 201);
    }

    #[OA\Post(
        path: '/api/agent/financial-accounts/{financialAccount}/transactions',
        operationId: 'agentStoreAccountTransaction',
        tags: ['Chargé de crédit'],
        summary: 'Enregistrer un mouvement de compte',
        description: '**Rôles :** Chargé (`credit_agent`), Admin (`admin`).',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/FinancialAccountId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreAccountTransactionRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Mouvement enregistré'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function storeAccountTransaction(StoreAccountTransactionRequest $request, FinancialAccount $financialAccount): JsonResponse
    {
        $transaction = $this->historyService->recordTransaction(
            $financialAccount,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Le mouvement de compte a bien été enregistré.',
            'transaction' => $transaction,
            'financial_account' => $financialAccount->fresh(),
        ], 201);
    }

    #[OA\Post(
        path: '/api/agent/clients/{client}/savings-history',
        operationId: 'agentStoreSavingsHistory',
        tags: ['Chargé de crédit'],
        summary: 'Saisir une synthèse d’épargne',
        description: '**Rôles :** Chargé (`credit_agent`), Admin (`admin`). Fait basculer le scoring vers le mode STANDARD.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/ClientId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreSavingsHistoryRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Épargne enregistrée'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function storeSavingsHistory(StoreSavingsHistoryRequest $request, Client $client): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['account_id'])) {
            $belongs = $client->financialAccounts()->whereKey($validated['account_id'])->exists();
            abort_unless($belongs, 422, 'Le compte n’appartient pas à ce client.');
        }

        $history = $this->historyService->recordSavingsHistory($client, $validated, $request->user());

        return response()->json([
            'message' => 'La synthèse d’épargne a bien été enregistrée.',
            'savings_history' => $history,
        ], 201);
    }

    #[OA\Get(
        path: '/api/agent/clients',
        operationId: 'agentClientsIndex',
        tags: ['Chargé de crédit'],
        summary: '[Lister] Les fiches clients',
        description: '**Rôles :** Chargé (`credit_agent`), Admin (`admin`).',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function indexClients(): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $clients = Client::query()
            ->with(['user', 'financialProfile', 'kycDocuments'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'data' => ClientResource::collection($clients),
            'meta' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'total' => $clients->total(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/agent/clients/{client}',
        operationId: 'agentClientsShow',
        tags: ['Chargé de crédit'],
        summary: '[Lire] Consulter une fiche client',
        description: '**Rôles :** Chargé (`credit_agent`), Admin (`admin`).',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/ClientId')],
        responses: [
            new OA\Response(response: 200, description: 'Fiche client'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function showClient(Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $client->load([
            'user',
            'kycDocuments',
            'financialProfile',
            'activities',
            'financialAccounts',
            'savingsHistories',
        ]);

        return response()->json([
            'client' => new ClientResource($client),
        ]);
    }
}
