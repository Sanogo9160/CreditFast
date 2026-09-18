<?php

namespace App\Http\Controllers\Api;

use App\Enums\KycStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ClientLoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\StaffLoginRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Services\UserPasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(private UserPasswordService $passwords) {}

    #[OA\Post(
        path: '/api/auth/register',
        operationId: 'authRegisterClient',
        tags: ['Authentification client'],
        summary: '[Créer] Un compte client',
        description: '**Public — clients uniquement.** Inscription par **numéro de téléphone unique** et mot de passe. L’e-mail est facultatif. Le rôle est toujours `client`.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ClientRegisterRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Compte créé', content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenResponse')),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $role = Role::where('name', RoleName::Client->value)->firstOrFail();

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'],
            'password' => $validated['password'],
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        Client::create([
            'user_id' => $user->id,
            'client_number' => 'CLI-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'kyc_status' => KycStatus::Pending,
        ]);

        return $this->sessionResponse(
            $user->load('role'),
            'Bienvenue. Votre compte a été créé avec succès.',
            201
        );
    }

    #[OA\Post(
        path: '/api/auth/client/login',
        operationId: 'authClientLogin',
        tags: ['Authentification client'],
        summary: 'Connexion client',
        description: '**Public — clients uniquement.** Identifiants : **numéro de téléphone** + mot de passe. Un compte interne ne peut pas se connecter ici.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ClientLoginRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Session ouverte', content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenResponse')),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function clientLogin(ClientLoginRequest $request): JsonResponse
    {
        $user = User::query()->where('phone', $request->validated('phone'))->first();

        return $this->authenticate(
            $user,
            $request->validated('password'),
            'phone',
            'Le numéro de téléphone ou le mot de passe ne correspond pas. Vous pouvez réessayer.',
            mustBeStaff: false,
        );
    }

    #[OA\Post(
        path: '/api/auth/staff/login',
        operationId: 'authStaffLogin',
        tags: ['Authentification interne'],
        summary: 'Connexion interne',
        description: '**Public — comptes internes uniquement** (admin, chargé, analyste, comité). Identifiants : **e-mail professionnel** + mot de passe. Un client ne peut pas se connecter ici.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StaffLoginRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Session ouverte', content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenResponse')),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function staffLogin(StaffLoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        return $this->authenticate(
            $user,
            $request->validated('password'),
            'email',
            'L’adresse e-mail ou le mot de passe ne correspond pas. Vous pouvez réessayer.',
            mustBeStaff: true,
        );
    }

    #[OA\Post(
        path: '/api/auth/logout',
        operationId: 'authLogout',
        tags: ['Session'],
        summary: 'Déconnexion',
        description: '**Rôles :** utilisateur authentifié (client ou interne).',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Token révoqué', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Vous avez été déconnecté. À bientôt.',
        ]);
    }

    #[OA\Get(
        path: '/api/auth/me',
        operationId: 'authMe',
        tags: ['Session'],
        summary: '[Lire] Le profil de session',
        description: '**Rôles :** utilisateur authentifié (client ou interne).',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Utilisateur courant',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load(['role', 'client'])),
        ]);
    }

    #[OA\Put(
        path: '/api/auth/password',
        operationId: 'authUpdatePassword',
        tags: ['Session'],
        summary: '[Modifier] Son mot de passe',
        description: '**Rôles :** utilisateur authentifié (client ou interne). L’ancien mot de passe est obligatoire. Les autres sessions sont révoquées.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdatePasswordRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Mot de passe mis à jour', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $this->passwords->change(
            $request->user(),
            $request->validated('password'),
            $request->user()->currentAccessToken(),
        );

        return response()->json([
            'message' => 'Votre mot de passe a bien été modifié.',
        ]);
    }

    private function authenticate(
        ?User $user,
        string $password,
        string $credentialKey,
        string $invalidMessage,
        bool $mustBeStaff,
    ): JsonResponse {
        $roleMatches = $user !== null && ($mustBeStaff ? $user->isStaff() : $user->hasRole(RoleName::Client));

        if (! $user || ! $roleMatches || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                $credentialKey => [$invalidMessage],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                $credentialKey => ['Ce compte n’est pas actif pour le moment. Merci de contacter votre agence si besoin.'],
            ]);
        }

        return $this->sessionResponse($user->load('role'), 'Vous êtes maintenant connecté.');
    }

    private function sessionResponse(User $user, string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'token' => $user->createToken('auth_token')->plainTextToken,
            'user' => new UserResource($user),
        ], $status);
    }
}
