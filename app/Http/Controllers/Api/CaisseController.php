<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CaisseResource;
use App\Http\Resources\GuichetResource;
use App\Models\Caisse;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class CaisseController extends Controller
{
    #[OA\Get(
        path: '/api/caisses',
        operationId: 'caissesIndex',
        tags: ['Adhésion compte PP', 'Adhésion compte PM'],
        summary: '[Lister] Les caisses actives',
        description: 'Référentiel public authentifié. Inclut les guichets sélectionnables (≥ 1 case active).',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste des caisses'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function index(): JsonResponse
    {
        $caisses = Caisse::query()
            ->where('is_active', true)
            ->with(['guichets' => fn ($q) => $q->selectable()->with(['cashDesks' => fn ($d) => $d->where('is_active', true)])])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => CaisseResource::collection($caisses),
        ]);
    }

    #[OA\Get(
        path: '/api/caisses/{caisse}/guichets',
        operationId: 'caisseGuichetsIndex',
        tags: ['Adhésion compte PP', 'Adhésion compte PM'],
        summary: '[Lister] Les guichets d’une caisse',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Guichets sélectionnables'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function guichets(Caisse $caisse): JsonResponse
    {
        abort_unless($caisse->is_active, 404);

        $guichets = $caisse->guichets()
            ->selectable()
            ->with(['cashDesks' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return response()->json([
            'caisse_id' => $caisse->id,
            'data' => GuichetResource::collection($guichets),
        ]);
    }
}
