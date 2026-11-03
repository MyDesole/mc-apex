<?php

namespace App\Domains\Minecraft\Controllers\Api;

use App\Domains\Minecraft\Models\MinecraftGrant;
use App\Domains\Minecraft\Services\MinecraftDuelService;
use App\Domains\Minecraft\Services\MinecraftGrantService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Приём данных от плагина сервера: итоги дуэлей, очередь выдач, онлайн.
 * Доступ только по ключу сервера (middleware minecraft.server).
 */
class MinecraftIntegrationController extends Controller
{
    public const ONLINE_KEY = 'minecraft:online';

    public function __construct(
        private readonly MinecraftDuelService $duels,
        private readonly MinecraftGrantService $grants,
    ) {
    }

    public function storeDuel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'match_key' => ['required', 'string', 'max:64'],
            'kit' => ['required', 'string', 'max:48'],
            'winner' => ['required', 'string', 'max:32'],
            'loser' => ['required', 'string', 'max:32', 'different:winner'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'ranked' => ['nullable', 'boolean'],
        ]);

        $duel = $this->duels->record($data);

        return response()->json(['ok' => true, 'id' => $duel->id, 'created' => $duel->wasRecentlyCreated]);
    }

    public function grants(): JsonResponse
    {
        return response()->json([
            'grants' => $this->grants->pending()->map(fn (MinecraftGrant $g) => [
                'id' => $g->id,
                'player' => $g->minecraft_username,
                'commands' => $g->commands,
            ])->all(),
        ]);
    }

    public function ackGrant(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'ok' => ['required', 'boolean'],
            'error' => ['nullable', 'string', 'max:250'],
        ]);

        $grant = MinecraftGrant::find($id);

        if (! $grant) {
            return response()->json(['message' => 'Выдача не найдена.'], 404);
        }

        $grant = $this->grants->acknowledge($grant, (bool) $data['ok'], $data['error'] ?? null);

        return response()->json(['ok' => true, 'status' => $grant->status]);
    }

    /** Кто сейчас на сервере: плагин присылает список раз в полминуты. */
    public function presence(Request $request): JsonResponse
    {
        $data = $request->validate([
            'players' => ['present', 'array', 'max:500'],
            'players.*' => ['string', 'max:32'],
        ]);

        $nicks = collect($data['players'])->map(fn ($n) => mb_strtolower($n))->unique()->values()->all();

        Cache::put(self::ONLINE_KEY, $nicks, now()->addSeconds(120));

        return response()->json(['ok' => true, 'count' => count($nicks)]);
    }
}
