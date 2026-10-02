<?php

namespace App\Domains\Players\Controllers;

use App\Domains\Clan\Resources\ClanCardResource;
use App\Domains\Players\Resources\UserCardResource;
use App\Domains\Clan\Models\Clan;
use App\Domains\Users\Models\User;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

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
