<?php

namespace App\Http\Controllers\Api;

use App\Enums\BankAccountApplicationStatus;
use App\Enums\ClientType;
use App\Http\Controllers\Controller;
use App\Http\Requests\BankAccount\ApproveBankAccountApplicationRequest;
use App\Http\Requests\BankAccount\RejectBankAccountApplicationRequest;
use App\Http\Requests\BankAccount\ReturnBankAccountApplicationRequest;
use App\Http\Resources\BankAccountApplicationResource;
use App\Models\BankAccountApplication;
use App\Services\BankAccountApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use OpenApi\Attributes as OA;

class AgentBankAccountApplicationController extends Controller
{
    public function __construct(protected BankAccountApplicationService $applications) {}

    #[OA\Get(
        path: '/api/agent/bank-account-applications/physical-person',
        operationId: 'agentPpBankAccountApplicationsIndex',
        tags: ['Adhésion compte PP'],
        summary: '[Lister] File adhésions personne physique',
        description: '**Rôles :** Chargé, Admin. Filtre `status` (défaut SUBMITTED, ou ALL).',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Liste paginée')]
    )]
    public function indexPhysical(Request $request): JsonResponse
    {
        return $this->indexByType($request, ClientType::PhysicalPerson);
    }

    #[OA\Get(
        path: '/api/agent/bank-account-applications/legal-entity',
        operationId: 'agentPmBankAccountApplicationsIndex',
        tags: ['Adhésion compte PM'],
        summary: '[Lister] File adhésions personne morale',
        description: '**Rôles :** Chargé, Admin. Filtre `status` (défaut SUBMITTED, ou ALL).',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Liste paginée')]
    )]
    public function indexLegal(Request $request): JsonResponse
    {
        return $this->indexByType($request, ClientType::LegalEntity);
    }

    #[OA\Get(
        path: '/api/agent/bank-account-applications/physical-person/{bankAccountApplication}',
        operationId: 'agentPpBankAccountApplicationsShow',
        tags: ['Adhésion compte PP'],
        summary: '[Lire] Demande PP (agent)',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Détail')]
    )]
    public function showPhysical(BankAccountApplication $bankAccountApplication): JsonResponse
    {
        return $this->showByType($bankAccountApplication, ClientType::PhysicalPerson);
    }

    #[OA\Get(
        path: '/api/agent/bank-account-applications/legal-entity/{bankAccountApplication}',
        operationId: 'agentPmBankAccountApplicationsShow',
        tags: ['Adhésion compte PM'],
        summary: '[Lire] Demande PM (agent)',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Détail')]
    )]
    public function showLegal(BankAccountApplication $bankAccountApplication): JsonResponse
    {
        return $this->showByType($bankAccountApplication, ClientType::LegalEntity);
    }

    #[OA\Post(
        path: '/api/agent/bank-account-applications/physical-person/{bankAccountApplication}/return',
        operationId: 'agentPpBankAccountApplicationsReturn',
        tags: ['Adhésion compte PP'],
        summary: '[Renvoyer] Demande PP au client',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Renvoyée')]
    )]
    public function returnPhysical(
        ReturnBankAccountApplicationRequest $request,
        BankAccountApplication $bankAccountApplication
    ): JsonResponse {
        $this->assertType($bankAccountApplication, ClientType::PhysicalPerson);

        return $this->returnToClient($request, $bankAccountApplication);
    }

    #[OA\Post(
        path: '/api/agent/bank-account-applications/legal-entity/{bankAccountApplication}/return',
        operationId: 'agentPmBankAccountApplicationsReturn',
        tags: ['Adhésion compte PM'],
        summary: '[Renvoyer] Demande PM au client',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Renvoyée')]
    )]
    public function returnLegal(
        ReturnBankAccountApplicationRequest $request,
        BankAccountApplication $bankAccountApplication
    ): JsonResponse {
        $this->assertType($bankAccountApplication, ClientType::LegalEntity);

        return $this->returnToClient($request, $bankAccountApplication);
    }

    #[OA\Post(
        path: '/api/agent/bank-account-applications/physical-person/{bankAccountApplication}/reject',
        operationId: 'agentPpBankAccountApplicationsReject',
        tags: ['Adhésion compte PP'],
        summary: '[Refuser] Demande PP',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Refusée')]
    )]
    public function rejectPhysical(
        RejectBankAccountApplicationRequest $request,
        BankAccountApplication $bankAccountApplication
    ): JsonResponse {
        $this->assertType($bankAccountApplication, ClientType::PhysicalPerson);

        return $this->reject($request, $bankAccountApplication);
    }

    #[OA\Post(
        path: '/api/agent/bank-account-applications/legal-entity/{bankAccountApplication}/reject',
        operationId: 'agentPmBankAccountApplicationsReject',
        tags: ['Adhésion compte PM'],
        summary: '[Refuser] Demande PM',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Refusée')]
    )]
    public function rejectLegal(
        RejectBankAccountApplicationRequest $request,
        BankAccountApplication $bankAccountApplication
    ): JsonResponse {
        $this->assertType($bankAccountApplication, ClientType::LegalEntity);

        return $this->reject($request, $bankAccountApplication);
    }

    #[OA\Post(
        path: '/api/agent/bank-account-applications/physical-person/{bankAccountApplication}/approve',
        operationId: 'agentPpBankAccountApplicationsApprove',
        tags: ['Adhésion compte PP'],
        summary: '[Approuver] Demande PP — N° de compte auto',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Compte créé')]
    )]
    public function approvePhysical(
        ApproveBankAccountApplicationRequest $request,
        BankAccountApplication $bankAccountApplication
    ): JsonResponse {
        $this->assertType($bankAccountApplication, ClientType::PhysicalPerson);

        return $this->approve($request, $bankAccountApplication);
    }

    #[OA\Post(
        path: '/api/agent/bank-account-applications/legal-entity/{bankAccountApplication}/approve',
        operationId: 'agentPmBankAccountApplicationsApprove',
        tags: ['Adhésion compte PM'],
        summary: '[Approuver] Demande PM — N° de compte auto',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Compte créé')]
    )]
    public function approveLegal(
        ApproveBankAccountApplicationRequest $request,
        BankAccountApplication $bankAccountApplication
    ): JsonResponse {
        $this->assertType($bankAccountApplication, ClientType::LegalEntity);

        return $this->approve($request, $bankAccountApplication);
    }

    protected function indexByType(Request $request, ClientType $type): JsonResponse
    {
        $this->authorize('viewAny', BankAccountApplication::class);

        $status = $request->string('status')->toString() ?: BankAccountApplicationStatus::Submitted->value;

        $items = BankAccountApplication::query()
            ->whereHas('client', fn ($q) => $q->where('client_type', $type))
            ->with(['client.user', 'caisse', 'guichet', 'parties', 'documents'])
            ->when($status !== 'ALL', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(15);

        return response()->json([
            'client_type' => $type->value,
            'data' => BankAccountApplicationResource::collection($items),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    protected function showByType(BankAccountApplication $bankAccountApplication, ClientType $type): JsonResponse
    {
        $this->assertType($bankAccountApplication, $type);
        $this->authorize('view', $bankAccountApplication);

        $bankAccountApplication->load([
            'client.user',
            'caisse',
            'guichet.cashDesks',
            'cashDesk',
            'financialAccount',
            'parties',
            'documents',
            'reviewer',
        ]);

        return response()->json([
            'application' => new BankAccountApplicationResource($bankAccountApplication),
        ]);
    }

    protected function returnToClient(
        ReturnBankAccountApplicationRequest $request,
        BankAccountApplication $bankAccountApplication
    ): JsonResponse {
        try {
            $application = $this->applications->returnForCompletion(
                $bankAccountApplication,
                $request->user(),
                $request->validated('comment')
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'application' => new BankAccountApplicationResource($application),
            'returned_to_client' => true,
        ]);
    }

    protected function reject(
        RejectBankAccountApplicationRequest $request,
        BankAccountApplication $bankAccountApplication
    ): JsonResponse {
        try {
            $application = $this->applications->reject(
                $bankAccountApplication,
                $request->user(),
                $request->validated('comment')
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'application' => new BankAccountApplicationResource($application),
        ]);
    }

    protected function approve(
        ApproveBankAccountApplicationRequest $request,
        BankAccountApplication $bankAccountApplication
    ): JsonResponse {
        try {
            $application = $this->applications->approve(
                $bankAccountApplication,
                $request->user(),
                $request->validated()
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'application' => new BankAccountApplicationResource($application),
            'account_number' => $application->financialAccount?->account_number,
        ]);
    }

    protected function assertType(BankAccountApplication $application, ClientType $type): void
    {
        $application->loadMissing('client');
        abort_unless($application->client?->client_type === $type, 404);
    }
}
