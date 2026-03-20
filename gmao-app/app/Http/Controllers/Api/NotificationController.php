<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Liste des notifications de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $notifications = Notification::forUser($user)
            ->orderByDesc('created_at')
            ->limit($request->limit ?? 50)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'icon' => $notification->icon,
                    'color' => $notification->color,
                    'link' => $notification->link,
                    'read_at' => $notification->read_at,
                    'is_read' => $notification->isRead(),
                    'created_at' => $notification->created_at,
                    'time_ago' => $notification->created_at->diffForHumans(),
                ];
            });

        return response()->json($notifications);
    }

    /**
     * Compteur de notifications non lues
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $count = Notification::forUser($user)
            ->unread()
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Marquer une notification comme lue
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();
        
        // Vérifier que la notification appartient à l'utilisateur
        if ($notification->site_id !== $user->current_site_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $notification->markAsRead();

        return response()->json(['message' => 'Notification marquée comme lue']);
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        
        Notification::forUser($user)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications marquées comme lues']);
    }

    /**
     * Supprimer une notification
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();
        
        if ($notification->site_id !== $user->current_site_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $notification->delete();

        return response()->json(['message' => 'Notification supprimée']);
    }

    /**
     * Supprimer toutes les notifications lues
     */
    public function clearRead(Request $request): JsonResponse
    {
        $user = $request->user();
        
        Notification::forUser($user)
            ->whereNotNull('read_at')
            ->delete();

        return response()->json(['message' => 'Notifications lues supprimées']);
    }

    /**
     * Générer les notifications (appelé par cron ou manuellement)
     */
    public function generate(Request $request): JsonResponse
    {
        $user = $request->user();
        $site = $user->currentSite;

        if (!$site) {
            return response()->json(['message' => 'Aucun site sélectionné'], 400);
        }

        $results = $this->notificationService->generateForSite($site);

        return response()->json([
            'message' => 'Notifications générées',
            'generated' => $results,
        ]);
    }
}
