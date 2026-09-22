<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCaisseRequest;
use App\Http\Requests\Admin\StoreCashDeskRequest;
use App\Http\Requests\Admin\StoreGuichetRequest;
use App\Http\Requests\Admin\UpdateCaisseRequest;
use App\Http\Requests\Admin\UpdateCashDeskRequest;
use App\Http\Requests\Admin\UpdateGuichetRequest;
use App\Http\Resources\CaisseResource;
use App\Http\Resources\CashDeskResource;
use App\Http\Resources\GuichetResource;
use App\Models\BankAccountApplication;
use App\Models\Caisse;
use App\Models\CashDesk;
use App\Models\Guichet;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class CaisseAdminController extends Controller
{
    // ─── Liste globale ───────────────────────────────────────────────

    #[OA\Get(
        path: '/api/admin/caisses',
        operationId: 'adminCaissesIndex',
        tags: ['Administration caisses'],
        summary: '[Lister] Tout le référentiel Caisse → Guichet → Case',
        description: <<<'MD'
**Rôle :** Admin uniquement.

Retourne toutes les caisses (actives ou non) avec leurs guichets et cases.
Utile pour administrer le réseau avant les adhésions clients.

**Hiérarchie :**
1. **Caisse** — unité (ex. Caisse de Bamako, code `BKO`)
2. **Guichet** — guichet rattaché à une caisse (ex. `G01`)
3. **Case** — till / caisse-espèces derrière le guichet (≥ 1 case active obligatoire pour qu’un guichet soit utilisable à l’adhésion)
MD,
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste complète'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function index(): JsonResponse
    {
        $caisses = Caisse::query()
            ->with(['guichets.cashDesks'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => CaisseResource::collection($caisses)]);
    }

    // ─── Caisse CRUD ─────────────────────────────────────────────────

    #[OA\Post(
        path: '/api/admin/caisses',
        operationId: 'adminCaissesStore',
        tags: ['Administration caisses'],
        summary: '[Créer] Une caisse',
        description: <<<'MD'
**Rôle :** Admin.

Créer une unité (ex. nouvelle ville). Après création, ajouter au moins un **guichet** puis une **case**.

| Champ | Obligatoire | Indication |
|-------|-------------|------------|
| `code` | oui | Code unique court (ex. `BKO`, `KAY`) — sert au N° de compte |
| `name` | oui | Libellé (ex. `Caisse de Kayes`) |
| `city` | non | Ville |
| `is_active` | non | Défaut `true` |
MD,
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreCaisseRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Caisse créée'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function storeCaisse(StoreCaisseRequest $request): JsonResponse
    {
        $caisse = Caisse::create($request->validated());

        return response()->json(['caisse' => new CaisseResource($caisse)], 201);
    }

    #[OA\Get(
        path: '/api/admin/caisses/{caisse}',
        operationId: 'adminCaissesShow',
        tags: ['Administration caisses'],
        summary: '[Lire] Une caisse avec ses guichets et cases',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CaisseId')],
        responses: [new OA\Response(response: 200, description: 'Détail')]
    )]
    public function showCaisse(Caisse $caisse): JsonResponse
    {
        return response()->json([
            'caisse' => new CaisseResource($caisse->load('guichets.cashDesks')),
        ]);
    }

    #[OA\Put(
        path: '/api/admin/caisses/{caisse}',
        operationId: 'adminCaissesUpdate',
        tags: ['Administration caisses'],
        summary: '[Modifier] Une caisse',
        description: 'Mettre à jour code, nom, ville ou activation. Changer le `code` impacte les futurs N° de compte générés.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CaisseId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateCaisseRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Caisse mise à jour'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function updateCaisse(UpdateCaisseRequest $request, Caisse $caisse): JsonResponse
    {
        $caisse->update($request->validated());

        return response()->json(['caisse' => new CaisseResource($caisse->fresh('guichets.cashDesks'))]);
    }

    #[OA\Delete(
        path: '/api/admin/caisses/{caisse}',
        operationId: 'adminCaissesDestroy',
        tags: ['Administration caisses'],
        summary: '[Supprimer] Une caisse',
        description: <<<'MD'
