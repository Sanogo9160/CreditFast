<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Loan\DisburseLoanRequest;
use App\Http\Requests\Loan\RecordRepaymentRequest;
use App\Http\Resources\LoanRepaymentResource;
use App\Http\Resources\LoanResource;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Services\LoanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class LoanController extends Controller
{
    /**
     * Consultation : client (ses prêts) et staff.
     * Décaissement et enregistrement des remboursements : chargé de crédit et admin uniquement.
     */
    public function __construct(protected LoanService $loanService) {}

    #[OA\Get(
        path: '/api/loans',
        operationId: 'loansIndex',
        tags: ['Prêts'],
        summary: '[Lister] Les prêts',
        description: '**Rôles :** Client (ses prêts) ; staff (tous). Consultation uniquement pour le client.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Loan::class);

        $user = $request->user();
        $query = Loan::query()
            ->with(['repayments' => fn ($repayments) => $repayments->orderBy('due_date')->orderBy('id')])
            ->latest()
            ->orderByDesc('id');

        if (! $user->isStaff()) {
            $client = $user->client;
            if (! $client) {
                return response()->json(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]]);
            }
            $query->forClient($client->id);
        }

        $loans = $query->paginate(15);

        return response()->json([
            'data' => LoanResource::collection($loans),
            'meta' => [
                'current_page' => $loans->currentPage(),
                'last_page' => $loans->lastPage(),
                'total' => $loans->total(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/loans/{loan}',
        operationId: 'loansShow',
        tags: ['Prêts'],
        summary: '[Lire] Détail d’un prêt',
        description: '**Rôles :** Client propriétaire (consultation : statut, fonds reçus, échéancier) ou staff.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/LoanId')],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Prêt',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'loan', ref: '#/components/schemas/Loan'),
                ])
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function show(Loan $loan): JsonResponse
    {
        $this->authorize('view', $loan);

        $loan->load([
            'repayments' => fn ($repayments) => $repayments->orderBy('due_date')->orderBy('id'),
            'creditRequest',
            'client.user',
        ]);

        return response()->json([
            'loan' => new LoanResource($loan),
        ]);
    }

    #[OA\Get(
        path: '/api/loans/{loan}/repayments',
        operationId: 'loansRepayments',
        tags: ['Prêts'],
        summary: '[Lister] L’échéancier',
        description: '**Rôles :** Client propriétaire ou staff. Lecture seule pour le client (statut, fonds reçus, échéances).',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/LoanId')],
        responses: [
            new OA\Response(response: 200, description: 'Échéancier'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function repayments(Loan $loan): JsonResponse
    {
        $this->authorize('view', $loan);

        $repayments = $loan->repayments()->orderBy('due_date')->orderBy('id')->get();

        return response()->json([
            'loan_id' => $loan->id,
            'loan_status' => $loan->status?->value ?? $loan->status,
            'funds_received' => $loan->disbursed_at ? (float) $loan->principal_amount : 0.0,
            'outstanding_amount' => (float) $loan->outstanding_amount,
            'repayments' => LoanRepaymentResource::collection($repayments),
        ]);
    }

    #[OA\Post(
        path: '/api/loans/{loan}/disburse',
        operationId: 'loansDisburse',
        tags: ['Prêts'],
        summary: 'Décaisser un prêt accordé',
        description: '**Rôles exclusifs :** Chargé (`credit_agent`) et Admin (`admin`). Un client reçoit HTTP 403.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/LoanId')],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: '#/components/schemas/DisburseLoanRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Crédit décaissé, échéancier généré'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function disburse(DisburseLoanRequest $request, Loan $loan): JsonResponse
    {
        $this->authorize('disburse', $loan);

        $validated = $request->validated();
        $disbursedAt = isset($validated['disbursed_at'])
            ? Carbon::parse($validated['disbursed_at'])
            : null;

        try {
            $updated = $this->loanService->disburse(
                $loan,
                $request->user(),
                $disbursedAt,
                $validated['comment'] ?? null
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['loan' => [$exception->getMessage()]],
            ], 422);
        }

        return response()->json([
            'message' => 'Le crédit a bien été décaissé. L’échéancier a été établi à partir de la date de valeur.',
            'loan' => new LoanResource($updated),
        ]);
    }

    #[OA\Post(
        path: '/api/loans/{loan}/repayments/{repayment}/record',
        operationId: 'loansRecordRepayment',
        tags: ['Prêts'],
        summary: 'Enregistrer un remboursement',
        description: '**Rôles exclusifs :** Chargé (`credit_agent`) et Admin (`admin`). Un client reçoit HTTP 403.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/LoanId'),
            new OA\Parameter(ref: '#/components/parameters/RepaymentId'),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RecordRepaymentRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Remboursement enregistré'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function recordRepayment(RecordRepaymentRequest $request, Loan $loan, LoanRepayment $repayment): JsonResponse
    {
        $this->authorize('recordRepayment', $loan);

        $validated = $request->validated();
        $paymentDate = isset($validated['payment_date'])
            ? Carbon::parse($validated['payment_date'])
            : null;

        $updated = $this->loanService->recordRepayment(
            $repayment,
            (float) $validated['paid_amount'],
            $request->user(),
            $paymentDate,
            $validated['comment'] ?? null
        );

        return response()->json([
            'message' => 'Le remboursement a bien été enregistré.',
            'repayment' => new LoanRepaymentResource($updated),
            'loan' => new LoanResource($updated->loan->load([
                'repayments' => fn ($repayments) => $repayments->orderBy('due_date')->orderBy('id'),
            ])),
        ]);
    }
}
