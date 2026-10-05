<?php

namespace App\Domains\Tiers\Services;

use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;

/**
 * Активная заявка на тир-тест: одна на игрока.
 *
 * Ограничение вынесено отдельно, потому что проверять его нужно в двух
 * местах: при создании заявки и при проведении теста вручную (админ
 * тоже не должен плодить заявки одному игроку).
 *
 * Активной считается заявка в статусе ожидания или в работе. Завершённые
 * и отменённые не мешают: история тестов может быть любой длины.
 */
trait GuardsActiveTierTest
{
    /** Статусы, в которых заявка считается активной. */
    public const ACTIVE_STATUSES = ['pending', 'in_progress'];

    /** Активная заявка игрока, если она есть. */
    public function activeTierTestFor(int $userId): ?TierTest
    {
        return TierTest::query()
            ->where('user_id', $userId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->latest('id')
            ->first();
    }

    /**
     * Не даёт создать вторую активную заявку.
     *
     * Сообщение объясняет, что делать: ждать текущую или отменить её.
     */
    public function assertNoActiveTierTest(User $player): void
    {
        $active = $this->activeTierTestFor($player->id);

        if (! $active) {
            return;
        }

        $status = $active->status === 'in_progress'
            ? 'уже в работе'
            : 'ожидает тестера';

        abort(422, sprintf(
            'У игрока уже есть заявка на тир-тест: она %s. '
                . 'Дождись её завершения или отмени её, прежде чем создавать новую.',
            $status,
        ));
    }
}
