<?php

namespace App\Domains\Chat\Services;

use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Кто сейчас на сайте.
 *
 * Источников два, и они дополняют друг друга:
 *
 *   1. Presence-канал Reverb: пока открыт сайт, соединение сообщает о
 *      входе и выходе. Именно он зажигает зелёную точку сразу.
 *
 *   2. Время последней активности (users.last_seen_at) — запасной
 *      вариант. Работает, если Reverb недоступен или вкладку только что
 *      закрыли: запись живёт ещё минуту.
 *
 * Явный выход перебивает второй источник. Без этого игрок, нажавший
 * «Выйти», оставался онлайн ещё минуту: выход снимал отметку присутствия,
 * но активность на странице держала его в списке.
 */
class PresenceService
{
    /**
     * Сколько секунд считать игрока онлайн по последней активности.
     *
     * Значение согласовано с частотой обновления присутствия на странице:
     * если сделать окно больше, вышедший игрок остаётся «онлайн» и
     * возвращается при обновлении страницы.
     */
    public const ONLINE_WINDOW_SECONDS = 60;

    /** Через сколько секунд запись о присутствии считается устаревшей. */
    private const PRESENCE_TTL_SECONDS = 90;

    private const CACHE_KEY = 'presence:members';

    /** Кто вышел явно: активность не должна возвращать его в список. */
    private const OFFLINE_KEY = 'presence:left';

    /**
     * Идентификаторы игроков, которые сейчас онлайн.
     *
     * @return array<int, int>
     */
    public function onlineUserIds(): array
    {
        $ids = array_unique([...$this->fromPresenceChannel(), ...$this->fromActivity()]);

        sort($ids);

        return $ids;
    }

    /**
     * Кто из перечисленных сейчас онлайн.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, int>
     */
    public function onlineAmong(array $userIds): array
    {
        $wanted = array_values(array_unique(array_map('intval', $userIds)));

        if (! $wanted) {
            return [];
        }

        $online = array_flip($this->onlineUserIds());

        return array_values(array_filter($wanted, fn (int $id) => isset($online[$id])));
    }

    /** Онлайн ли конкретный игрок. */
    public function isOnline(int $userId): bool
    {
        return in_array($userId, $this->onlineUserIds(), true);
    }

    /**
     * Отметить вход на сайт.
     *
     * Вызывается presence-каналом при подписке. Время хранится рядом с
     * идентификатором: так устаревшие записи отпадают сами, если выход
     * по какой-то причине не пришёл.
     */
    public function markOnline(int $userId): void
    {
        $members = $this->members();

        $members[$userId] = now()->timestamp;

        Cache::put(self::CACHE_KEY, $members, now()->addMinutes(10));

        // Вернулся — значит вышедшим больше не считается
        $this->forgetLeft($userId);
    }

    /**
     * Отметить выход.
     *
     * Presence-канал сообщает о выходе одной подписки. Если у игрока
     * остались другие вкладки, следующая подписка отметит его снова;
     * поэтому выход просто убирает запись.
     */
    public function markOffline(int $userId): void
    {
        $members = $this->members();

        unset($members[$userId]);

        Cache::put(self::CACHE_KEY, $members, now()->addMinutes(10));
    }

    /**
     * Отметить явный выход: нажата кнопка «Выйти» или закрыт сайт.
     *
     * Кроме снятия отметки присутствия запоминаем выход отдельно, иначе
     * недавняя активность вернёт игрока в список онлайна.
     */
    public function markLeft(int $userId): void
    {
        $this->markOffline($userId);

        $left = $this->leftMembers();

        $left[$userId] = now()->timestamp;

        Cache::put(self::OFFLINE_KEY, $left, now()->addMinutes(10));
    }

    /* ------------------------- Источники ------------------------- */

    /**
     * Кто отмечен в presence-канале и не устарел.
     *
     * @return array<int, int>
     */
    private function fromPresenceChannel(): array
    {
        $limit = now()->subSeconds(self::PRESENCE_TTL_SECONDS)->timestamp;

        $ids = [];

        foreach ($this->members() as $id => $seenAt) {
            if ((int) $seenAt >= $limit) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    /**
     * Кто недавно проявлял активность.
     *
     * Явно вышедших пропускаем: активность на странице не должна
     * возвращать их в список онлайна.
     *
     * @return array<int, int>
     */
    private function fromActivity(): array
    {
        $limit = now()->subSeconds(self::ONLINE_WINDOW_SECONDS);

        $left = $this->recentlyLeft();

        return User::query()
            ->where('last_seen_at', '>=', $limit)
            ->when($left, fn ($q) => $q->whereNotIn('id', $left))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Кто вышел явно и ещё не вернулся.
     *
     * Записи старше окна активности не нужны: после него последняя
     * активность и так перестаёт означать онлайн.
     *
     * @return array<int, int>
     */
    private function recentlyLeft(): array
    {
        $limit = now()->subSeconds(self::ONLINE_WINDOW_SECONDS)->timestamp;

        $ids = [];

        foreach ($this->leftMembers() as $id => $leftAt) {
            if ((int) $leftAt >= $limit) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    /** Убирает отметку явного выхода. */
    private function forgetLeft(int $userId): void
    {
        $left = $this->leftMembers();

        if (! isset($left[$userId])) {
            return;
        }

        unset($left[$userId]);

        Cache::put(self::OFFLINE_KEY, $left, now()->addMinutes(10));
    }

    /** Сырые записи присутствия. */
    private function members(): array
    {
        $members = Cache::get(self::CACHE_KEY, []);

        return is_array($members) ? $members : [];
    }

    /** Сырые записи о явном выходе. */
    private function leftMembers(): array
    {
        $left = Cache::get(self::OFFLINE_KEY, []);

        return is_array($left) ? $left : [];
    }
}
