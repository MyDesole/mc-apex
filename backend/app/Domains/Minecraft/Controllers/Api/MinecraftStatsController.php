<?php

namespace App\Domains\Minecraft\Controllers\Api;

use App\Domains\Minecraft\Models\MinecraftDuel;
use App\Domains\Minecraft\Services\MinecraftDuelService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Публичные данные с сервера для сайта: история дуэлей, топ кланов, онлайн. */
class MinecraftStatsController extends Controller
{
    public function __construct(private readonly MinecraftDuelService $duels) {}

    public function playerDuels(Request $request, User $user): JsonResponse
    {
        $request->validate(['kit' => ['nullable', 'string', 'max:48'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $kit = $request->query('kit');
        $page = $this->duels->history($user, is_string($kit) && $kit !== '' ? $kit : null);

        return response()->json([
            'player' => [
                'id' => $user->id,
                'username' => $user->username,
                'minecraft_username' => $user->minecraft_username,
                'online' => $this->isOnline($user),
            ],
            'stats' => $this->duels->stats($user),
            'server_stats' => $this->duels->serverStats($user),
            'duels' => collect($page->items())->map(fn (MinecraftDuel $d) => [
                'id' => $d->id,
                'kit' => $d->kit,
                'won' => $d->winner_user_id === $user->id,
                'opponent' => $d->winner_user_id === $user->id ? $d->loser_name : $d->winner_name,
                'opponent_user_id' => $d->winner_user_id === $user->id ? $d->loser_user_id : $d->winner_user_id,
                'duration_seconds' => $d->duration_seconds,
                'ranked' => $d->ranked,
                'played_at' => $d->played_at?->toIso8601String(),
            ])->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $data = $request->validate(['kit' => ['nullable', 'string', 'max:48'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $page = MinecraftDuel::query()->when($data['kit'] ?? null, fn ($q, $kit) => $q->where('kit', mb_strtolower($kit)))
            ->orderByDesc('played_at')->orderByDesc('id')->paginate(20);

        return response()->json([
            'duels' => collect($page->items())->map(fn (MinecraftDuel $d) => [
                'id' => $d->id, 'kit' => $d->kit, 'winner' => $d->winner_name, 'loser' => $d->loser_name,
                'winner_user_id' => $d->winner_user_id, 'loser_user_id' => $d->loser_user_id,
                'duration_seconds' => $d->duration_seconds, 'ranked' => $d->ranked,
                'played_at' => $d->played_at?->toIso8601String(),
            ])->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function clanBoard(): JsonResponse
    {
        return response()->json(['clans' => $this->duels->clanLeaderboard()]);
    }

    /** Игроки сайта, которые сейчас на сервере. */
    public function online(): JsonResponse
    {
        $nicks = Cache::get(MinecraftIntegrationController::ONLINE_KEY, []);

        $users = $nicks === [] ? collect() : User::query()
            ->whereIn(DB::raw('lower(minecraft_username)'), $nicks)
            ->get(['id', 'username', 'minecraft_username']);

        return response()->json([
            'count' => count($nicks),
            'players' => $users->map(fn (User $u) => ['id' => $u->id, 'username' => $u->username, 'minecraft_username' => $u->minecraft_username])->all(),
        ]);
    }

    private function isOnline(User $user): bool
    {
        return $user->minecraft_username
            && in_array(mb_strtolower($user->minecraft_username), Cache::get(MinecraftIntegrationController::ONLINE_KEY, []), true);
    }
}
