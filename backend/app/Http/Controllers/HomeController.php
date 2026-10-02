<?php

namespace App\Http\Controllers;

use App\Models\Clan;
use App\Models\User;
use App\Http\Resources\ClanCardResource;
use App\Http\Resources\UserCardResource;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    public function top(): JsonResponse
    {
        $players = User::query()
            ->whereNotIn('role', ['admin', 'moderator', 'tester'])
            ->with('clanMember.clan:id,name,tag,banner_color')
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
            ->where('is_banned', false)
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
                    'avatar_url' => $clan->avatar_url,
                    'cover_url' => $clan->cover_url,
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

        // Топ игроков. Отдаём через ресурс: ручной map терял поля
        // (рамку, эффект профиля, статус), которые читает фронтенд.
        $players = \App\Http\Resources\UserCardResource::collection(
            \App\Models\User::query()
                ->excludeStaff()   // персонал в топ не попадает, медийка участвует
                ->with('clanMember.clan:id,tag,banner_color')
                ->orderByDesc('tier_score')
                ->limit(10)
                ->get()
        )->resolve();

        // Топ кланов. Через ресурс — он считает подсветку с учётом срока
        // и отдаёт цвет с эффектом; members_count берётся одним запросом.
        $clans = \App\Http\Resources\ClanCardResource::collection(
            \App\Models\Clan::query()
                ->where('is_banned', false)
                ->with('leader:id,username,avatar')
                ->withCount('members')
                ->orderByDesc('power')
                ->limit(10)
                ->get()
        )->resolve();

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
