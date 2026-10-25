<?php

namespace App\Domains\Activity\Observers;

use App\Domains\Activity\Models\Activity;
use App\Domains\Activity\Services\ActivityFeed;
use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanWar;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Tournaments\Models\TournamentWin;
use App\Domains\Users\Models\User;

/**
 * Превращает изменения моделей в события ленты.
 *
 * Наблюдатель, а не вызовы из сервисов: к одному результату ведут
 * несколько путей (тест у тестера и ручной тест админа, отчёт о войне
 * и правка админом), и о каждом пришлось бы помнить отдельно.
 * Ачивки — исключение: они выдаются через attach() без событий модели,
 * поэтому их записывает AchievementService::grant().
 */
class ActivityObserver
{
    public function __construct(
        private readonly ActivityFeed $feed,
    ) {
    }

    /* ------------------------------ Тир-тесты ------------------------------ */

    /** Ручной тест админа создаётся сразу завершённым. */
    public function tierTestCreated(TierTest $test): void
    {
        if ($test->status === 'completed' && $test->result_tier) {
            $this->recordTierResult($test);
        }
    }

    /**
     * Тест у тестера завершается обновлением статуса.
     *
     * wasRecentlyCreated здесь не годится: он остаётся true у экземпляра
     * и после следующих правок, и правка заметки дублировала бы событие.
     */
    public function tierTestUpdated(TierTest $test): void
    {
        if ($test->status === 'completed' && $test->result_tier && $test->wasChanged('status')) {
            $this->recordTierResult($test);
        }
    }

    private function recordTierResult(TierTest $test): void
    {
        $this->feed->record(Activity::TIER_ASSIGNED, $test->user, [
            'tier' => $test->result_tier,
            'score' => (float) $test->result_score,
            'mode' => $test->mode,
        ]);
    }

    /* ------------------------------ Кланы ------------------------------ */

    public function clanCreated(Clan $clan): void
    {
        $this->feed->record(Activity::CLAN_CREATED, $clan->leader, [
            'clan' => self::clanSnapshot($clan),
        ]);
    }

    public function clanWarUpdated(ClanWar $war): void
    {
        if ($war->status !== 'completed' || ! $war->wasChanged('status')) {
            return;
        }

        $challenger = Clan::find($war->challenger_clan_id);
        $opponent = Clan::find($war->opponent_clan_id);

        if (! $challenger || ! $opponent) {
            return;
        }

        $this->feed->record(Activity::CLAN_WAR_FINISHED, null, [
            'challenger' => self::clanSnapshot($challenger),
            'opponent' => self::clanSnapshot($opponent),
            'challenger_score' => (int) $war->challenger_score,
            'opponent_score' => (int) $war->opponent_score,
            'winner_clan_id' => $war->winner_clan_id,
            'outcome' => $war->outcome,
        ]);
    }

    /* ------------------------------ Турниры ------------------------------ */

    public function tournamentWinCreated(TournamentWin $win): void
    {
        $tournament = $win->tournament;

        $this->feed->record(Activity::TOURNAMENT_WON, $win->user, [
            'tournament' => $tournament ? [
                'id' => $tournament->id,
                'name' => $tournament->name,
            ] : null,
        ]);
    }

    /* ------------------------------ Майнкрафт ------------------------------ */

    public function userUpdated(User $user): void
    {
        if (! $user->wasChanged('minecraft_linked_at') || ! $user->minecraft_linked_at) {
            return;
        }

        $this->feed->record(Activity::MINECRAFT_LINKED, $user, [
            'nickname' => $user->minecraft_username,
        ]);
    }

    /** Снимок клана: имя и цвет на момент события. */
    public static function clanSnapshot(Clan $clan): array
    {
        return [
            'id' => $clan->id,
            'name' => $clan->name,
            'tag' => $clan->tag,
            'color' => $clan->banner_color,
        ];
    }
}
