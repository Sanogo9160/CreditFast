<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CreditProductCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CreditProductController extends Controller
{
    public function __construct(protected CreditProductCatalog $catalog) {}

    #[OA\Get(
        path: '/api/credit-products',
        operationId: 'creditProductsIndex',
        tags: ['Produits de crédit'],
        summary: '[Lister] Les types de crédit compatibles avec le profil',
        description: '**Rôles :** Client authentifié — uniquement les produits alignés sur `client_type` (personne physique ou morale). Staff — catalogue complet.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Catalogue filtré'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isStaff()) {
            return response()->json([
                'client_type' => null,
                'data' => $this->catalog->all(),
            ]);
        }

        $client = $user->client()->first();

        return response()->json([
            'client_type' => $client?->client_type?->value,
            'data' => $this->catalog->forClient($client),
        ]);
    }
}
