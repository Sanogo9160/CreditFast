<?php

namespace App\Http\Controllers\Api;

use App\Enums\CommitteeDecision;
use App\Enums\CreditRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Committee\CommitteeDecisionRequest;
use App\Http\Resources\CreditRequestResource;
use App\Models\CreditCommitteeDecision;
use App\Models\CreditRequest;
use App\Services\CreditWorkflowService;
use App\Services\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class CommitteeController extends Controller
{
    public function __construct(
        protected CreditWorkflowService $workflowService,
        protected LoanService $loanService
    ) {}

    #[OA\Get(
        path: '/api/committee/requests',
        operationId: 'committeeRequestsIndex',
        tags: ['Comité'],
        summary: '[Lister] La file du comité',
        description: '**Rôles :** Membre du comité (`committee_member`), Admin (`admin`).',
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
                'creditReviews.analyst',
            ])
            ->inCommitteeQueue()
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
        path: '/api/committee/requests/{creditRequest}/decide',
        operationId: 'committeeDecide',
        tags: ['Comité'],
        summary: 'Décider de l’octroi',
        description: '**Rôles :** Membre du comité (`committee_member`), Admin (`admin`). **Valeurs :** `decision` = `APPROVED` | `REJECTED` | `AMENDED`. `APPROVED` / `AMENDED` créent le prêt (sans décaissement).',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CommitteeDecisionRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Décision enregistrée'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function decide(CommitteeDecisionRequest $request, CreditRequest $creditRequest): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $decisionType = CommitteeDecision::from($validated['decision']);

        $decision = DB::transaction(function () use ($creditRequest, $user, $validated, $decisionType) {
            $lockedRequest = CreditRequest::query()->lockForUpdate()->findOrFail($creditRequest->id);

            $decision = CreditCommitteeDecision::create([
                'credit_request_id' => $lockedRequest->id,
                'committee_member_id' => $user->id,
                'decision' => $decisionType,
                'approved_amount' => $validated['approved_amount'] ?? $lockedRequest->requested_amount,
                'approved_duration_months' => $validated['approved_duration_months'] ?? $lockedRequest->duration_months,
                'comment' => $validated['comment'],
                'decided_at' => now(),
            ]);

            if (in_array($decisionType, [CommitteeDecision::Approved, CommitteeDecision::Amended], true)) {
                $this->workflowService->transitionStatus(
                    $lockedRequest,
                    CreditRequestStatus::Approved,
                    $user,
                    "Décision Comité : {$decisionType->value}"
                );

                $this->loanService->createApprovedLoan($lockedRequest, $validated);
            } else {
                $this->workflowService->transitionStatus(
                    $lockedRequest,
                    CreditRequestStatus::Rejected,
                    $user,
                    "Décision Comité : {$decisionType->value}"
                );
            }

            return $decision;
        });

        return response()->json([
            'message' => 'La décision du comité a bien été enregistrée.',
            'committee_decision' => $decision,
            'credit_request' => new CreditRequestResource($creditRequest->fresh(['loan.repayments', 'committeeDecisions'])),
        ]);
    }
}
