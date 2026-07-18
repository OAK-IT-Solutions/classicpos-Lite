<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/notifications")]
class NotificationController extends Controller
{
    #[OA\Get(path: "/notifications", tags: ["Notifications"], summary: "List user notifications", responses: [new OA\Response(response: 200, description: "Paginated notifications")])]
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->paginate($request->input('per_page', 20));

        return response()->json($notifications);
    }

    #[OA\Get(path: "/notifications/unread-count", tags: ["Notifications"], summary: "Get unread notification count", responses: [new OA\Response(response: 200, description: "Unread count")])]
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    #[OA\Post(path: "/notifications/{id}/read", tags: ["Notifications"], summary: "Mark notification as read", responses: [new OA\Response(response: 200, description: "Success")])]
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    #[OA\Post(path: "/notifications/read-all", tags: ["Notifications"], summary: "Mark all notifications as read", responses: [new OA\Response(response: 200, description: "Success")])]
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
