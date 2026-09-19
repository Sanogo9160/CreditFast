<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\StoreProfilePhotoRequest;
use App\Http\Requests\Profile\UpdateProfilePhotoRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ProfilePhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfilePhotoController extends Controller
{
    public function __construct(protected ProfilePhotoService $photos) {}

    #[OA\Get(
        path: '/api/profile-photo',
        operationId: 'profilePhotoShow',
        tags: ['Photo de profil'],
        summary: '[Lire] Sa photo de profil',
        description: '**Rôles :** utilisateur authentifié. Métadonnées + URL de téléchargement.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'État de la photo', content: new OA\JsonContent(ref: '#/components/schemas/ProfilePhotoEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('role');

        return $this->photoResponse($user, $user->profile_photo_path
            ? 'Photo de profil disponible.'
            : 'Aucune photo de profil n’est enregistrée pour le moment.');
    }

    #[OA\Post(
        path: '/api/profile-photo',
        operationId: 'profilePhotoStore',
        tags: ['Photo de profil'],
        summary: '[Créer] Une photo de profil',
        description: '**Rôles :** utilisateur authentifié. Corps **multipart/form-data**, champ `photo`. Impossible s’il existe déjà une photo (utiliser PUT).',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(ref: '#/components/requestBodies/UploadProfilePhoto'),
        responses: [
            new OA\Response(response: 201, description: 'Photo enregistrée', content: new OA\JsonContent(ref: '#/components/schemas/ProfilePhotoEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 409, description: 'Une photo existe déjà', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function store(StoreProfilePhotoRequest $request): JsonResponse
    {
        $user = $this->photos->store($request->user(), $request->file('photo'));

        return $this->photoResponse($user, 'Votre photo de profil a bien été enregistrée.', 201);
    }

    #[OA\Put(
        path: '/api/profile-photo',
        operationId: 'profilePhotoUpdate',
        tags: ['Photo de profil'],
        summary: '[Modifier] Sa photo de profil',
        description: '**Rôles :** utilisateur authentifié. Remplace la photo existante. Corps **multipart/form-data**, champ `photo`.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(ref: '#/components/requestBodies/UploadProfilePhoto'),
        responses: [
            new OA\Response(response: 200, description: 'Photo remplacée', content: new OA\JsonContent(ref: '#/components/schemas/ProfilePhotoEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function update(UpdateProfilePhotoRequest $request): JsonResponse
    {
        $user = $this->photos->update($request->user(), $request->file('photo'));

        return $this->photoResponse($user, 'Votre photo de profil a bien été mise à jour.');
    }

    #[OA\Delete(
        path: '/api/profile-photo',
        operationId: 'profilePhotoDestroy',
        tags: ['Photo de profil'],
        summary: '[Supprimer] Sa photo de profil',
        description: '**Rôles :** utilisateur authentifié.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Photo retirée', content: new OA\JsonContent(ref: '#/components/schemas/ProfilePhotoEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function destroy(Request $request): JsonResponse
    {
        $user = $this->photos->delete($request->user());

        return $this->photoResponse($user, 'Votre photo de profil a bien été retirée.');
    }

    #[OA\Get(
        path: '/api/users/{user}/photo/file',
        operationId: 'usersPhotoFile',
        tags: ['Photo de profil'],
        summary: '[Lire] Télécharger la photo d’un utilisateur',
        description: '**Rôles :** le propriétaire, ou un membre du staff.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/UserId')],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Fichier image',
                content: new OA\MediaType(
                    mediaType: 'application/octet-stream',
                    schema: new OA\Schema(type: 'string', format: 'binary')
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function file(User $user): StreamedResponse
    {
        $this->authorize('viewProfilePhoto', $user);

        abort_unless(
            filled($user->profile_photo_path) && Storage::exists($user->profile_photo_path),
            404,
            'Aucune photo de profil n’est disponible pour le moment.'
        );

        return Storage::download($user->profile_photo_path, 'photo-profil');
    }

    protected function photoResponse(User $user, string $message, int $status = 200): JsonResponse
    {
        $user->loadMissing('role');

        return response()->json([
            'message' => $message,
            'has_photo' => filled($user->profile_photo_path),
            'profile_photo_url' => $user->profile_photo_path ? route('users.photo.file', $user, true) : null,
            'user' => new UserResource($user),
        ], $status);
    }
}
