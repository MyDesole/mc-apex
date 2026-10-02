<?php

namespace App\Domains\Notifications\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()->latest()->paginate(20);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        // Возвращаем актуальный счётчик, чтобы фронт не перезапрашивал список
        return response()->json([
            'ok' => true,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        // Прямой запрос вместо $user->unreadNotifications->markAsRead():
        // ленивая коллекция могла быть уже загружена и содержать устаревшие данные,
        // из-за чего часть уведомлений оставалась непрочитанной
        // и бейдж не пропадал до перезагрузки страницы.
        $updated = $user->unreadNotifications()->update(['read_at' => now()]);

        // Сбрасываем загруженные связи, иначе счётчик вернёт старое значение
        $user->unsetRelation('unreadNotifications');
        $user->unsetRelation('notifications');

        return response()->json([
            'ok' => true,
            'unread_count' => 0,
            'marked' => $updated,
        ]);
    }
}
