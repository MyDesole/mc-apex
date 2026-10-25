<?php

namespace App\Domains\Players\Controllers\Api;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanWar;
use App\Domains\Players\Models\PlayerAspectPvp;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Публичная статистика проекта: распределение по тирам, средние аспекты,
 * динамика тестов. Считается раз в 10 минут — данные меняются медленно.
 */
class StatsController extends Controller
{
    private const TTL = 600;

    private const MONTHS = 6;

    private const ASPECTS = ['block_placing', 'rotka', 'movement', 'aim', 'game_sense'];

    public function index(): JsonResponse
    {
        return response()->json(Cache::remember('stats:public', self::TTL, fn () => [
            'totals' => $this->totals(),
            'tiers' => $this->tierDistribution(),
            'aspects' => $this->averageAspects(),
            'tests_by_month' => $this->testsByMonth(),
            'generated_at' => now()->toIso8601String(),
        ]));
    }

    private function totals(): array
    {
        return [
            'players' => User::count(),
            'ranked' => User::query()->excludeStaff()->where('rating_points', '>', 0)->count(),
            'tests' => TierTest::where('status', 'completed')->count(),
            'clans' => Clan::where('is_banned', false)->count(),
            'clan_wars' => ClanWar::where('status', 'completed')->count(),
        ];
    }

    /** Сколько игроков в каждом тире — среди тех, кто есть в рейтинге. */
    private function tierDistribution(): array
    {
        $counts = User::query()
            ->excludeStaff()
            ->where('tier_score', '>', 0)
            ->selectRaw('tier, COUNT(*) as total')
            ->groupBy('tier')
            ->pluck('total', 'tier');

        return collect(['S', 'A', 'B', 'C', 'D', 'E'])
            ->map(fn ($tier) => ['tier' => $tier, 'players' => (int) ($counts[$tier] ?? 0)])
            ->all();
    }

    /** Средние PvP-аспекты среди прошедших тест (нулевые записи не считаем). */
    private function averageAspects(): array
    {
        $sum = implode(' + ', self::ASPECTS);

        $row = PlayerAspectPvp::query()
            ->whereRaw("({$sum}) > 0")
            ->selectRaw(implode(', ', array_map(fn ($a) => "AVG({$a}) as {$a}", self::ASPECTS)))
            ->first();

        return collect(self::ASPECTS)
            ->map(fn ($key) => ['key' => $key, 'average' => round((float) ($row?->{$key} ?? 0), 1)])
            ->all();
    }

    /**
     * Завершённые тесты по месяцам за полгода. Группируем в PHP:
     * функции дат в SQLite и MySQL разные.
     */
    private function testsByMonth(): array
    {
        $from = now()->startOfMonth()->subMonths(self::MONTHS - 1);

        $byMonth = TierTest::query()
            ->where('status', 'completed')
            ->where('completed_at', '>=', $from)
            ->pluck('completed_at')
            ->countBy(fn ($date) => Carbon::parse($date)->format('Y-m'));

        return collect(range(0, self::MONTHS - 1))
            ->map(function ($i) use ($from, $byMonth) {
                $month = $from->copy()->addMonths($i)->format('Y-m');

                return ['month' => $month, 'tests' => (int) ($byMonth[$month] ?? 0)];
            })
            ->all();
    }
}
