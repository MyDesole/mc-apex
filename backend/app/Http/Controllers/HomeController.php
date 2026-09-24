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

    public function index(): JsonResponse
    {
        // настройки главной
        $hero = \App\Models\SiteSetting::group('hero');
        $socials = \App\Models\SiteSetting::group('socials');
        $footer = \App\Models\SiteSetting::group('footer');
        $stats = \App\Models\SiteSetting::group('stats');

        // топ игроков
        $players = \App\Models\User::query()
            ->with('clanMember.clan:id,tag,banner_color')
            ->orderByDesc('tier_score')
            ->limit(10)
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'username' => $u->username,
                'avatar_url' => $u->avatar_url,
                'tier' => $u->tier,
                'tier_score' => $u->tier_score,
                'clan_tag' => $u->clan_tag,
                'clan_color' => $u->clan_color,
            ]);

        // топ кланов
        $clans = \App\Models\Clan::query()
            ->where('is_banned', false)
            ->with('leader:id,username,avatar')
            ->orderByDesc('power')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'tag' => $c->tag,
                'avatar_url' => $c->avatar_url,
                'banner_color' => $c->banner_color,
                'power' => $c->power,
                'wins' => $c->wins,
                'losses' => $c->losses,
                'members_count' => $c->members()->count(),
                'leader' => $c->leader,
            ]);

        // новости
        $news = \App\Models\News::where('is_published', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->with('author:id,username,avatar')
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->limit(6)
            ->get();

        // общая статистика
        $statsData = [
            'players' => \App\Models\User::count(),
            'clans' => \App\Models\Clan::where('is_banned', false)->count(),
            'tournaments' => \App\Models\Tournament::whereIn('status', ['ongoing', 'registration'])->count(),
            'matches' => \App\Models\TournamentMatch::where('status', 'completed')->count(),
        ];

        return response()->json([
            'hero' => $hero,
            'socials' => $socials,
            'footer' => $footer,
            'stats' => $stats,
            'stats_data' => $statsData,
            'players' => $players,
            'clans' => $clans,
            'news' => $news,
        ]);
    }
}
