<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simulation\SimulateInstallmentsRequest;
use App\Services\CreditSimulationService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class SimulationController extends Controller
{
    public function __construct(protected CreditSimulationService $simulationService) {}

    #[OA\Post(
        path: '/api/simulations/installments',
        operationId: 'simulationsInstallments',
        tags: ['Simulation'],
        summary: 'Comparer des scénarios de mensualités',
        description: '**Rôles :** utilisateur authentifié (tous rôles).',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SimulateInstallmentsRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Comparaison des scénarios'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function installments(SimulateInstallmentsRequest $request): JsonResponse
    {
        $user = $request->user()->loadMissing('client.financialProfile');
        $validated = $request->validated();
        $profile = $user->client?->financialProfile;

        $monthlyIncome = (float) ($validated['monthly_income'] ?? $profile?->monthly_income ?? 0);
        $otherIncome = (float) ($validated['other_income'] ?? $profile?->other_income ?? 0);
        $monthlyExpenses = (float) ($validated['monthly_expenses'] ?? $profile?->monthly_expenses ?? 0);
        $existingDebt = (float) ($validated['existing_debt_payment'] ?? $profile?->existing_debt_payment ?? 0);

        $result = $this->simulationService->compareScenarios(
            $monthlyIncome,
            $otherIncome,
            $monthlyExpenses,
            $existingDebt,
            $validated['scenarios']
        );

        return response()->json($result);
    }
}
