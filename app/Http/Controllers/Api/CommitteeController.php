<?php

namespace App\Http\Controllers\Api;

use App\Enums\CommitteeDecision;
use App\Enums\CreditRequestStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Committee\CommitteeDecisionRequest;
use App\Http\Resources\CreditRequestResource;
use App\Models\CreditCommitteeDecision;
use App\Models\CreditRequest;
use App\Models\User;
use App\Services\CreditWorkflowService;
use App\Services\InterestRateService;
use App\Services\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class CommitteeController extends Controller
{
    public function __construct(
        protected CreditWorkflowService $workflowService,
        protected LoanService $loanService,
        protected InterestRateService $interestRates,
    ) {}

    #[OA\Get(
        path: '/api/committee/requests',
        operationId: 'committeeRequestsIndex',
        tags: ['Comité'],
        summary: '[Lister] La file du comité',
        description: '**Rôles :** Membre du comité (`committee_member`), Admin (`admin`). File au comité + dossiers déjà décidés pour consultation.',
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

        $user = $request->user();

        $requests = CreditRequest::query()
            ->with([
                'client.user',
                'activity',
                'latestAnalysis',
                'anomalies',
                'documents',
                'creditReviews.analyst',
                'latestCommitteeDecision',
            ])
            ->where(function ($query) {
                $query->whereIn('status', [
                    CreditRequestStatus::PendingCommittee,
                    CreditRequestStatus::Committee,
                ])->orWhereIn('status', CreditRequestStatus::closed());
            })
            ->when(
                $user && ! $user->hasRole(RoleName::Admin) && filled($user->agency_code),
                fn ($q) => $q->where('agency_code', $user->agency_code)
            )
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
        description: '**Rôles :** Membre du comité (`committee_member`), Admin (`admin`). APPROVED/AMENDED versent le capital sur le compte épargne.',
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
        $this->workflowService->assertActorOwnsStep($user, $creditRequest);

        $validated = $request->validated();
        $decisionType = CommitteeDecision::from($validated['decision']);

        $decision = DB::transaction(function () use ($creditRequest, $user, $validated, $decisionType) {
            $lockedRequest = CreditRequest::query()->lockForUpdate()->findOrFail($creditRequest->id);

            if (! in_array($lockedRequest->status, CreditRequestStatus::committeeOwned(), true)) {
                abort(403, 'Ce dossier est clos. Cette étape est verrouillée.');
            }

            $reason = $validated['reason'] ?? null;
            $what = $validated['what'] ?? null;
            $subject = $validated['subject'] ?? null;
            $complementDetail = ($reason && $what)
                ? "Pourquoi : {$reason}. Quoi : {$what}"
                : null;

            $decision = CreditCommitteeDecision::create([
                'credit_request_id' => $lockedRequest->id,
                'committee_member_id' => $user->id,
                'decision' => $decisionType,
                'approved_amount' => $validated['approved_amount'] ?? $lockedRequest->requested_amount,
                'approved_duration_months' => $validated['approved_duration_months'] ?? $lockedRequest->duration_months,
                'annual_interest_rate_percent' => $this->interestRates->institutionalRate(),
                'comment' => $validated['comment'] ?? $complementDetail,
                'reason' => $reason,
                'what' => $what,
                'subject' => $subject,
                'complement_detail' => $complementDetail,
                'decided_at' => now(),
            ]);

            match ($decisionType) {
                CommitteeDecision::Approved, CommitteeDecision::Amended => $this->approve($lockedRequest, $user, $validated, $decisionType),
                CommitteeDecision::Adjourned => $this->adjourn($lockedRequest, $user, $reason, $what),
                CommitteeDecision::VerificationRequired => $this->returnForComplements($lockedRequest, $user, $subject, $complementDetail, $reason, $what),
                CommitteeDecision::Rejected => $this->workflowService->transitionStatus(
                    $lockedRequest,
                    CreditRequestStatus::Rejected,
                    $user,
                    "Décision Comité : {$decisionType->value}"
                ),
            };

            return $decision;
        });

        return response()->json([
            'message' => 'La décision du comité a bien été enregistrée.',
            'committee_decision' => $decision,
            'credit_request' => new CreditRequestResource($creditRequest->fresh(['loan.repayments', 'committeeDecisions', 'latestCommitteeDecision'])),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function approve(
        CreditRequest $lockedRequest,
        User $user,
        array $validated,
        CommitteeDecision $decisionType
    ): void {
        $targetStatus = $decisionType === CommitteeDecision::Amended
            ? CreditRequestStatus::Amended
            : CreditRequestStatus::Approved;

        $this->workflowService->transitionStatus(
            $lockedRequest,
            $targetStatus,
            $user,
            "Décision Comité : {$decisionType->value}"
        );

        $this->loanService->grantAndDisburse($lockedRequest, $validated, $user);
    }

    protected function adjourn(
        CreditRequest $lockedRequest,
        User $user,
        ?string $reason,
        ?string $what
    ): void {
        $lockedRequest->forceFill([
            'adjourn_reason' => $reason,
            'adjourn_what' => $what,
        ])->save();

        $this->workflowService->transitionStatus(
            $lockedRequest,
            CreditRequestStatus::Adjourned,
            $user,
            "Ajournement : {$reason}"
        );
    }

    protected function returnForComplements(
        CreditRequest $lockedRequest,
        User $user,
        ?string $subject,
        ?string $complementDetail,
        ?string $reason,
        ?string $what
    ): void {
        $lockedRequest->forceFill([
            'complement_subject' => $subject,
            'complement_detail' => $complementDetail,
            'adjourn_reason' => $reason,
            'adjourn_what' => $what,
        ])->save();

        $this->workflowService->transitionStatus(
            $lockedRequest,
            CreditRequestStatus::VerificationRequired,
            $user,
            $complementDetail ?? 'Complément demandé par le comité'
        );
    }
}
