<?php

namespace App\Domains\Players\Services;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Core\Models\SiteSetting;
use App\Domains\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Очки общего рейтинга.
 *
 *   очки = среднее тиров (PvP + BedWars + бридж) / 3
 *        + победы в матчах турниров × MATCH_WIN
 *        + сумма win_points выигранных турниров
 *        + участия в клановых войнах × WAR_PARTICIPATION
 *        + выигранные клановые войны × WAR_WIN
 *
 * Тиры — проценты 0–100. Бридж-процент — среднее трёх оценок (0–100)
 * по подтверждённым видам. Делим всегда на три: несданный режим = 0.
 * Турнирные очки получают только одиночные участники.
 *
 * Сила клана = половина очков каждого участника из рейтинга.
 */
class RatingService
{
    public const DEFAULTS = [
        'rating_match_win' => 5,
        'rating_war_participation' => 3,
        'rating_war_win' => 7,
    ];

    /** Доля очков игрока, которая идёт в силу клана. */
    public const CLAN_SHARE = 0.5;

    private const PVP_FIELDS = ['block_placing', 'rotka', 'movement', 'aim', 'game_sense'];

    private const BEDWARS_FIELDS = ['pvp', 'game_sense', 'bed_play', 'teamplay', 'building'];

    /** @return array{match_win: int, war_participation: int, war_win: int} */
    public function weights(): array
    {
        return [
            'match_win' => (int) SiteSetting::get('rating_match_win', self::DEFAULTS['rating_match_win']),
            'war_participation' => (int) SiteSetting::get('rating_war_participation', self::DEFAULTS['rating_war_participation']),
            'war_win' => (int) SiteSetting::get('rating_war_win', self::DEFAULTS['rating_war_win']),
        ];
    }

    /** Разбивка очков игрока — для профиля. */
    public function breakdown(User $user): array
    {
        return $this->compute([$user->id])[$user->id] ?? $this->empty();
    }

