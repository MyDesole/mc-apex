<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClanCardResource;
use App\Http\Resources\UserCardResource;
use App\Models\Clan;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Публичный топ игроков и кланов.
 *
 * Раньше оба списка собирались вручную, а число участников считалось
 * отдельным запросом на каждый клан (N+1).
 */
class TopController extends Controller
{
    private const LIMIT = 10;

    public function index(): JsonResponse
    {
        return response()->json([
            'players' => $this->players(),
            'clans' => $this->clans(),
        ]);
    }

    private function players(): array
    {
        $players = User::query()
            ->excludeStaff()
            ->with('clanMember.clan:id,name,tag,banner_color')
            ->orderByDesc('tier_score')
            ->limit(self::LIMIT)
            ->get();

        return UserCardResource::collection($players)->resolve();
    }

    private function clans(): array
    {
        $clans = Clan::query()
            ->where('is_banned', false)
            ->with('leader:id,username,avatar')
            ->withCount('members')
            ->orderByDesc('power')
            ->limit(self::LIMIT)
            ->get();

        // withCount кладёт members_count в атрибут модели — ресурс его подхватит
        return ClanCardResource::collection($clans)->resolve();
    }
}
