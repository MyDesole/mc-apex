<?php

namespace App\Domains\Minecraft\Services;

use App\Domains\Minecraft\Models\MinecraftDuel;
use App\Domains\Users\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Дуэли с игрового сервера: приём итогов, история и статистика по режимам.
 */
class MinecraftDuelService
{
    /** Сохраняет итог матча. Повторная отправка того же match_key ничего не дублирует. */
    public function record(array $data): MinecraftDuel
    {
        $winner = $this->userByNick($data['winner']);
        $loser = $this->userByNick($data['loser']);

        return MinecraftDuel::firstOrCreate(
            ['match_key' => $data['match_key']],
            [
                'kit' => mb_strtolower($data['kit']),
                'winner_name' => $data['winner'],
                'loser_name' => $data['loser'],
                'winner_user_id' => $winner?->id,
                'loser_user_id' => $loser?->id,
                'duration_seconds' => (int) ($data['duration_seconds'] ?? 0),
                'ranked' => (bool) ($data['ranked'] ?? false),
                'played_at' => $data['played_at'] ?? now(),
            ],
        );
    }

    /** Ник регистронезависимо: в SQLite сравнение строк чувствительно к регистру, в MySQL нет. */
    private function userByNick(string $nick): ?User
    {
        return User::query()
            ->whereRaw('lower(minecraft_username) = ?', [mb_strtolower($nick)])
            ->first();
    }

    /** История игрока: победы и поражения, новые сверху. */
    public function history(User $user, ?string $kit, int $perPage = 20): LengthAwarePaginator
    {
        return MinecraftDuel::query()
            ->where(fn ($q) => $q->where('winner_user_id', $user->id)->orWhere('loser_user_id', $user->id))
            ->when($kit, fn ($q) => $q->where('kit', $kit))
            ->orderByDesc('played_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Статистика игрока по режимам.
     *
     * @return array{total: array, kits: array<int, array>}
     */
    public function stats(User $user): array
    {
        $wins = MinecraftDuel::query()->where('winner_user_id', $user->id)
            ->select('kit', DB::raw('count(*) as n'), DB::raw('sum(duration_seconds) as secs'))
            ->groupBy('kit')->get()->keyBy('kit');

        $losses = MinecraftDuel::query()->where('loser_user_id', $user->id)
            ->select('kit', DB::raw('count(*) as n'), DB::raw('sum(duration_seconds) as secs'))
            ->groupBy('kit')->get()->keyBy('kit');

        $kits = $wins->keys()->merge($losses->keys())->unique()->values()
            ->map(function (string $kit) use ($wins, $losses) {
                $w = (int) ($wins[$kit]->n ?? 0);
                $l = (int) ($losses[$kit]->n ?? 0);

                return $this->row($kit, $w, $l, (int) ($wins[$kit]->secs ?? 0) + (int) ($losses[$kit]->secs ?? 0));
            })
            ->sortByDesc('games')
            ->values();

        $totalW = $kits->sum('wins');
        $totalL = $kits->sum('losses');

        return [
            'total' => $this->row(null, $totalW, $totalL, $kits->sum('seconds')) + ['streak' => $this->streak($user)],
            'kits' => $kits->all(),
        ];
    }

    private function row(?string $kit, int $wins, int $losses, int $seconds): array
    {
        $games = $wins + $losses;

        return [
            'kit' => $kit,
            'games' => $games,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => $games > 0 ? round($wins / $games * 100, 1) : 0.0,
            'seconds' => $seconds,
        ];
    }

    /** Текущая серия: положительная — победы подряд, отрицательная — поражения. */
    private function streak(User $user): int
    {
        $last = MinecraftDuel::query()
            ->where(fn ($q) => $q->where('winner_user_id', $user->id)->orWhere('loser_user_id', $user->id))
            ->orderByDesc('played_at')->orderByDesc('id')
            ->limit(50)
            ->get(['winner_user_id']);

        if ($last->isEmpty()) {
            return 0;
        }

        $first = $last->first()->winner_user_id === $user->id;
        $n = 0;

        foreach ($last as $duel) {
            if (($duel->winner_user_id === $user->id) !== $first) {
                break;
            }
            $n++;
        }

        return $first ? $n : -$n;
    }

    /** Топ кланов по победам в дуэлях их участников. */
    public function clanLeaderboard(int $limit = 20): array
    {
        $wins = DB::table('minecraft_duels as d')
            ->join('clan_members as m', 'm.user_id', '=', 'd.winner_user_id')
            ->select('m.clan_id', DB::raw('count(*) as n'))
            ->groupBy('m.clan_id')->pluck('n', 'clan_id');

        $losses = DB::table('minecraft_duels as d')
            ->join('clan_members as m', 'm.user_id', '=', 'd.loser_user_id')
            ->select('m.clan_id', DB::raw('count(*) as n'))
            ->groupBy('m.clan_id')->pluck('n', 'clan_id');

        $names = DB::table('clans')->whereIn('id', $wins->keys()->merge($losses->keys())->unique())
            ->get(['id', 'name', 'tag'])->keyBy('id');

        return $names->map(function ($clan) use ($wins, $losses) {
            $w = (int) ($wins[$clan->id] ?? 0);
            $l = (int) ($losses[$clan->id] ?? 0);

            return [
                'clan_id' => $clan->id,
                'name' => $clan->name,
                'tag' => $clan->tag,
                'wins' => $w,
                'losses' => $l,
                'win_rate' => $w + $l > 0 ? round($w / ($w + $l) * 100, 1) : 0.0,
            ];
        })->sortByDesc('wins')->take($limit)->values()->all();
    }
}
