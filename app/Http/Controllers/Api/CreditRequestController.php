<?php

namespace App\Http\Controllers\Api;

use App\Enums\CreditRequestStatus;
use App\Enums\GuaranteeVerificationStatus;
use App\Enums\RepaymentCapacityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreditRequest\StoreCreditRequest;
use App\Http\Requests\CreditRequest\StoreGuaranteeRequest;
use App\Http\Requests\CreditRequest\UpdateCreditRequest;
use App\Http\Requests\CreditRequest\UpdateGuaranteeRequest;
use App\Http\Requests\CreditRequest\UploadDocumentRequest;
use App\Http\Resources\CreditRequestResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\GuaranteeResource;
use App\Models\Client;
use App\Models\CreditRequest;
use App\Models\Document;
use App\Models\Guarantee;
use App\Services\AnomalyDetectionService;
use App\Services\CreditWorkflowService;
use App\Services\FinancialCalculationService;
use App\Services\OcrExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class CreditRequestController extends Controller
{
    public function __construct(
        protected FinancialCalculationService $finService,
        protected CreditWorkflowService $workflowService,
        protected OcrExtractionService $ocrService,
        protected AnomalyDetectionService $anomalyService
    ) {}

    #[OA\Get(
        path: '/api/credit-requests',
        operationId: 'creditRequestsIndex',
        tags: ['Demandes de crédit'],
        summary: '[Lister] Les demandes de crédit',
        description: '**Rôles :** Client (ses dossiers) ; staff (toutes).',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CreditRequest::class);

        $user = $request->user();
        $query = CreditRequest::query()
            ->with([
                'client.user',
                'activity',
                'latestAnalysis',
                'anomalies',
                'documents',
            ])
            ->latest()
            ->orderByDesc('id');

        if (! $user->isStaff()) {
            $client = $user->client;
            if (! $client) {
                return response()->json(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]]);
            }
            $query->forClient($client->id);
        }

        $requests = $query->paginate(15);

        return response()->json([
            'data' => CreditRequestResource::collection($requests),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/credit-requests',
        operationId: 'creditRequestsStore',
        tags: ['Demandes de crédit'],
        summary: '[Créer] Une demande de crédit',
        description: '**Rôles :** Client (`client`). La demande est enregistrée puis peut encore être complétée avant soumission. La garantie imbriquée est facultative : si elle est absente ou non renseignée, aucune garantie n’est créée.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreCreditRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Demande enregistrée', content: new OA\JsonContent(ref: '#/components/schemas/CreditRequestEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function store(StoreCreditRequest $request): JsonResponse
    {
        $client = $request->user()->client()->with(['financialProfile', 'activities'])->firstOrFail();
        $validated = $request->validated();

        $requestedAmount = (float) $validated['requested_amount'];
        $durationMonths = (int) $validated['duration_months'];
        $declaredIncome = (float) $validated['declared_monthly_income'];
        $declaredExpenses = (float) $validated['declared_monthly_expenses'];

        $creditRequest = CreditRequest::create([
            'client_id' => $client->id,
            'activity_id' => $validated['activity_id'] ?? $client->activities->first()?->id,
            'requested_amount' => $requestedAmount,
            'duration_months' => $durationMonths,
            'purpose' => $validated['purpose'],
            ...$this->snapshotCapacity($client, $requestedAmount, $durationMonths, $declaredIncome, $declaredExpenses),
            'status' => CreditRequestStatus::Draft,
        ]);

        $guarantee = $validated['guarantee'] ?? null;

        if (is_array($guarantee) && filled($guarantee['guarantee_type'] ?? null)) {
            $creditRequest->guarantees()->create([
                'guarantee_type' => $guarantee['guarantee_type'],
                'description' => $guarantee['description'] ?? null,
                'declared_value' => $guarantee['declared_value'],
                'verification_status' => GuaranteeVerificationStatus::Pending,
            ]);
        }

        return response()->json([
            'message' => 'Votre demande de crédit a bien été enregistrée. Vous pouvez encore la compléter avant de la transmettre à l’équipe.',
            'credit_request' => new CreditRequestResource($creditRequest->load(['client', 'activity', 'guarantees'])),
        ], 201);
    }

    #[OA\Put(
        path: '/api/credit-requests/{creditRequest}',
        operationId: 'creditRequestsUpdate',
        tags: ['Demandes de crédit'],
        summary: '[Modifier] Une demande (brouillon uniquement)',
        description: '**Rôles :** Client propriétaire (ou chargé/admin). Uniquement si le statut est DRAFT.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateCreditRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Demande mise à jour', content: new OA\JsonContent(ref: '#/components/schemas/CreditRequestEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function update(UpdateCreditRequest $request, CreditRequest $creditRequest): JsonResponse
    {
        $client = $creditRequest->client()->with('financialProfile')->firstOrFail();
        $validated = $request->validated();

        $requestedAmount = (float) ($validated['requested_amount'] ?? $creditRequest->requested_amount);
        $durationMonths = (int) ($validated['duration_months'] ?? $creditRequest->duration_months);
        $declaredIncome = (float) ($validated['declared_monthly_income'] ?? $creditRequest->declared_monthly_income);
        $declaredExpenses = (float) ($validated['declared_monthly_expenses'] ?? $creditRequest->declared_monthly_expenses);

        $creditRequest->update([
            ...$validated,
            'requested_amount' => $requestedAmount,
            'duration_months' => $durationMonths,
            'declared_monthly_income' => $declaredIncome,
            'declared_monthly_expenses' => $declaredExpenses,
            ...$this->snapshotCapacity($client, $requestedAmount, $durationMonths, $declaredIncome, $declaredExpenses),
        ]);

        return response()->json([
            'message' => 'Votre demande a été mise à jour.',
            'credit_request' => new CreditRequestResource($creditRequest->fresh(['client', 'activity', 'guarantees'])),
        ]);
    }

    #[OA\Post(
        path: '/api/credit-requests/{creditRequest}/guarantees',
        operationId: 'creditRequestsStoreGuarantee',
        tags: ['Demandes de crédit'],
        summary: '[Créer] Ajouter une garantie',
        description: '**Rôles :** Client propriétaire (DRAFT ou VERIFICATION_REQUIRED). JSON ou **multipart/form-data**. Champ `file` facultatif (PDF, JPG, PNG, 10 Mo max) en plus du type, de la description et de la valeur.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        requestBody: new OA\RequestBody(ref: '#/components/requestBodies/StoreGuarantee'),
        responses: [
            new OA\Response(response: 201, description: 'Garantie enregistrée, en attente de validation par l’équipe'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function storeGuarantee(StoreGuaranteeRequest $request, CreditRequest $creditRequest): JsonResponse
    {
        $guarantee = $creditRequest->guarantees()->create([
            ...$request->safe()->except('file'),
            'verification_status' => GuaranteeVerificationStatus::Pending,
        ]);

        $this->storeGuaranteeFile($guarantee, $request->file('file'));

        return response()->json([
            'message' => 'Garantie enregistrée avec succès. En attente de validation finale par l’équipe.',
            'guarantee' => new GuaranteeResource($guarantee->fresh()),
        ], 201);
    }

    #[OA\Get(
        path: '/api/credit-requests/{creditRequest}',
        operationId: 'creditRequestsShow',
        tags: ['Demandes de crédit'],
        summary: '[Lire] Consulter une demande',
        description: '**Rôles :** Client propriétaire ou staff.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        responses: [
            new OA\Response(response: 200, description: 'Dossier'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function show(Request $request, CreditRequest $creditRequest): JsonResponse
    {
        $this->authorize('view', $creditRequest);

        $creditRequest->load([
            'client.user',
            'client.financialProfile',
            'client.activities',
            'activity',
            'guarantees',
            'documents.extraction',
            'anomalies',
            'creditAnalyses.factors',
            'latestAnalysis.factors',
            'humanValidations.validator',
            'creditReviews.analyst',
            'committeeDecisions.committeeMember',
            'statusHistory.changer',
            'loan.repayments',
        ]);

        return response()->json([
            'credit_request' => new CreditRequestResource($creditRequest),
        ]);
    }

    #[OA\Post(
        path: '/api/credit-requests/{creditRequest}/submit',
        operationId: 'creditRequestsSubmit',
        tags: ['Demandes de crédit'],
        summary: 'Transmettre la demande à l’équipe',
        description: '**Rôles :** Client propriétaire, Chargé ou Admin.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        responses: [
            new OA\Response(response: 200, description: 'Demande transmise', content: new OA\JsonContent(ref: '#/components/schemas/CreditRequestEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, description: 'Transition de statut impossible'),
        ]
    )]
    public function submit(Request $request, CreditRequest $creditRequest): JsonResponse
    {
        $this->authorize('submit', $creditRequest);

        $updated = $this->workflowService->transitionStatus(
            $creditRequest,
            CreditRequestStatus::Submitted,
            $request->user(),
            'Soumission de la demande par le demandeur'
        );

        $this->anomalyService->detectAnomalies($updated);

        return response()->json([
            'message' => 'Votre demande a bien été transmise. Notre équipe va l’examiner avec attention.',
            'credit_request' => new CreditRequestResource($updated->fresh(['anomalies'])),
        ]);
    }

    #[OA\Post(
        path: '/api/credit-requests/{creditRequest}/documents',
        operationId: 'creditRequestsUploadDocument',
        tags: ['Demandes de crédit'],
        summary: '[Créer] Joindre une pièce au dossier',
        description: '**Rôles :** Client propriétaire, Chargé, Admin ou Analyste. Corps **multipart/form-data**.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        requestBody: new OA\RequestBody(ref: '#/components/requestBodies/UploadCreditDocument'),
        responses: [
            new OA\Response(response: 201, description: 'Document reçu (OCR automatique, contrôle humain ensuite)'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function uploadDocument(UploadDocumentRequest $request, CreditRequest $creditRequest): JsonResponse
    {
        $user = $request->user();
        $file = $request->file('file');
        $filePath = $file->store("credit_documents/{$creditRequest->id}");

        $document = $creditRequest->documents()->create([
            'document_type' => $request->validated('document_type'),
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'mime_type' => $file->getClientMimeType(),
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
            'status' => 'UPLOADED',
        ]);

        $this->ocrService->processDocumentExtraction($document);
        $this->anomalyService->detectAnomalies($creditRequest);

        return response()->json([
            'message' => 'Document reçu. Un premier examen automatique a été réalisé ; l’équipe confirmera ensuite les informations.',
            'document' => new DocumentResource($document->load('extraction')),
        ], 201);
    }

    #[OA\Delete(
        path: '/api/credit-requests/{creditRequest}',
        operationId: 'creditRequestsDestroy',
        tags: ['Demandes de crédit'],
        summary: '[Supprimer] Une demande encore en brouillon',
        description: '**Rôles :** Client propriétaire, Chargé ou Admin. Uniquement si le statut est DRAFT.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        responses: [
            new OA\Response(response: 200, description: 'Demande retirée', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function destroy(CreditRequest $creditRequest): JsonResponse
    {
        $this->authorize('delete', $creditRequest);

        if ($creditRequest->status !== CreditRequestStatus::Draft) {
            return response()->json([
                'message' => 'Seule une demande encore en préparation peut être retirée.',
            ], 422);
        }

        $creditRequest->load(['documents', 'guarantees']);

        foreach ($creditRequest->documents as $document) {
            if ($document->file_path) {
                Storage::delete($document->file_path);
            }
        }

        foreach ($creditRequest->guarantees as $guarantee) {
            $this->deleteGuaranteeFile($guarantee);
        }

        $creditRequest->delete();

        return response()->json([
            'message' => 'La demande a bien été retirée.',
        ]);
    }

    #[OA\Get(
        path: '/api/credit-requests/{creditRequest}/documents',
        operationId: 'creditDocumentsIndex',
        tags: ['Demandes de crédit'],
        summary: '[Lister] Les pièces du dossier',
        description: '**Rôles :** Client propriétaire ou staff.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        responses: [
            new OA\Response(response: 200, description: 'Liste des pièces'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function indexDocuments(CreditRequest $creditRequest): JsonResponse
    {
        $this->authorize('view', $creditRequest);

        $documents = $creditRequest->documents()->with('extraction')->latest()->orderByDesc('id')->get();

        return response()->json([
            'data' => DocumentResource::collection($documents),
        ]);
    }

    #[OA\Get(
        path: '/api/credit-requests/{creditRequest}/documents/{document}',
        operationId: 'creditDocumentsShow',
        tags: ['Demandes de crédit'],
        summary: '[Lire] Consulter une pièce du dossier',
        description: '**Rôles :** Client propriétaire ou staff.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/CreditRequestId'),
            new OA\Parameter(ref: '#/components/parameters/DocumentId'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pièce'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function showDocument(CreditRequest $creditRequest, Document $document): JsonResponse
    {
        $this->authorize('view', $document);
        abort_unless($document->credit_request_id === $creditRequest->id, 404);

        return response()->json([
            'document' => new DocumentResource($document->load('extraction')),
        ]);
    }

    #[OA\Delete(
        path: '/api/credit-requests/{creditRequest}/documents/{document}',
        operationId: 'creditDocumentsDestroy',
        tags: ['Demandes de crédit'],
        summary: '[Supprimer] Une pièce du dossier',
        description: '**Rôles :** Client propriétaire, Chargé, Admin ou Analyste. Dossier en DRAFT ou VERIFICATION_REQUIRED uniquement.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/CreditRequestId'),
            new OA\Parameter(ref: '#/components/parameters/DocumentId'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pièce retirée', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function destroyDocument(CreditRequest $creditRequest, Document $document): JsonResponse
    {
        $this->authorize('delete', $document);
        abort_unless($document->credit_request_id === $creditRequest->id, 404);

        if (! in_array($creditRequest->status, [CreditRequestStatus::Draft, CreditRequestStatus::VerificationRequired], true)) {
            return response()->json([
                'message' => 'Les pièces ne peuvent être retirées que tant que le dossier est en préparation ou renvoyé pour complément.',
            ], 422);
        }

        if ($document->file_path) {
            Storage::delete($document->file_path);
        }

        $document->delete();

        return response()->json([
            'message' => 'La pièce a bien été retirée du dossier.',
        ]);
    }

    #[OA\Get(
        path: '/api/credit-requests/{creditRequest}/guarantees',
        operationId: 'creditGuaranteesIndex',
        tags: ['Demandes de crédit'],
        summary: '[Lister] Les garanties du dossier',
        description: '**Rôles :** Client propriétaire ou staff.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        responses: [
            new OA\Response(response: 200, description: 'Liste des garanties'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function indexGuarantees(CreditRequest $creditRequest): JsonResponse
    {
        $this->authorize('view', $creditRequest);

        return response()->json([
            'data' => GuaranteeResource::collection(
                $creditRequest->guarantees()->latest()->orderByDesc('id')->get()
            ),
        ]);
    }

    #[OA\Get(
        path: '/api/credit-requests/{creditRequest}/guarantees/{guarantee}',
        operationId: 'creditGuaranteesShow',
        tags: ['Demandes de crédit'],
        summary: '[Lire] Consulter une garantie',
        description: '**Rôles :** Client propriétaire ou staff.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/CreditRequestId'),
            new OA\Parameter(ref: '#/components/parameters/GuaranteeId'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Garantie'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function showGuarantee(CreditRequest $creditRequest, Guarantee $guarantee): JsonResponse
    {
        $this->authorize('view', $guarantee);
        abort_unless($guarantee->credit_request_id === $creditRequest->id, 404);

        return response()->json(['guarantee' => new GuaranteeResource($guarantee)]);
    }

    #[OA\Put(
        path: '/api/credit-requests/{creditRequest}/guarantees/{guarantee}',
        operationId: 'creditGuaranteesUpdate',
        tags: ['Demandes de crédit'],
        summary: '[Modifier] Une garantie en attente',
        description: '**Rôles :** Client propriétaire. Uniquement si la garantie n’a pas encore été examinée. JSON ou **multipart/form-data**. Un nouveau `file` remplace le fichier existant.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/CreditRequestId'),
            new OA\Parameter(ref: '#/components/parameters/GuaranteeId'),
        ],
        requestBody: new OA\RequestBody(ref: '#/components/requestBodies/UpdateGuarantee'),
        responses: [
            new OA\Response(response: 200, description: 'Garantie mise à jour'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function updateGuarantee(UpdateGuaranteeRequest $request, CreditRequest $creditRequest, Guarantee $guarantee): JsonResponse
    {
        abort_unless($guarantee->credit_request_id === $creditRequest->id, 404);

        $guarantee->update($request->safe()->except('file'));
        $this->storeGuaranteeFile($guarantee, $request->file('file'));

        return response()->json([
            'message' => 'La garantie a bien été mise à jour.',
            'guarantee' => new GuaranteeResource($guarantee->fresh()),
        ]);
    }

    #[OA\Delete(
        path: '/api/credit-requests/{creditRequest}/guarantees/{guarantee}',
        operationId: 'creditGuaranteesDestroy',
        tags: ['Demandes de crédit'],
        summary: '[Supprimer] Une garantie en attente',
        description: '**Rôles :** Client propriétaire. Uniquement si la garantie n’a pas encore été examinée.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/CreditRequestId'),
            new OA\Parameter(ref: '#/components/parameters/GuaranteeId'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Garantie retirée', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function destroyGuarantee(CreditRequest $creditRequest, Guarantee $guarantee): JsonResponse
    {
        $this->authorize('delete', $guarantee);
        abort_unless($guarantee->credit_request_id === $creditRequest->id, 404);

        if ($guarantee->verification_status !== GuaranteeVerificationStatus::Pending) {
            return response()->json([
                'message' => 'Cette garantie a déjà été examinée. Elle ne peut plus être retirée de cette façon.',
            ], 422);
        }

        if (! in_array($creditRequest->status, [CreditRequestStatus::Draft, CreditRequestStatus::VerificationRequired], true)) {
            return response()->json([
                'message' => 'Les garanties ne peuvent être retirées que tant que le dossier est en préparation ou renvoyé pour complément.',
            ], 422);
        }

        $this->deleteGuaranteeFile($guarantee);
        $guarantee->delete();

        return response()->json([
            'message' => 'La garantie a bien été retirée.',
        ]);
    }

    protected function storeGuaranteeFile(Guarantee $guarantee, ?UploadedFile $file): void
    {
        if ($file === null) {
            return;
        }

        $this->deleteGuaranteeFile($guarantee);

        $path = $file->store("guarantee_documents/{$guarantee->credit_request_id}");

        $guarantee->update([
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
        ]);
    }

    protected function deleteGuaranteeFile(Guarantee $guarantee): void
    {
        if (filled($guarantee->file_path) && Storage::exists($guarantee->file_path)) {
            Storage::delete($guarantee->file_path);
        }
    }

    /**
     * @return array{
     *     declared_monthly_income: float,
     *     declared_monthly_expenses: float,
     *     estimated_monthly_payment: float,
     *     disposable_income: float,
     *     repayment_capacity_status: RepaymentCapacityStatus
     * }
     */
    protected function snapshotCapacity(
        Client $client,
        float $requestedAmount,
        int $durationMonths,
        float $declaredIncome,
        float $declaredExpenses
    ): array {
        $profile = $client->financialProfile;
        $estimatedMonthlyPayment = $this->finService->calculateEstimatedMonthlyPayment($requestedAmount, $durationMonths);
        $disposableIncome = $this->finService->calculateDisposableIncome(
            $declaredIncome,
            (float) ($profile?->other_income ?? 0),
            $declaredExpenses,
            (float) ($profile?->existing_debt_payment ?? 0)
        );

        return [
            'declared_monthly_income' => $declaredIncome,
            'declared_monthly_expenses' => $declaredExpenses,
            'estimated_monthly_payment' => $estimatedMonthlyPayment,
            'disposable_income' => $disposableIncome,
            'repayment_capacity_status' => $this->finService->evaluateRepaymentCapacity($disposableIncome, $estimatedMonthlyPayment),
        ];
    }
}
