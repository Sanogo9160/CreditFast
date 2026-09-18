<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NotificationController extends Controller
{
    #[OA\Get(
        path: '/api/notifications',
        operationId: 'notificationsIndex',
        tags: ['Notifications'],
        summary: '[Lister] Ses notifications',
        description: '**Rôles :** utilisateur authentifié (tous rôles).',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste paginée',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Notification')),
                    new OA\Property(property: 'unread_count', type: 'integer'),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                ])
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->userNotifications()
            ->latest()
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'unread_count' => $request->user()->userNotifications()->where('is_read', false)->count(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/notifications/{notification}/read',
        operationId: 'notificationsMarkAsRead',
        tags: ['Notifications'],
        summary: '[Modifier] Marquer une notification comme lue',
        description: '**Rôles :** propriétaire de la notification.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/NotificationId')],
        responses: [
            new OA\Response(response: 200, description: 'Notification mise à jour'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function markAsRead(Notification $notification): JsonResponse
    {
        $this->authorize('update', $notification);

        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => 'Notification marquée comme lue.',
            'notification' => new NotificationResource($notification),
        ]);
    }

    #[OA\Get(
        path: '/api/notifications/{notification}',
        operationId: 'notificationsShow',
        tags: ['Notifications'],
        summary: '[Lire] Consulter une notification',
        description: '**Rôles :** propriétaire de la notification.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/NotificationId')],
        responses: [
            new OA\Response(response: 200, description: 'Notification'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function show(Notification $notification): JsonResponse
    {
        $this->authorize('view', $notification);

        return response()->json([
            'notification' => new NotificationResource($notification),
        ]);
    }

    #[OA\Delete(
        path: '/api/notifications/{notification}',
        operationId: 'notificationsDestroy',
        tags: ['Notifications'],
        summary: '[Supprimer] Une notification',
        description: '**Rôles :** propriétaire de la notification.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/NotificationId')],
        responses: [
            new OA\Response(response: 200, description: 'Notification retirée', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function destroy(Notification $notification): JsonResponse
    {
        $this->authorize('delete', $notification);

        $notification->delete();

        return response()->json([
            'message' => 'La notification a bien été retirée.',
        ]);
    }
}