**Impossible** s’il existe déjà des demandes d’adhésion liées à cette caisse.
Sinon, suppression en cascade des guichets et cases rattachés.
MD,
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CaisseId')],
        responses: [
            new OA\Response(response: 200, description: 'Caisse supprimée'),
            new OA\Response(response: 422, description: 'Des adhésions y sont liées'),
        ]
    )]
    public function destroyCaisse(Caisse $caisse): JsonResponse
    {
        if (BankAccountApplication::query()->where('caisse_id', $caisse->id)->exists()) {
            throw ValidationException::withMessages([
                'caisse' => 'Impossible de supprimer cette caisse : des demandes d’adhésion y sont liées.',
            ]);
        }

        $caisse->delete();

        return response()->json(['message' => 'Caisse supprimée.']);
    }

    // ─── Guichet CRUD ────────────────────────────────────────────────

    #[OA\Post(
        path: '/api/admin/guichets',
        operationId: 'adminGuichetsStore',
        tags: ['Administration caisses'],
        summary: '[Créer] Un guichet',
        description: <<<'MD'
**Rôle :** Admin.

Le guichet appartient à **une** caisse. Le couple (`caisse_id`, `code`) doit être unique.

| Champ | Obligatoire | Indication |
|-------|-------------|------------|
| `caisse_id` | oui | ID de la caisse parente (`GET /api/admin/caisses`) |
| `code` | oui | Code unique **dans** la caisse (ex. `G01`) |
| `name` | oui | Libellé (ex. `Guichet ACI 2000`) |
| `is_active` | non | Défaut `true` — un guichet actif sans case ne sera pas proposé au client |

**Ensuite :** créer au moins une case via `POST /api/admin/cash-desks`.
MD,
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreGuichetRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Guichet créé'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function storeGuichet(StoreGuichetRequest $request): JsonResponse
    {
        $guichet = Guichet::create($request->validated());

        return response()->json(['guichet' => new GuichetResource($guichet->load('cashDesks'))], 201);
    }

    #[OA\Get(
        path: '/api/admin/guichets/{guichet}',
        operationId: 'adminGuichetsShow',
        tags: ['Administration caisses'],
        summary: '[Lire] Un guichet et ses cases',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/GuichetId')],
        responses: [new OA\Response(response: 200, description: 'Détail')]
    )]
    public function showGuichet(Guichet $guichet): JsonResponse
    {
        return response()->json([
            'guichet' => new GuichetResource($guichet->load(['caisse', 'cashDesks'])),
        ]);
    }

    #[OA\Put(
        path: '/api/admin/guichets/{guichet}',
        operationId: 'adminGuichetsUpdate',
        tags: ['Administration caisses'],
        summary: '[Modifier] Un guichet',
        description: 'Impossible d’activer (`is_active=true`) un guichet qui n’a aucune case active.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/GuichetId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateGuichetRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Guichet mis à jour'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function updateGuichet(UpdateGuichetRequest $request, Guichet $guichet): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('is_active', $data) && $data['is_active'] === true) {
            if (! $guichet->cashDesks()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages([
                    'is_active' => 'Impossible d’activer un guichet sans case active. Créez d’abord une case.',
                ]);
            }
        }

        $guichet->update($data);

        return response()->json(['guichet' => new GuichetResource($guichet->fresh(['caisse', 'cashDesks']))]);
    }

    #[OA\Delete(
        path: '/api/admin/guichets/{guichet}',
        operationId: 'adminGuichetsDestroy',
        tags: ['Administration caisses'],
        summary: '[Supprimer] Un guichet',
        description: 'Impossible s’il existe des demandes d’adhésion liées. Sinon, suppression en cascade des cases.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/GuichetId')],
        responses: [
            new OA\Response(response: 200, description: 'Guichet supprimé'),
            new OA\Response(response: 422, description: 'Des adhésions y sont liées'),
        ]
    )]
    public function destroyGuichet(Guichet $guichet): JsonResponse
    {
        if (BankAccountApplication::query()->where('guichet_id', $guichet->id)->exists()) {
            throw ValidationException::withMessages([
                'guichet' => 'Impossible de supprimer ce guichet : des demandes d’adhésion y sont liées.',
            ]);
        }

        $guichet->delete();

        return response()->json(['message' => 'Guichet supprimé.']);
    }

    // ─── Case (cash desk) CRUD ───────────────────────────────────────

    #[OA\Post(
        path: '/api/admin/cash-desks',
        operationId: 'adminCashDesksStore',
        tags: ['Administration caisses'],
        summary: '[Créer] Une case (till) derrière un guichet',
        description: <<<'MD'