    /** Пересчитать игроков (и силу их кланов). */
    public function recalculate(iterable $userIds): void
    {
        $ids = collect($userIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        foreach ($this->compute($ids->all()) as $id => $row) {
            User::whereKey($id)->update(['rating_points' => $row['total'], 'bridge_primary' => $row['bridge_primary']]);
        }

        $clanIds = ClanMember::whereIn('user_id', $ids)->pluck('clan_id');
        $this->recalculateClans($clanIds);
    }

    /** Пересчитать всех — после смены весов или миграции. */
    public function recalculateAll(): int
    {
        $count = 0;

        User::query()->select('id')->chunkById(500, function ($users) use (&$count) {
            foreach ($this->compute($users->pluck('id')->all()) as $id => $row) {
                User::whereKey($id)->update(['rating_points' => $row['total'], 'bridge_primary' => $row['bridge_primary']]);
                $count++;
            }
        });

        $this->recalculateClans(Clan::pluck('id'));

        return $count;
    }

    /** Сила клана = CLAN_SHARE от очков участников, которые есть в рейтинге. */
    public function recalculateClans(iterable $clanIds): void
    {
        $ids = collect($clanIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $sums = ClanMember::query()
            ->join('users', 'users.id', '=', 'clan_members.user_id')
            ->whereIn('clan_members.clan_id', $ids)
            ->whereNotIn('users.role', User::STAFF_ROLES)
            ->groupBy('clan_members.clan_id')
            ->selectRaw('clan_members.clan_id, SUM(users.rating_points) as total')
            ->pluck('total', 'clan_id');

        foreach ($ids as $clanId) {
            $power = (int) floor(((int) ($sums[$clanId] ?? 0)) * self::CLAN_SHARE);
            Clan::whereKey($clanId)->where('power', '!=', $power)->update(['power' => $power]);
        }
    }

    /**
     * Разбивка для набора игроков. Каждая часть — один сгруппированный запрос.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, int|float>>
     */
    public function compute(array $ids): array
    {
        $weights = $this->weights();

        $pvp = $this->aspectPercent('player_aspects_pvp', self::PVP_FIELDS, $ids);
        $bedwars = $this->aspectPercent('player_aspects_bedwars', self::BEDWARS_FIELDS, $ids);
        $bridge = $this->bridgePercent($ids);
        $matchWins = $this->matchWins($ids);
        $tournamentPoints = $this->tournamentWinPoints($ids);
        [$wars, $warsWon] = $this->wars($ids);

        $result = [];

        foreach ($ids as $id) {
            $row = $this->empty();

            $row['pvp'] = (int) ($pvp[$id] ?? 0);
            $row['bedwars'] = (int) ($bedwars[$id] ?? 0);
            $row['bridge'] = (int) round((float) ($bridge[$id] ?? 0));
            // Бридж сильнее PvP — на сайте вместо тира показываем звание бриджера
            $row['bridge_primary'] = $row['bridge'] > $row['pvp'];
            $row['tiers_average'] = round(($row['pvp'] + $row['bedwars'] + $row['bridge']) / 3, 1);

            $row['match_wins'] = (int) ($matchWins[$id] ?? 0);
            $row['match_points'] = $row['match_wins'] * $weights['match_win'];

            $row['tournament_wins'] = (int) ($tournamentPoints[$id]->wins ?? 0);
            $row['tournament_points'] = (int) ($tournamentPoints[$id]->points ?? 0);

            $row['wars'] = (int) ($wars[$id] ?? 0);
            $row['war_points'] = $row['wars'] * $weights['war_participation'];
            $row['wars_won'] = (int) ($warsWon[$id] ?? 0);
            $row['war_win_points'] = $row['wars_won'] * $weights['war_win'];

            // Вверх: игрок с 1% в одном режиме не должен обнуляться и выпадать из рейтинга
            $row['tiers_points'] = (int) ceil(($row['pvp'] + $row['bedwars'] + $row['bridge']) / 3);

            $row['total'] = $row['tiers_points']
                + $row['match_points'] + $row['tournament_points']
                + $row['war_points'] + $row['war_win_points'];

            $result[$id] = $row;
        }

        return $result;
    }

    private function empty(): array
    {
        return [
            'pvp' => 0, 'bedwars' => 0, 'bridge' => 0, 'bridge_primary' => false, 'tiers_average' => 0.0, 'tiers_points' => 0,
            'match_wins' => 0, 'match_points' => 0,
            'tournament_wins' => 0, 'tournament_points' => 0,
            'wars' => 0, 'war_points' => 0, 'wars_won' => 0, 'war_win_points' => 0,
            'total' => 0,
        ];
    }

    /* ----------------------------- Части формулы ----------------------------- */

    private function aspectPercent(string $table, array $fields, array $ids): Collection
    {
        $sum = implode(' + ', array_map(fn ($f) => "COALESCE($f, 0)", $fields));

        return DB::table($table)
            ->whereIn('user_id', $ids)
            ->selectRaw("user_id, ($sum) as percent")
            ->pluck('percent', 'user_id');
    }

    /** Среднее (стабильность + скорость + сложность) / 3 по подтверждённым видам. */
    private function bridgePercent(array $ids): Collection
    {
        return DB::table('user_bridge_techniques')
            ->whereIn('user_id', $ids)
            ->where('status', 'confirmed')
            ->groupBy('user_id')
            ->selectRaw('user_id, AVG((COALESCE(stability, 0) + COALESCE(speed, 0) + COALESCE(difficulty, 0)) / 3.0) as percent')
            ->pluck('percent', 'user_id');
    }

    /** Выигранные матчи турниров одиночным участником. */
    private function matchWins(array $ids): Collection
    {
        return DB::table('tournament_matches as m')
            ->join('tournament_participants as p', 'p.id', '=', 'm.winner_id')
            ->whereIn('p.user_id', $ids)
            ->whereNull('p.clan_id')
            // Пропуск (bye) — не победа в матче
            ->where('m.is_bye', false)
            ->groupBy('p.user_id')
            ->selectRaw('p.user_id, COUNT(*) as wins')
            ->pluck('wins', 'user_id');
    }

    /** Победы в турнирах (победитель финала) и сумма их win_points. */
    private function tournamentWinPoints(array $ids): Collection
    {
        return DB::table('tournament_matches as m')
            ->join('tournament_participants as p', 'p.id', '=', 'm.winner_id')
            ->join('tournaments as t', 't.id', '=', 'm.tournament_id')
            ->whereNull('m.next_match_id')
            ->whereIn('p.user_id', $ids)
            ->whereNull('p.clan_id')
            ->groupBy('p.user_id')
            ->selectRaw('p.user_id, COUNT(DISTINCT t.id) as wins, SUM(t.win_points) as points')
            ->get()
            ->keyBy('user_id');
    }

    /** @return array{0: Collection, 1: Collection} участия и победы в завершённых войнах */
    private function wars(array $ids): array
    {
        $rows = DB::table('clan_war_participants as cp')
            ->join('clan_wars as w', 'w.id', '=', 'cp.clan_war_id')
            ->whereIn('cp.user_id', $ids)
            ->where('w.status', 'completed')
            ->groupBy('cp.user_id')
            ->selectRaw('cp.user_id, COUNT(*) as total, SUM(CASE WHEN w.winner_clan_id = cp.clan_id THEN 1 ELSE 0 END) as won')
            ->get();

        return [$rows->pluck('total', 'user_id'), $rows->pluck('won', 'user_id')];
    }
}
