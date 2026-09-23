<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\CancelFieldVisitRequest;
use App\Http\Requests\Agent\CompleteFieldVisitRequest;
use App\Http\Requests\Agent\StoreFieldVisitRequest;
use App\Http\Requests\Agent\UpdateFieldVisitRequest;
use App\Http\Resources\FieldVisitResource;
use App\Models\CreditRequest;
use App\Models\FieldVisit;
use App\Services\FieldVisitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use OpenApi\Attributes as OA;

class FieldVisitController extends Controller
{
    public function __construct(protected FieldVisitService $fieldVisits) {}

    #[OA\Get(
        path: '/api/agent/field-visits',
        operationId: 'agentFieldVisitsIndex',
        tags: ['Visites terrain'],
        summary: '[Lister] Les visites / rendez-vous terrain',
        description: '**Rôles :** Chargé (`credit_agent`), Admin (`admin`). Filtre optionnel `status`, `credit_request_id`.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FieldVisit::class);

        $visits = FieldVisit::query()
            ->with(['agent', 'client.user', 'creditRequest'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('credit_request_id'), fn ($q) => $q->where('credit_request_id', $request->integer('credit_request_id')))
            ->latest('scheduled_at')
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'data' => FieldVisitResource::collection($visits),
            'meta' => [
                'current_page' => $visits->currentPage(),
                'last_page' => $visits->lastPage(),
                'total' => $visits->total(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/agent/requests/{creditRequest}/field-visits',
        operationId: 'agentCreditRequestFieldVisitsIndex',
        tags: ['Visites terrain'],
        summary: '[Lister] Les visites d’un dossier',
        description: '**Rôles :** Chargé, Admin.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        responses: [
            new OA\Response(response: 200, description: 'Liste des visites du dossier'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function indexForRequest(CreditRequest $creditRequest): JsonResponse
    {
        $this->authorize('view', $creditRequest);
        $this->authorize('viewAny', FieldVisit::class);

        $visits = $creditRequest->fieldVisits()
            ->with(['agent'])
            ->latest('scheduled_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'credit_request_id' => $creditRequest->id,
            'data' => FieldVisitResource::collection($visits),
        ]);
    }

    #[OA\Post(
        path: '/api/agent/requests/{creditRequest}/field-visits',
        operationId: 'agentFieldVisitsStore',
        tags: ['Visites terrain'],
        summary: '[Créer] Planifier une visite / un rendez-vous terrain',
        description: '**Rôles :** Chargé, Admin. Dossier en `SUBMITTED`, `VERIFICATION_REQUIRED`, `IN_ANALYSIS` ou `PENDING_COMMITTEE`. Notifie le client.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreFieldVisitRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Visite planifiée'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function store(StoreFieldVisitRequest $request, CreditRequest $creditRequest): JsonResponse
    {
        $this->authorize('view', $creditRequest);

        try {
            $visit = $this->fieldVisits->schedule($creditRequest, $request->user(), $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'La visite terrain a été planifiée. Le client a été notifié.',
            'field_visit' => new FieldVisitResource($visit),
        ], 201);
    }

    #[OA\Get(
        path: '/api/agent/field-visits/{fieldVisit}',
        operationId: 'agentFieldVisitsShow',
        tags: ['Visites terrain'],
        summary: '[Lire] Une visite terrain',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/FieldVisitId')],
        responses: [
            new OA\Response(response: 200, description: 'Détail'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function show(FieldVisit $fieldVisit): JsonResponse
    {
        $this->authorize('view', $fieldVisit);

        $fieldVisit->load(['agent', 'client.user', 'creditRequest']);

        return response()->json([
            'field_visit' => new FieldVisitResource($fieldVisit),
        ]);
    }

    #[OA\Put(
        path: '/api/agent/field-visits/{fieldVisit}',
        operationId: 'agentFieldVisitsUpdate',
        tags: ['Visites terrain'],
        summary: '[Modifier] Une visite planifiée ou en cours',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/FieldVisitId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateFieldVisitRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Visite mise à jour'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function update(UpdateFieldVisitRequest $request, FieldVisit $fieldVisit): JsonResponse
    {
        try {
            $visit = $this->fieldVisits->update($fieldVisit, $request->user(), $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'La visite terrain a été mise à jour.',
            'field_visit' => new FieldVisitResource($visit),
        ]);
    }

    #[OA\Post(
        path: '/api/agent/field-visits/{fieldVisit}/start',
        operationId: 'agentFieldVisitsStart',
        tags: ['Visites terrain'],
        summary: 'Démarrer une visite sur place',
        description: 'Passe le statut de `SCHEDULED` à `IN_PROGRESS`.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/FieldVisitId')],
        responses: [
            new OA\Response(response: 200, description: 'Visite démarrée'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function start(Request $request, FieldVisit $fieldVisit): JsonResponse
    {
        $this->authorize('update', $fieldVisit);

        try {
            $visit = $this->fieldVisits->start($fieldVisit, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'La visite terrain est en cours.',
            'field_visit' => new FieldVisitResource($visit),
        ]);
    }

    #[OA\Post(
        path: '/api/agent/field-visits/{fieldVisit}/complete',
        operationId: 'agentFieldVisitsComplete',
        tags: ['Visites terrain'],
        summary: 'Clôturer la visite avec le rapport terrain',
        description: 'Enregistre constats + conclusion (`FAVORABLE` | `RESERVED` | `UNFAVORABLE`). N’impose pas seul la décision d’octroi.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/FieldVisitId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CompleteFieldVisitRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Rapport enregistré'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function complete(CompleteFieldVisitRequest $request, FieldVisit $fieldVisit): JsonResponse
    {
        try {
            $visit = $this->fieldVisits->complete($fieldVisit, $request->user(), $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Le rapport de visite terrain a été enregistré.',
            'field_visit' => new FieldVisitResource($visit),
        ]);
    }

    #[OA\Post(
        path: '/api/agent/field-visits/{fieldVisit}/cancel',
        operationId: 'agentFieldVisitsCancel',
        tags: ['Visites terrain'],
        summary: 'Annuler ou marquer une absence (no-show)',
        description: '`as_no_show=true` → statut `NO_SHOW`, sinon `CANCELLED`.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/FieldVisitId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CancelFieldVisitRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Visite annulée'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function cancel(CancelFieldVisitRequest $request, FieldVisit $fieldVisit): JsonResponse
    {
        try {
            $visit = $this->fieldVisits->cancel($fieldVisit, $request->user(), $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $visit->status->value === 'NO_SHOW'
                ? 'La visite a été marquée en absence du client.'
                : 'La visite terrain a été annulée.',
            'field_visit' => new FieldVisitResource($visit),
        ]);
    }
}
