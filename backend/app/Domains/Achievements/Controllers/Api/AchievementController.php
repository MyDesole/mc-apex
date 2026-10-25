<?php

namespace App\Domains\Achievements\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Achievements\Models\Achievement;
use App\Domains\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AchievementController extends Controller
{
    /**
     * Все доступные ачивки + какие получены текущим юзером.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $all = Achievement::orderBy('points')->get();

        $earned = $user->achievements()
            ->get()
            ->keyBy('id');

        /*
         * Доля игроков с ачивкой — «редкость по факту». Считаем одним
         * сгруппированным запросом, а не отдельным на каждую ачивку.
         */
        $players = max(1, User::count());

        $holders = DB::table('user_achievements')
            ->select('achievement_id', DB::raw('COUNT(*) as holders'))
            ->groupBy('achievement_id')
            ->pluck('holders', 'achievement_id');

        $achievements = $all->map(function ($a) use ($earned, $holders, $players) {
            $isEarned = $earned->has($a->id);

            return [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'description' => $a->description,
                'icon' => $a->icon,
                'color' => $a->color,
                'rarity' => $a->rarity,
                'points' => $a->points,
                'earned' => $isEarned,
                'earned_at' => $isEarned ? $earned[$a->id]->pivot->earned_at : null,
                'holders_percent' => round(((int) ($holders[$a->id] ?? 0)) / $players * 100, 1),
            ];
        });

        return response()->json([
            'achievements' => $achievements,
            'earned_count' => $achievements->where('earned', true)->count(),
            'total_count' => $achievements->count(),
            'points' => $user->achievementPoints(),
        ]);
    }

    /**
     * Ачивки конкретного игрока (для чужого профиля).
     */
    public function user(Request $request, User $user): JsonResponse
    {
        $earned = $user->achievements()->orderByDesc('user_achievements.earned_at')->get();

        return response()->json([
            'achievements' => $earned->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'description' => $a->description,
                'icon' => $a->icon,
                'color' => $a->color,
                'rarity' => $a->rarity,
                'points' => $a->points,
                'earned_at' => $a->pivot->earned_at,
            ]),
            'points' => $user->achievementPoints(),
        ]);
    }
}