**Rôle :** Admin.

Chaque guichet doit avoir **au moins une case active** pour être sélectionnable par le client à l’adhésion.

| Champ | Obligatoire | Indication |
|-------|-------------|------------|
| `guichet_id` | oui | ID du guichet parent |
| `code` | oui | Unique **dans** le guichet (ex. `C01`) |
| `label` | oui | Libellé (ex. `Case 1`) |
| `is_active` | non | Défaut `true` |
MD,
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreCashDeskRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Case créée'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function storeCashDesk(StoreCashDeskRequest $request): JsonResponse
    {
        $desk = CashDesk::create($request->validated());

        return response()->json(['cash_desk' => new CashDeskResource($desk)], 201);
    }

    #[OA\Get(
        path: '/api/admin/cash-desks/{cashDesk}',
        operationId: 'adminCashDesksShow',
        tags: ['Administration caisses'],
        summary: '[Lire] Une case',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CashDeskId')],
        responses: [new OA\Response(response: 200, description: 'Détail')]
    )]
    public function showCashDesk(CashDesk $cashDesk): JsonResponse
    {
        return response()->json([
            'cash_desk' => new CashDeskResource($cashDesk->load('guichet')),
        ]);
    }

    #[OA\Put(
        path: '/api/admin/cash-desks/{cashDesk}',
        operationId: 'adminCashDesksUpdate',
        tags: ['Administration caisses'],
        summary: '[Modifier] Une case',
        description: 'Impossible de désactiver la **dernière** case active d’un guichet encore actif.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CashDeskId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateCashDeskRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Case mise à jour'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function updateCashDesk(UpdateCashDeskRequest $request, CashDesk $cashDesk): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('is_active', $data) && $data['is_active'] === false) {
            $this->assertNotLastActiveCashDesk($cashDesk);
        }

        $cashDesk->update($data);

        return response()->json(['cash_desk' => new CashDeskResource($cashDesk->fresh())]);
    }

    #[OA\Delete(
        path: '/api/admin/cash-desks/{cashDesk}',
        operationId: 'adminCashDesksDestroy',
        tags: ['Administration caisses'],
        summary: '[Supprimer] Une case',
        description: 'Impossible de supprimer la dernière case active d’un guichet actif. Impossible aussi si des comptes financiers y sont liés.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/CashDeskId')],
        responses: [
            new OA\Response(response: 200, description: 'Case supprimée'),
            new OA\Response(response: 422, description: 'Dernière case ou liée à des comptes'),
        ]
    )]
    public function destroyCashDesk(CashDesk $cashDesk): JsonResponse
    {
        if ($cashDesk->is_active) {
            $this->assertNotLastActiveCashDesk($cashDesk);
        }

        if ($cashDesk->guichet->cashDesks()->whereKeyNot($cashDesk->id)->doesntExist()
            && BankAccountApplication::query()->where('guichet_id', $cashDesk->guichet_id)->exists()) {
            throw ValidationException::withMessages([
                'cash_desk' => 'Impossible de supprimer la seule case de ce guichet : des adhésions y sont liées.',
            ]);
        }

        $cashDesk->delete();

        return response()->json(['message' => 'Case supprimée.']);
    }

    protected function assertNotLastActiveCashDesk(CashDesk $cashDesk): void
    {
        $activeSiblings = $cashDesk->guichet->cashDesks()
            ->where('is_active', true)
            ->whereKeyNot($cashDesk->id)
            ->count();

        if ($activeSiblings < 1 && $cashDesk->guichet->is_active) {
            throw ValidationException::withMessages([
                'is_active' => 'Chaque guichet actif doit conserver au moins une case active.',
            ]);
        }
    }
}
