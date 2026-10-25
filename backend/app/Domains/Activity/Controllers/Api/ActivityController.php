<?php

namespace App\Domains\Activity\Controllers\Api;

use App\Domains\Activity\Resources\ActivityResource;
use App\Domains\Activity\Services\ActivityFeed;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Лента «Сейчас на APEX». Новые события приходят по каналу activity,
 * этот запрос отдаёт начальный список и «показать ещё».
 */
class ActivityController extends Controller
{
    public function __construct(
        private readonly ActivityFeed $feed,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:' . ActivityFeed::MAX_LIMIT],
            'before' => ['nullable', 'integer', 'min:1'],
        ]);

        $items = $this->feed->latest(
            (int) ($validated['limit'] ?? ActivityFeed::DEFAULT_LIMIT),
            isset($validated['before']) ? (int) $validated['before'] : null,
        );

        return response()->json([
            'data' => ActivityResource::collection($items)->resolve(),
        ]);
    }
}
