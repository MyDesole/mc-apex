<?php

namespace App\Http\Controllers;

use App\Models\Clan;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    public function top(): JsonResponse
    {
        $players = User::query()
            ->with('clanMember.clan:id,name,tag,banner_color')   // ← добавь
            ->orderByDesc('tier_score')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'avatar_url' => $user->avatar_url,
                    'tier' => $user->tier,
                    'tier_score' => $user->tier_score,
                    'clan_tag' => $user->clan_tag,
                    'clan_color' => $user->clan_color,
                ];
            });

        $clans = Clan::query()
            ->with('leader:id,username,avatar')
            ->orderByDesc('power')
            ->limit(10)
            ->get()
            ->map(function ($clan) {
                return [
                    'id' => $clan->id,
                    'name' => $clan->name,
                    'tag' => $clan->tag,
                    'avatar' => $clan->avatar,
                    'avatar_url' => $clan->avatar_url,   // ← добавь это
                    'cover_url' => $clan->cover_url,     // ← и это, если нужно
                    'banner_color' => $clan->banner_color,
                    'power' => $clan->power,
                    'wins' => $clan->wins,
                    'losses' => $clan->losses,
                    'is_highlighted' => $clan->is_highlighted,
                    'members_count' => $clan->members()->count(),
                    'leader' => $clan->leader,
                ];
            });

        return response()->json([
            'players' => $players,
            'clans' => $clans,
        ]);
    }
}
