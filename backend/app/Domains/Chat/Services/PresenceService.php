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
 *      закрыли: запись живёт ещё пару минут.
 *
 * Итог — объединение обоих списков.
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
     * @return array<int, int>
     */
    private function fromActivity(): array
    {
        return User::query()
            ->where('last_seen_at', '>=', now()->subSeconds(self::ONLINE_WINDOW_SECONDS))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Сырые записи присутствия. */
    private function members(): array
    {
        $members = Cache::get(self::CACHE_KEY, []);

        return is_array($members) ? $members : [];
    }
}
