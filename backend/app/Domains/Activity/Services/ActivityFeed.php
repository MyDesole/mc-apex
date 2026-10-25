<?php

namespace App\Domains\Activity\Services;

use App\Domains\Activity\Events\ActivityRecorded;
use App\Domains\Activity\Models\Activity;
use App\Domains\Activity\Resources\ActivityResource;
use App\Domains\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Лента «Сейчас на APEX»: запись событий и их рассылка.
 *
 * Запись идёт в той же транзакции, что и само действие: откатилось
 * действие — исчезла и запись. Рассылка — строго после коммита, иначе
 * зрители увидели бы событие, которого в итоге не случилось.
 *
 * Лента — украшение, а не бизнес-логика: если Reverb недоступен,
 * действие игрока всё равно должно пройти. Поэтому ошибки рассылки
 * только пишутся в лог.
 */
class ActivityFeed
{
    public const DEFAULT_LIMIT = 20;

    public const MAX_LIMIT = 50;

    public function record(string $type, ?User $actor, array $payload = []): Activity
    {
        $activity = Activity::create([
            'type' => $type,
            'actor_id' => $actor?->id,
            'payload' => $payload,
        ]);

        DB::afterCommit(fn () => $this->broadcast($activity));

        return $activity;
    }

    /**
     * Последние события, новые сверху. $before — курсор для «показать ещё».
     */
    public function latest(int $limit = self::DEFAULT_LIMIT, ?int $before = null): Collection
    {
        return Activity::query()
            ->with('actor.clanMember.clan:id,tag,banner_color')
            ->when($before, fn ($q) => $q->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit(min(max($limit, 1), self::MAX_LIMIT))
            ->get();
    }

    private function broadcast(Activity $activity): void
    {
        try {
            $activity->load('actor.clanMember.clan:id,tag,banner_color');

            broadcast(new ActivityRecorded(
                (new ActivityResource($activity))->resolve()
            ));
        } catch (Throwable $e) {
            Log::warning('Лента: не удалось разослать событие', [
                'activity_id' => $activity->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
