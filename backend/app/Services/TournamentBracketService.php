<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentMatch;

class TournamentBracketService
{
    /**
     * Генерирует single-elimination сетку для турнира.
     * Очищает старые матчи и создаёт новые по сидам.
     */
    public static function generateSingleElim(Tournament $tournament): void
    {
        $participants = $tournament->approvedParticipants()
            ->orderBy('seed')
            ->get();

        $count = $participants->count();
        if ($count < 2) {
            throw new \Exception('Нужно минимум 2 участника.');
        }

        // ближайшая степень двойки
        $bracketSize = 1;
        while ($bracketSize < $count) $bracketSize *= 2;

        // чистим старые
        $tournament->matches()->delete();

        $totalRounds = (int) log($bracketSize, 2);

        $matches = [];

        // первый раунд
        $firstRoundMatches = [];
        for ($i = 0; $i < $bracketSize / 2; $i++) {
            $p1 = $participants[$i * 2] ?? null;
            $p2 = $participants[$i * 2 + 1] ?? null;

            $matches[] = [
                'tournament_id' => $tournament->id,
                'round' => 1,
                'position' => $i,
                'bracket' => 'main',
                'participant1_id' => $p1?->id,
                'participant2_id' => $p2?->id,
                'status' => ($p1 && $p2) ? 'ready' : 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // остальные раунды — пока без участников
        for ($r = 2; $r <= $totalRounds; $r++) {
            $matchesInRound = (int) ($bracketSize / pow(2, $r));
            for ($i = 0; $i < $matchesInRound; $i++) {
                $matches[] = [
                    'tournament_id' => $tournament->id,
                    'round' => $r,
                    'position' => $i,
                    'bracket' => 'main',
                    'participant1_id' => null,
                    'participant2_id' => null,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // вставляем
        TournamentMatch::insert($matches);

        // связываем next_match_id
        self::linkNextMatches($tournament);
    }

    /**
     * Связывает матчи: победитель матча идёт в следующий.
     */
    public static function linkNextMatches(Tournament $tournament): void
    {
        $matches = $tournament->matches()->get();

        $byRound = $matches->groupBy('round');

        foreach ($byRound as $round => $roundMatches) {
            $nextRoundMatches = $byRound[$round + 1] ?? null;
            if (!$nextRoundMatches) continue;

            foreach ($roundMatches as $i => $match) {
                $nextPosition = (int) floor($i / 2);
                $nextMatch = $nextRoundMatches->firstWhere('position', $nextPosition);
                if (!$nextMatch) continue;

                $slot = ($i % 2 === 0) ? 'p1' : 'p2';

                $match->update([
                    'next_match_id' => $nextMatch->id,
                    'next_slot' => $slot,
                ]);
            }
        }
    }

    /**
     * Обновить сетку после победы в матче.
     */
    public static function advanceWinner(TournamentMatch $match): void
    {
        if (!$match->winner_id || !$match->next_match_id) return;

        $next = $match->nextMatch;
        if (!$next) return;

        $field = $match->next_slot === 'p2' ? 'participant2_id' : 'participant1_id';
        $next->update([$field => $match->winner_id]);

        // если оба участника есть — матч становится ready
        if ($next->participant1_id && $next->participant2_id) {
            $next->update(['status' => 'ready']);
        }
    }
}
