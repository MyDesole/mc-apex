<?php

namespace App\Domains\Chat\Controllers\Api;

use App\Domains\Chat\Services\PresenceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Кто сейчас на сайте.
 *
 * Reverb сообщает о входе и выходе мгновенно, но если он недоступен,
 * статус всё равно нужен: запасной источник — время последней
 * активности, которое отмечает middleware.
 */
class PresenceController extends Controller
{
    public function __construct(private readonly PresenceService $presence)
    {
    }

    /**
     * Кто онлайн.
     *
     * Без параметров отдаёт весь список — так страница сообщений сразу
     * знает, кого подсветить. С параметром user_ids отвечает только про
     * запрошенных: удобно при периодическом опросе.
     */
    public function index(Request $request): JsonResponse
    {
        $requested = $request->query('user_ids');

        if (is_string($requested) && $requested !== '') {
            $ids = array_filter(array_map('intval', explode(',', $requested)));

            return response()->json([
                'online' => $this->presence->onlineAmong($ids),
            ]);
        }

        return response()->json([
            'online' => $this->presence->onlineUserIds(),
        ]);
    }

    /** Отметить, что игрок зашёл на сайт. */
    public function online(Request $request): JsonResponse
    {
        $this->presence->markOnline((int) $request->user()->id);

        return response()->json(['online' => $this->presence->onlineUserIds()]);
    }

    /** Отметить, что игрок закрыл сайт. */
    public function offline(Request $request): JsonResponse
    {
        $this->presence->markOffline((int) $request->user()->id);

        return response()->json(['ok' => true]);
    }
}
