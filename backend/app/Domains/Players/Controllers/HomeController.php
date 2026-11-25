<?php

namespace App\Domains\Players\Controllers;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Resources\ClanCardResource;
use App\Domains\Core\Models\SiteSetting;
use App\Domains\News\Models\News;
use App\Domains\Players\Resources\UserCardResource;
use App\Domains\Players\Services\HomeDashboardService;
use App\Domains\Tournaments\Models\Tournament;
use App\Domains\Tournaments\Models\TournamentMatch;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function top(): JsonResponse
    {
        $players = User::query()
            ->excludeStaff()
            ->with('clanMember.clan:id,name,tag,banner_color')
            ->orderByDesc('rating_points')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'avatar_url' => $user->avatar_url,
                    'tier' => $user->publicTier(),
                    'display_rank' => $user->display_rank,
                    'tier_score' => $user->tier_score,
                    'rating_points' => $user->rating_points,
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

    /** Панель вошедшего игрока: кулдауны, питомцы, надетое. */
    public function dashboard(Request $request, HomeDashboardService $dashboard): JsonResponse
    {
        return response()->json($dashboard->for($request->user()));
    }

    public function index(): JsonResponse
    {
        // настройки главной
        $hero = SiteSetting::group('hero');
        $socials = SiteSetting::group('socials');
        $footer = SiteSetting::group('footer');
        $stats = SiteSetting::group('stats');

        // Топ игроков. Отдаём через ресурс: ручной map терял поля
        // (рамку, эффект профиля, статус), которые читает фронтенд.
        $players = UserCardResource::collection(
            User::query()
                ->excludeStaff()   // персонал в топ не попадает, медийка участвует
                ->with('clanMember.clan:id,tag,banner_color')
                ->orderByDesc('rating_points')
                ->limit(10)
                ->get()
        )->resolve();

        // Топ кланов. Через ресурс — он считает подсветку с учётом срока
        // и отдаёт цвет с эффектом; members_count берётся одним запросом.
        $clans = ClanCardResource::collection(
            Clan::query()
                ->where('is_banned', false)
                ->with('leader:id,username,avatar')
                ->withCount('members')
                ->orderByDesc('power')
                ->limit(10)
                ->get()
        )->resolve();

        // новости
        $news = News::where('is_published', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->limit(6)
            ->get(['id', 'title', 'slug', 'excerpt', 'cover', 'type', 'is_pinned', 'published_at', 'created_at']);

        // Общая статистика: четыре count(*) по большим таблицам на каждый
        // заход на главную не нужны — счётчики живут в кэше пять минут
        $statsData = Cache::remember('home:stats', 300, fn () => [
            'players' => User::count(),
            'clans' => Clan::where('is_banned', false)->count(),
            'tournaments' => Tournament::whereIn('status', ['ongoing', 'registration'])->count(),
            'matches' => TournamentMatch::where('status', 'completed')->count(),
        ]);

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
