<?php

namespace App\Domains\Seasons\Services;

use App\Domains\Seasons\Models\Season;
use App\Domains\Seasons\Models\SeasonResult;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Сезоны: текущий сезон и закрытие с сохранением итогов.
 *
 * Закрытие не сбрасывает тиры — оно лишь фиксирует место каждого
 * игрока в рейтинге, чтобы в профиле осталась история по сезонам.
 */
class SeasonService
{
    /** Текущий сезон. Первый создаётся сам при первом обращении. */
    public function current(): Season
    {
        return Season::whereNull('ends_at')->latest('number')->first()
            ?? Season::create(['number' => 1, 'name' => 'Сезон 1', 'starts_at' => now()]);
    }

    /**
     * Закрыть текущий сезон и открыть следующий.
     *
     * @return array{closed: Season, next: Season, players: int}
     */
    public function close(?string $nextName = null): array
    {
        return DB::transaction(function () use ($nextName) {
            $season = $this->current();

            $players = User::query()
                ->excludeStaff()
                ->where('rating_points', '>', 0)
                ->orderByDesc('rating_points')
                ->orderByDesc('id')
                ->get(['id', 'tier', 'tier_score', 'rating_points']);

            $now = now();
            $rows = $players->values()->map(fn (User $user, int $i) => [
                'season_id' => $season->id,
                'user_id' => $user->id,
                'rank' => $i + 1,
                'tier' => $user->tier ?? 'E',
                'tier_score' => (int) $user->tier_score,
                'rating_points' => (int) $user->rating_points,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($rows->chunk(500) as $chunk) {
                SeasonResult::insert($chunk->all());
            }

            $season->update(['ends_at' => $now]);

            $number = $season->number + 1;
            $next = Season::create([
                'number' => $number,
                'name' => $nextName ?: "Сезон {$number}",
                'starts_at' => $now,
            ]);

            return ['closed' => $season->fresh(), 'next' => $next, 'players' => $rows->count()];
        });
    }
}
