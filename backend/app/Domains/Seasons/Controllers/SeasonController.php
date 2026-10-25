<?php

namespace App\Domains\Seasons\Controllers;

use App\Domains\Seasons\Models\Season;
use App\Domains\Seasons\Models\SeasonResult;
use App\Domains\Seasons\Services\SeasonService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SeasonController extends Controller
{
    private const TOP = 10;

    public function __construct(private SeasonService $seasons) {}

    /** Текущий сезон и прошедшие — с тройкой лучших. */
    public function index(): JsonResponse
    {
        $current = $this->seasons->current();

        $past = Season::whereNotNull('ends_at')
            ->latest('number')
            ->get()
            ->map(fn (Season $season) => [
                ...$this->present($season),
                'top' => $this->top($season, 3),
            ]);

        return response()->json([
            'current' => $this->present($current),
            'past' => $past,
        ]);
    }

    /** Итоговая таблица закрытого сезона. */
    public function show(Season $season): JsonResponse
    {
        return response()->json([
            'season' => $this->present($season),
            'top' => $this->top($season, self::TOP),
        ]);
    }

    /** Места игрока по сезонам — для профиля. */
    public function player(User $user): JsonResponse
    {
        $results = SeasonResult::where('user_id', $user->id)
            ->with(['season' => fn ($q) => $q->withCount('results')])
            ->get()
            ->sortByDesc(fn (SeasonResult $r) => $r->season->number)
            ->values()
            ->map(fn (SeasonResult $r) => [
                'season' => $this->present($r->season),
                'rank' => $r->rank,
                'tier' => $r->tier,
                'tier_score' => $r->tier_score,
                'players' => $r->season->results_count,
            ]);

        return response()->json(['results' => $results]);
    }

    private function present(Season $season): array
    {
        return [
            'id' => $season->id,
            'number' => $season->number,
            'name' => $season->name,
            'starts_at' => $season->starts_at?->toIso8601String(),
            'ends_at' => $season->ends_at?->toIso8601String(),
            'active' => $season->isActive(),
        ];
    }

    private function top(Season $season, int $limit): array
    {
        return $season->results()
            ->with('user:id,username,avatar')
            ->orderBy('rank')
            ->limit($limit)
            ->get()
            ->map(fn (SeasonResult $r) => [
                'rank' => $r->rank,
                'tier' => $r->tier,
                'tier_score' => $r->tier_score,
                'user' => $r->user ? ['id' => $r->user->id, 'username' => $r->user->username, 'avatar_url' => $r->user->avatar_url] : null,
            ])
            ->all();
    }
}
