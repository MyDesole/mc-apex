<?php

namespace App\Domains\Tournaments\Services;

use App\Domains\Achievements\Services\AchievementService;
use App\Domains\Tournaments\Models\Tournament;
use App\Domains\Tournaments\Models\TournamentMatch;
use App\Domains\Tournaments\Models\TournamentParticipant;
use App\Domains\Tournaments\Models\TournamentWin;
use App\Domains\Tournaments\Notifications\TournamentNotification;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

class TournamentBracketService
{
    /**
     * Генерирует single-elimination сетку для турнира.
     *
     * Размер — ближайшая степень двойки. Сильнейшие посевы получают
     * «пропуск» (bye) и сразу проходят во второй раунд — так в первом
     * раунде нет пустых матчей, а сетка всегда доходит до финала.
     * Раньше при 5–7 участниках одиночки застревали, и финал был недостижим.
     *
     * Турнир переходит в статус ongoing: регистрация закрывается.
     */
    public static function generateSingleElim(Tournament $tournament): void
    {
        $played = $tournament->matches()->where('status', 'completed')->where('is_bye', false)->exists();

        if ($played) {
            throw new \Exception('Уже есть сыгранные матчи — пересоздание сетки сотрёт результаты.');
        }

        $participants = $tournament->approvedParticipants()
            ->orderByRaw('seed IS NULL')
            ->orderBy('seed')
            ->orderBy('id')
            ->get()
            ->values();

        $count = $participants->count();
        if ($count < 2) {
            throw new \Exception('Нужно минимум 2 одобренных участника.');
        }

        $bracketSize = 1;
        while ($bracketSize < $count) {
            $bracketSize *= 2;
        }

        $totalRounds = (int) log($bracketSize, 2);

        DB::transaction(function () use ($tournament, $participants, $bracketSize, $totalRounds) {
            $tournament->matches()->delete();

            $now = now();
            $rows = [];

            // Первый раунд: посев k играет с посевом (size-1-k). Участников
            // больше половины сетки, поэтому в каждой паре есть хотя бы один,
            // а «пустые» соперники достаются сильнейшим посевам
            for ($k = 0; $k < $bracketSize / 2; $k++) {
                $p1 = $participants[$k] ?? null;
                $p2 = $participants[$bracketSize - 1 - $k] ?? null;

                $rows[] = self::row($tournament, 1, $k, $p1?->id, $p2?->id, ($p1 && $p2) ? 'ready' : 'pending', $now);
            }

            for ($r = 2; $r <= $totalRounds; $r++) {
                for ($i = 0; $i < $bracketSize / (2 ** $r); $i++) {
                    $rows[] = self::row($tournament, $r, $i, null, null, 'pending', $now);
                }
            }

            TournamentMatch::insert($rows);

            self::linkNextMatches($tournament);

            // Пропуски: одиночка в паре сразу проходит дальше
            $tournament->matches()
                ->where('round', 1)
                ->get()
                ->filter(fn (TournamentMatch $m) => (bool) $m->participant1_id !== (bool) $m->participant2_id)
                ->each(function (TournamentMatch $m) {
                    $m->update([
                        'winner_id' => $m->participant1_id ?? $m->participant2_id,
                        'status' => 'completed',
                        'is_bye' => true,
                        'completed_at' => now(),
                    ]);

                    self::advanceWinner($m->fresh());
                });

            $tournament->update([
                'status' => 'ongoing',
                'starts_at' => $tournament->starts_at ?? now(),
            ]);
        });
    }

    private static function row(Tournament $t, int $round, int $position, ?int $p1, ?int $p2, string $status, $now): array
    {
        return [
            'tournament_id' => $t->id,
            'round' => $round,
            'position' => $position,
            'bracket' => 'main',
            'participant1_id' => $p1,
            'participant2_id' => $p2,
            'status' => $status,
            'is_bye' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ];
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
            if (! $nextRoundMatches) {
                continue;
            }

            foreach ($roundMatches->sortBy('position')->values() as $i => $match) {
                $nextMatch = $nextRoundMatches->firstWhere('position', intdiv($i, 2));
                if (! $nextMatch) {
                    continue;
                }

                $match->update([
                    'next_match_id' => $nextMatch->id,
                    'next_slot' => $i % 2 === 0 ? 'p1' : 'p2',
                ]);
            }
        }
    }

    /**
     * После результата матча: победитель уходит в следующий матч,
     * а победа в финале завершает турнир.
     *
     * Раньше метод выходил сразу, если следующего матча нет, — то есть
     * ровно на финале, и чемпион нигде не фиксировался.
     */
    public static function advanceWinner(TournamentMatch $match): void
    {
        if (! $match->winner_id) {
            return;
        }

        if ($match->next_match_id) {
            $next = $match->nextMatch;
            if (! $next) {
                return;
            }

            $field = $match->next_slot === 'p2' ? 'participant2_id' : 'participant1_id';
            $next->update([$field => $match->winner_id]);

            // Оба участника на месте — матч готов к игре
            if ($next->participant1_id && $next->participant2_id && $next->status === 'pending') {
                $next->update(['status' => 'ready']);
            }

            return;
        }

        self::finishTournament($match);
    }

    /** Финал сыгран: турнир завершён, победа записана, чемпион награждён. */
    private static function finishTournament(TournamentMatch $final): void
    {
        $tournament = $final->tournament;
        $winner = $final->winner;

        if (! $tournament || ! $winner) {
            return;
        }

        $tournament->update([
            'status' => 'completed',
            'ends_at' => $tournament->ends_at ?? now(),
        ]);

        // Результат финала исправили — победа прежнего чемпиона отменяется
        TournamentWin::where('tournament_id', $tournament->id)
            ->where('user_id', '!=', $winner->user_id ?? 0)
            ->delete();

        if ($tournament->type === 'solo' && $winner->user_id) {
            $user = User::find($winner->user_id);
            if (! $user) {
                return;
            }

            TournamentWin::firstOrCreate(
                ['user_id' => $user->id, 'tournament_id' => $tournament->id],
                ['min_tier' => $tournament->min_tier, 'max_tier' => $tournament->max_tier],
            );

            self::promoteByLevel($user, $tournament->level);

            AchievementService::check($user);
            AchievementService::grant($user, 'tournament_first_win');

            $user->notify(new TournamentNotification(
                'tournament_won',
                "Вы выиграли турнир «{$tournament->name}»! +{$tournament->win_points} очков рейтинга.",
                $tournament->id,
            ));

            return;
        }

        // Клановый турнир: поздравляем лидера клана
        $leader = $winner->clan?->leader;
        $leader?->notify(new TournamentNotification(
            'tournament_won',
            "Ваш клан выиграл турнир «{$tournament->name}»!",
            $tournament->id,
        ));
    }

    /**
     * Тир за турниры уровня: A-турнир (B..A) + 71% → S,
     * пять побед в S-турнирах (A..S) + 71% → S+.
     */
    private static function promoteByLevel(User $user, ?string $level): void
    {
        if ($level === 'A' && $user->tier_score >= 71 && ! in_array($user->tier, ['S', 'S+'], true)) {
            $user->update(['tier' => 'S']);
        }

        if ($level === 'S' && $user->tier_score >= 71) {
            $sWins = TournamentWin::where('user_id', $user->id)
                ->where('min_tier', 'A')
                ->where('max_tier', 'S')
                ->count();

            if ($sWins >= 5) {
                $user->update(['tier' => 'S+']);
            }
        }
    }
}
