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

class RoutingController extends Controller
{
    public function __construct(protected AgentAssignmentService $assignments) {}

    public function catalog(): JsonResponse
    {
        return response()->json($this->assignments->catalog());
    }

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
