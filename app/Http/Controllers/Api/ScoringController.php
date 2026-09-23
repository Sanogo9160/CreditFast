<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreditAnalysisResource;
use App\Models\CreditRequest;
use App\Services\CreditScoringEngine;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ScoringController extends Controller
{
    public function __construct(protected CreditScoringEngine $scoringEngine) {}

    #[OA\Post(
        path: '/api/credit-requests/{creditRequest}/score',
        operationId: 'scoringEvaluate',
        tags: ['Scoring et analyse'],
        summary: 'Calculer le score d’aide à la décision',
        description: '**Rôles :** Chargé (`credit_agent`), Analyste (`analyst`), Admin (`admin`). Le client ne peut pas déclencher le calcul (403). Le score n’emporte pas l’octroi.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Analyse calculée',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'analysis', ref: '#/components/schemas/CreditAnalysis'),
                ])
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function evaluate(CreditRequest $creditRequest): JsonResponse
    {
        $this->authorize('score', $creditRequest);

        $analysis = $this->scoringEngine->evaluateCreditRequest($creditRequest);

        return response()->json([
            'message' => 'Le score d’aide à la décision a bien été calculé. Il n’emporte pas l’octroi du crédit.',
            'analysis' => new CreditAnalysisResource($analysis->load('factors', 'scoringModel')),
        ]);
    }

    #[OA\Get(
        path: '/api/credit-requests/{creditRequest}/analysis',
        operationId: 'scoringGetAnalysis',
        tags: ['Scoring et analyse'],
        summary: '[Lire] Consulter la dernière analyse',
        description: '**Rôles :** Client propriétaire ou staff.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CreditRequestId')],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Analyse',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'analysis', ref: '#/components/schemas/CreditAnalysis'),
                ])
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function getAnalysis(CreditRequest $creditRequest): JsonResponse
    {
        $this->authorize('viewAnalysis', $creditRequest);

        $analysis = $creditRequest->latestAnalysis?->load('factors', 'scoringModel');

        if (! $analysis) {
            return response()->json(['message' => 'Aucune analyse n’est encore disponible pour ce dossier.'], 404);
        }

        return response()->json([
            'analysis' => new CreditAnalysisResource($analysis),
        ]);
    }
}
