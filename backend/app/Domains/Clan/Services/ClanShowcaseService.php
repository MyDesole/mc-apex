<?php

namespace App\Domains\Clan\Services;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanCheckin;
use App\Domains\Clan\Models\ClanWar;
use App\Domains\Clan\Support\ClanLevel;
use Illuminate\Support\Collection;

/**
 * Витрина клана для публичной страницы: уровень, место в рейтинге, форма
 * в войнах, активность и трофеи. Всё считается из уже существующих данных —
 * отдельного журнала не нужно.
 */
class ClanShowcaseService
{
    private const FORM_LIMIT = 10;

    public function build(Clan $clan): array
    {
        $level = ClanLevel::progress(ClanLevel::sync($clan));
        $wars = $this->recentWars($clan);
        $rank = $this->rank($clan);
        $week = $this->weekActivity($clan);
        $age = (int) $clan->created_at?->diffInDays(now());

        return [
            'level' => $level,
            'rank' => $rank,
            'founded_at' => $clan->created_at?->toIso8601String(),
            'age_days' => $age,
            'form' => $wars,
            'streak' => $this->streak($wars),
            'activity' => $week,
            'trophies' => $this->trophies($clan, $level['level'], $rank, $wars, $age, $week),
        ];
    }

    /** Последние завершённые войны: соперник, счёт, итог — для полосы «формы». */
    private function recentWars(Clan $clan): Collection
    {
        return ClanWar::query()
            ->where('status', 'completed')
            ->where(fn ($q) => $q->where('challenger_clan_id', $clan->id)->orWhere('opponent_clan_id', $clan->id))
            ->with(['challenger:id,name,tag,avatar,banner_color', 'opponent:id,name,tag,avatar,banner_color'])
            ->latest('updated_at')
            ->limit(self::FORM_LIMIT)
            ->get()
            ->map(function (ClanWar $war) use ($clan) {
                $weChallenged = $war->challenger_clan_id === $clan->id;
                $enemy = $weChallenged ? $war->opponent : $war->challenger;

                return [
                    'id' => $war->id,
                    'result' => match (true) {
                        $war->winner_clan_id === $clan->id => 'win',
                        $war->winner_clan_id === null => 'draw',
                        default => 'loss',
                    },
                    'us' => (int) ($weChallenged ? $war->challenger_score : $war->opponent_score),
                    'them' => (int) ($weChallenged ? $war->opponent_score : $war->challenger_score),
                    'enemy' => $enemy ? [
                        'id' => $enemy->id,
                        'name' => $enemy->name,
                        'tag' => $enemy->tag,
                        'avatar_url' => $enemy->avatar_url,
                        'banner_color' => $enemy->banner_color,
                    ] : null,
                    'at' => $war->updated_at?->toIso8601String(),
                ];
            });
    }

    /** Серия: сколько последних войн подряд с одинаковым итогом. */
    private function streak(Collection $wars): ?array
    {
        $first = $wars->first();

        if (! $first || $first['result'] === 'draw') {
            return null;
        }

        $count = 0;
        foreach ($wars as $war) {
            if ($war['result'] !== $first['result']) {
                break;
            }
            $count++;
        }

        return ['result' => $first['result'], 'count' => $count];
    }

    /** Место по силе среди незабаненных кланов. */
    private function rank(Clan $clan): array
    {
        $total = Clan::where('is_banned', false)->count();
        $position = Clan::where('is_banned', false)->where('power', '>', (int) $clan->power)->count() + 1;

        return ['position' => $position, 'total' => $total];
    }

    /** Отметки за 7 дней: сколько всего и сколько разных участников. */
    private function weekActivity(Clan $clan): array
    {
        $rows = ClanCheckin::where('clan_id', $clan->id)
            ->where('day', '>=', now()->subDays(6)->toDateString())
            ->get(['user_id', 'day']);

        // По дням: для мини-графика активности
        $days = collect(range(6, 0))->map(function ($ago) use ($rows) {
            $day = now()->subDays($ago)->toDateString();

            return ['date' => $day, 'count' => $rows->filter(fn ($r) => (string) $r->day === $day)->count()];
        })->values();

        return [
            'checkins' => $rows->count(),
            'active_members' => $rows->pluck('user_id')->unique()->count(),
            'days' => $days,
        ];
    }

    /**
     * Трофеи клана: открытые и закрытые, с подсказкой, как получить.
     * Это витрина достижений — как трофеи в профиле игрока.
     */
    private function trophies(Clan $clan, int $level, array $rank, Collection $wars, int $age, array $week): array
    {
        $members = $clan->members()->count();
        $cap = ClanLevel::memberCap($clan);
        $wins = (int) $clan->wins;
        $flawless = $wars->take(5)->count() === 5 && $wars->take(5)->every(fn ($w) => $w['result'] === 'win');

        $list = [
            ['key' => 'top1', 'icon' => 'crown', 'title' => 'Сильнейший', 'text' => 'Первое место по силе', 'done' => $rank['position'] === 1],
            ['key' => 'top3', 'icon' => 'trophy', 'title' => 'Пьедестал', 'text' => 'Топ-3 по силе', 'done' => $rank['position'] <= 3],
            ['key' => 'first_win', 'icon' => 'sword', 'title' => 'Первая кровь', 'text' => 'Выиграть клановую войну', 'done' => $wins >= 1],
            ['key' => 'wins10', 'icon' => 'medal', 'title' => 'Ветераны войн', 'text' => '10 побед в клановых войнах', 'done' => $wins >= 10],
            ['key' => 'flawless', 'icon' => 'flame', 'title' => 'Без шансов', 'text' => '5 побед в войнах подряд', 'done' => $flawless],
            ['key' => 'level5', 'icon' => 'star', 'title' => 'Опытный клан', 'text' => 'Достичь 5 уровня', 'done' => $level >= 5],
            ['key' => 'full', 'icon' => 'shield', 'title' => 'Полный состав', 'text' => 'Заполнить все места в клане', 'done' => $members >= $cap],
            ['key' => 'crowd', 'icon' => 'handshake', 'title' => 'Большая семья', 'text' => '20 участников', 'done' => $members >= 20],
            ['key' => 'active', 'icon' => 'bolt', 'title' => 'Живой клан', 'text' => '30 отметок участников за неделю', 'done' => $week['checkins'] >= 30],
            ['key' => 'old', 'icon' => 'clock', 'title' => 'Старая гвардия', 'text' => 'Клану больше 90 дней', 'done' => $age >= 90],
        ];

        // Открытые — первыми
        usort($list, fn ($a, $b) => (int) $b['done'] <=> (int) $a['done']);

        return $list;
    }
}
