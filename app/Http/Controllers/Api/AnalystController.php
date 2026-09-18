<?php

namespace App\Http\Controllers\Api;

use App\Enums\CreditRequestStatus;
use App\Enums\ValidationDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Analyst\ResolveAnomalyRequest;
use App\Http\Requests\Analyst\ReviewCreditRequest;
use App\Http\Requests\Analyst\StoreHumanValidationRequest;
use App\Http\Resources\AnomalyResource;
use App\Http\Resources\CreditRequestResource;
use App\Models\Anomaly;
use App\Models\CreditRequest;
use App\Models\CreditReview;
use App\Models\HumanValidation;
use App\Services\CreditWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AnalystController extends Controller
{
    public function __construct(protected CreditWorkflowService $workflowService) {}

    #[OA\Get(
        path: '/api/analyst/requests',
        operationId: 'analystRequestsIndex',
        tags: ['Analyste'],
        summary: '[Lister] La file d’analyse',
        description: '**Rôles :** Analyste (`analyst`), Admin (`admin`).',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CreditRequest::class);

        $requests = CreditRequest::query()
            ->with([
                'client.user',
                'activity',
                'latestAnalysis',
                'anomalies',
                'documents',
            ])
            ->inAnalystQueue()
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

    #[OA\Post(
        path: '/api/analyst/requests/{creditRequest}/review',
        operationId: 'analystReview',
        tags: ['Analyste'],
        summary: 'Enregistrer la revue analyste',
        description: '**Rôles :** Analyste (`analyst`), Admin (`admin`). `next_step=VERIFICATION_REQUIRED` renvoie le dossier au client.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/AnalystReviewRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Revue enregistrée', content: new OA\JsonContent(ref: '#/components/schemas/CreditRequestEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function review(ReviewCreditRequest $request, CreditRequest $creditRequest): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $review = CreditReview::create([
            'credit_request_id' => $creditRequest->id,
            'analyst_id' => $user->id,
            'review_status' => 'COMPLETED',
            'recommendation' => $validated['recommendation'],
            'comment' => $validated['comment'],
            'reviewed_at' => now(),
        ]);

        $nextStep = $validated['next_step'] ?? 'COMMITTEE';

        if ($nextStep === 'VERIFICATION_REQUIRED') {
            $this->workflowService->transitionStatus(
                $creditRequest,
                CreditRequestStatus::VerificationRequired,
                $user,
                "Compléments demandés par l’analyste : {$validated['recommendation']}"
            );

            return response()->json([
                'message' => 'Revue enregistrée. Le dossier a été renvoyé au client afin qu’il puisse compléter les informations demandées.',
                'returned_to_client' => true,
                'next_actor' => 'client',
                'review' => $review,
                'credit_request' => new CreditRequestResource($creditRequest->fresh(['creditReviews', 'statusHistory'])),
            ]);
        }

        if (in_array($creditRequest->status, [
            CreditRequestStatus::Submitted,
            CreditRequestStatus::Analysis,
            CreditRequestStatus::VerificationRequired,
        ], true)) {
            $this->workflowService->transitionStatus(
                $creditRequest,
                CreditRequestStatus::CreditReview,
                $user,
                'Revue analyste en cours'
            );
        }

        $this->workflowService->transitionStatus(
            $creditRequest,
            CreditRequestStatus::Committee,
            $user,
            "Recommandation analyste : {$validated['recommendation']}"
        );

        return response()->json([
            'message' => 'Votre revue a bien été enregistrée. Le dossier est transmis au comité pour décision.',
            'review' => $review,
            'credit_request' => new CreditRequestResource($creditRequest->fresh(['creditReviews', 'statusHistory'])),
        ]);
    }

    #[OA\Post(
        path: '/api/analyst/anomalies/{anomaly}/resolve',
        operationId: 'analystResolveAnomaly',
        tags: ['Analyste'],
        summary: 'Mettre à jour un point à vérifier',
        description: '**Rôles :** Analyste (`analyst`), Admin (`admin`).',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/AnomalyId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ResolveAnomalyRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Point mis à jour'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function resolveAnomaly(ResolveAnomalyRequest $request, Anomaly $anomaly): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $anomaly->update([
            'status' => $validated['status'],
            'resolved_by' => $user->id,
            'resolved_at' => now(),
            'resolution_comment' => $validated['resolution_comment'],
        ]);

        return response()->json([
            'message' => 'Le point à vérifier a bien été mis à jour. Merci pour cette précision.',
            'anomaly' => new AnomalyResource($anomaly),
        ]);
    }

    #[OA\Post(
        path: '/api/analyst/requests/{creditRequest}/human-validation',
        operationId: 'analystHumanValidation',
        tags: ['Analyste'],
        summary: 'Enregistrer une validation humaine',
        description: '**Rôles :** Analyste (`analyst`), Admin (`admin`). Décision `TO_COMPLETE` : dossier renvoyé au client.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/HumanValidationRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Contrôle enregistré'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function recordHumanValidation(StoreHumanValidationRequest $request, CreditRequest $creditRequest): JsonResponse
    {
        $validated = $request->validated();

        $validation = HumanValidation::create([
            'credit_request_id' => $creditRequest->id,
            'document_id' => $validated['document_id'] ?? null,
            'validator_id' => $request->user()->id,
            'validation_type' => $validated['validation_type'],
            'decision' => $validated['decision'],
            'comment' => $validated['comment'] ?? null,
            'validated_at' => now(),
        ]);

        if ($validation->decision === ValidationDecision::ToComplete
            && $this->workflowService->isTransitionAllowed($creditRequest->status, CreditRequestStatus::VerificationRequired)
        ) {
            $this->workflowService->transitionStatus(
                $creditRequest,
                CreditRequestStatus::VerificationRequired,
                $request->user(),
                'Validation humaine : complément demandé au client'
            );
        }

        $payload = [
            'message' => 'Votre contrôle a bien été enregistré.',
            'validation' => $validation,
            'credit_request' => new CreditRequestResource($creditRequest->fresh(['humanValidations', 'statusHistory'])),
        ];

        if ($validation->decision === ValidationDecision::ToComplete) {
            $payload['returned_to_client'] = true;
            $payload['next_actor'] = 'client';
            $payload['message'] = 'Contrôle enregistré. Le dossier a été renvoyé au client afin qu’il puisse compléter les éléments demandés.';
        }

        return response()->json($payload);
    }
}
