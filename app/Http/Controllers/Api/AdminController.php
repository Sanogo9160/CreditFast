<?php

namespace App\Http\Controllers\Api;

use App\Enums\ScoringModelStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetUserPasswordRequest;
use App\Http\Requests\Admin\StoreScoringModelRequest;
use App\Http\Requests\Admin\StoreScoringRuleRequest;
use App\Http\Requests\Admin\StoreStaffUserRequest;
use App\Http\Requests\Admin\UpdateScoringModelStatusRequest;
use App\Http\Requests\Admin\UpdateStaffUserRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Role;
use App\Models\ScoringModel;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ScoringModelAdminService;
use App\Services\UserPasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminController extends Controller
{
    public function __construct(
        protected ScoringModelAdminService $scoringAdmin,
        protected UserPasswordService $passwords,
        protected AuditLogger $auditLogger,
    ) {}

    #[OA\Get(
        path: '/api/admin/users',
        operationId: 'adminUsersIndex',
        tags: ['Administration'],
        summary: '[Lister] Les utilisateurs internes',
        description: '**Rôles :** Admin (`admin`).',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function users(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('role')
            ->latest()
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => UserResource::collection($users),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/admin/users',
        operationId: 'adminUsersStore',
        tags: ['Administration'],
        summary: '[Créer] Un compte interne',
        description: '**Rôles :** Admin (`admin`). **Valeurs :** `role` = `admin` | `credit_agent` | `analyst` | `committee_member`.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreStaffUserRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Compte créé'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function storeUser(StoreStaffUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $role = Role::where('name', $validated['role'])->firstOrFail();

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Le compte interne a bien été créé.',
            'user' => new UserResource($user->load('role')),
        ], 201);
    }

    #[OA\Get(
        path: '/api/admin/users/{user}',
        operationId: 'adminUsersShow',
        tags: ['Administration'],
        summary: '[Lire] Consulter un utilisateur',
        description: '**Rôles :** Admin (`admin`).',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/UserId')],
        responses: [
            new OA\Response(response: 200, description: 'Utilisateur'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function showUser(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'user' => new UserResource($user->load('role')),
        ]);
    }

    #[OA\Put(
        path: '/api/admin/users/{user}',
        operationId: 'adminUsersUpdate',
        tags: ['Administration'],
        summary: '[Modifier] Un compte interne',
        description: '**Rôles :** Admin (`admin`). **Valeurs :** `status` = `active` | `inactive`. `role` = `admin` | `credit_agent` | `analyst` | `committee_member`.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/UserId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateStaffUserRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Compte mis à jour'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function updateUser(UpdateStaffUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();
        $payload = collect($validated)->except(['role'])->all();

        if (isset($validated['role'])) {
            $payload['role_id'] = Role::where('name', $validated['role'])->firstOrFail()->id;
        }

        $user->update($payload);

        if (($payload['status'] ?? null) === 'inactive') {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'Le compte a bien été mis à jour.',
            'user' => new UserResource($user->fresh('role')),
        ]);
    }

    #[OA\Delete(
        path: '/api/admin/users/{user}',
        operationId: 'adminUsersDestroy',
        tags: ['Administration'],
        summary: '[Supprimer] Désactiver un compte',
        description: '**Rôles :** Admin (`admin`). Désactive le compte et révoque les jetons. Impossible sur son propre compte.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/UserId')],
        responses: [
            new OA\Response(response: 200, description: 'Compte désactivé', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function destroyUser(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->update(['status' => 'inactive']);
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Le compte a été désactivé.',
            'user' => new UserResource($user->fresh('role')),
        ]);
    }

    #[OA\Put(
        path: '/api/admin/users/{user}/password',
        operationId: 'adminUsersResetPassword',
        tags: ['Administration'],
        summary: '[Modifier] Réinitialiser le mot de passe d’un utilisateur',
        description: '**Rôles :** Admin (`admin`). Impossible sur son propre compte (utiliser `/api/auth/password`). Révoque toutes les sessions de la cible.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/UserId')],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ResetUserPasswordRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Mot de passe réinitialisé', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function resetUserPassword(ResetUserPasswordRequest $request, User $user): JsonResponse
    {
        $this->passwords->reset($user, $request->validated('password'));

        $this->auditLogger->record(
            $request->user(),
            'USER_PASSWORD_RESET',
            User::class,
            $user->id,
            ['target_email' => $user->email],
        );

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Mot de passe réinitialisé',
            'message' => 'Un administrateur a réinitialisé votre mot de passe. Connectez-vous avec le nouveau mot de passe communiqué par votre agence.',
            'type' => 'PASSWORD_RESET',
        ]);

        return response()->json([
            'message' => 'Le mot de passe a été réinitialisé. L’utilisateur devra se reconnecter.',
        ]);
    }

    #[OA\Get(
        path: '/api/admin/scoring-models',
        operationId: 'adminScoringModelsIndex',
        tags: ['Administration'],
        summary: '[Lister] Les modèles de scoring',
        description: '**Rôles :** Admin (`admin`).',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Modèles'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function scoringModels(): JsonResponse
    {
        $this->authorize('viewAny', ScoringModel::class);

        $models = ScoringModel::query()
            ->with('rules')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $models]);
    }

    #[OA\Post(
        path: '/api/admin/scoring-models',
        operationId: 'adminScoringModelsStore',
        tags: ['Administration'],
        summary: '[Créer] Un modèle de scoring',
        description: '**Rôles :** Admin (`admin`). **Valeurs :** `scoring_mode` = `STANDARD`. Le modèle reste en `DRAFT` jusqu’à activation.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreScoringModelRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Modèle enregistré'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function storeScoringModel(StoreScoringModelRequest $request): JsonResponse
    {
        $model = $this->scoringAdmin->createModel($request->validated(), $request->user());

        return response()->json([
            'message' => 'Modèle de scoring enregistré. Il reste en préparation jusqu’à son activation par l’institution.',
            'scoring_model' => $model,
        ], 201);
    }

    #[OA\Post(
        path: '/api/admin/scoring-models/{scoringModel}/status',
        operationId: 'adminScoringModelsStatus',
        tags: ['Administration'],
        summary: '[Modifier] Le statut d’un modèle',
        description: '**Rôles :** Admin (`admin`). **Valeurs :** `status` = `DRAFT` | `ACTIVE` | `INACTIVE` | `ARCHIVED`.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/ScoringModelId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateScoringModelStatusRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Statut mis à jour'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function updateScoringModelStatus(UpdateScoringModelStatusRequest $request, ScoringModel $scoringModel): JsonResponse
    {
        $updated = $this->scoringAdmin->changeStatus(
            $scoringModel,
            ScoringModelStatus::from($request->validated('status')),
            $request->user()
        );

        return response()->json([
            'message' => 'Le statut du modèle a bien été mis à jour.',
            'scoring_model' => $updated,
        ]);
    }

    #[OA\Post(
        path: '/api/admin/scoring-models/{scoringModel}/rules',
        operationId: 'adminScoringRulesStore',
        tags: ['Administration'],
        summary: '[Créer] Une règle de modèle',
        description: '**Rôles :** Admin (`admin`). **Valeurs :** `factor_type` = `income_consistency` | `expense` | `activity` | `document` | `savings` | `credit_history` | `guarantee` | `repayment_capacity` | `residential_zone`.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/ScoringModelId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreScoringRuleRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Règle ajoutée'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function storeScoringRule(StoreScoringRuleRequest $request, ScoringModel $scoringModel): JsonResponse
    {
        $rule = $this->scoringAdmin->addRule($scoringModel, $request->validated(), $request->user());

        return response()->json([
            'message' => 'La règle a bien été ajoutée au modèle.',
            'scoring_rule' => $rule,
        ], 201);
    }

    #[OA\Get(
        path: '/api/admin/audit-logs',
        operationId: 'adminAuditLogs',
        tags: ['Administration'],
        summary: '[Lister] Le journal d’audit',
        description: '**Rôles :** Admin (`admin`).',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Journaux paginés'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function auditLogs(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $logs = AuditLog::query()
            ->with('user.role')
            ->latest()
            ->orderByDesc('id')
            ->paginate(30);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
