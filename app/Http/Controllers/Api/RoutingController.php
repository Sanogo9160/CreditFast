<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreditRequestResource;
use App\Models\CreditRequest;
use App\Models\User;
use App\Services\AgentAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class RoutingController extends Controller
{
    public function __construct(protected AgentAssignmentService $assignments) {}

    #[OA\Get(
        path: '/api/routing/catalog',
        operationId: 'routingCatalog',
        tags: ['Routage'],
        summary: '[Lister] Catalogue agences et zones',
        description: '**Rôles :** utilisateur authentifié. La zone choisie doit appartenir à l’agence du compte épargne.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Agences et zones'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function catalog(): JsonResponse
    {
        return response()->json($this->assignments->catalog());
    }

    #[OA\Get(
        path: '/api/routing/requests',
        operationId: 'routingRequestsIndex',
        tags: ['Routage'],
        summary: '[Lister] File à affecter',
        description: '**Rôles :** Admin. Dossiers non brouillon.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function requests(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole(RoleName::Admin), 403);

        $requests = CreditRequest::query()
            ->with(['client.user', 'assignedAgent', 'activity'])
            ->where('status', '!=', 'DRAFT')
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
        path: '/api/routing/requests/{creditRequest}/assign',
        operationId: 'routingAssign',
        tags: ['Routage'],
        summary: 'Affecter un agent',
        description: '**Rôles :** Admin. Agent disponible de la même agence. Motif obligatoire.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['agent_id', 'reason'], properties: [
                new OA\Property(property: 'agent_id', type: 'integer', example: 2),
                new OA\Property(property: 'reason', type: 'string', minLength: 5, example: 'Réaffectation suite à congé'),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Dossier affecté'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function assign(Request $request, CreditRequest $creditRequest): JsonResponse
    {
        abort_unless($request->user()?->hasRole(RoleName::Admin), 403);

        $validated = $request->validate([
            'agent_id' => ['required', 'exists:users,id'],
            'reason' => ['required', 'string', 'min:5'],
        ]);

        $agent = User::query()->findOrFail($validated['agent_id']);

        if (! $agent->hasRole(RoleName::CreditAgent)) {
            throw ValidationException::withMessages([
                'agent_id' => 'L’utilisateur choisi n’est pas un agent de crédit.',
            ]);
        }

        if (! $agent->available) {
            throw ValidationException::withMessages([
                'agent_id' => 'Cet agent n’est pas disponible.',
            ]);
        }

        $updated = $this->assignments->assignManually(
            $creditRequest,
            $agent,
            $request->user(),
            $validated['reason']
        );

        return response()->json([
            'message' => 'Le dossier a bien été affecté.',
            'credit_request' => new CreditRequestResource($updated->load(['client.user', 'assignedAgent'])),
        ]);
    }

    #[OA\Put(
        path: '/api/routing/agents/{agent}',
        operationId: 'routingUpdateAgent',
        tags: ['Routage'],
        summary: '[Modifier] Couverture d’un agent',
        description: '**Rôles :** Admin. `agency_code`, `zone_codes`, `available`.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'agent',
                description: 'Identifiant de l’agent (utilisateur credit_agent)',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 2)
            ),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'agency_code', type: 'string', example: 'BKO-HAM'),
                new OA\Property(property: 'zone_codes', type: 'array', items: new OA\Items(type: 'string'), example: ['HAM']),
                new OA\Property(property: 'available', type: 'boolean', example: true),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Couverture mise à jour'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function updateAgent(Request $request, User $agent): JsonResponse
    {
        abort_unless($request->user()?->hasRole(RoleName::Admin), 403);
        abort_unless($agent->hasRole(RoleName::CreditAgent), 404);

        $validated = $request->validate([
            'agency_code' => ['sometimes', 'string', 'max:30'],
            'zone_codes' => ['sometimes', 'array'],
            'zone_codes.*' => ['string', 'max:80'],
            'available' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->assignments->updateAgentCoverage($agent, $validated);

        return response()->json([
            'message' => 'La couverture de l’agent a été mise à jour.',
            'agent' => [
                'id' => $updated->id,
                'agency_code' => $updated->agency_code,
                'zone_codes' => $updated->zone_codes ?? [],
                'available' => (bool) $updated->available,
            ],
        ]);
    }
}
