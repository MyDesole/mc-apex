<?php

namespace App\Domains\Minecraft\Services;

use App\Domains\Minecraft\Models\MinecraftGrant;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Ранги сервера: группы LuckPerms, которые админ выдаёт игрокам, а роли
 * сайта (тестер, медийка, модератор) получают сами.
 *
 * Как и вся выдача, ранг уходит на сервер очередью minecraft_grants:
 * плагин забирает команды `lp …` и выполняет их от консоли. Группа
 * в LuckPerms должна существовать — команды для её создания отдаёт
 * `setupCommands()`, админ выполняет их на сервере один раз.
 */
class ServerRankService
{
    /**
     * Каталог рангов. `group` — имя группы в LuckPerms, `weight` — вес
     * (старший ранг перекрывает префикс младшего), `prefix` — префикс чата и TAB.
     */
    public const RANKS = [
        // Платные и боевые: растут по цене и по значимости
        // `compact` — короткая форма без жирного для ника над головой (там до 16 символов)
        'vip' => ['name' => 'VIP', 'kind' => 'donate', 'group' => 'vip', 'weight' => 10, 'prefix' => '&a&lVIP', 'compact' => '&aVIP', 'about' => 'Базовый статус'],
        'mvp' => ['name' => 'MVP', 'kind' => 'donate', 'group' => 'mvp', 'weight' => 20, 'prefix' => '&3&lM&b&lVP', 'compact' => '&3M&bVP', 'about' => 'Статус бойца'],
        'mvp_plus' => ['name' => 'MVP+', 'kind' => 'donate', 'group' => 'mvp_plus', 'weight' => 30, 'prefix' => '&3&lM&b&lVP&c&l+', 'compact' => '&3M&bVP&c+', 'about' => 'Расширенный MVP'],
        'apex' => ['name' => 'APEX', 'kind' => 'donate', 'group' => 'apex', 'weight' => 40, 'prefix' => '&6&lAP&e&lEX', 'compact' => '&6AP&eEX', 'about' => 'Высший боевой статус'],
        'legend' => ['name' => 'LEGEND', 'kind' => 'donate', 'group' => 'legend', 'weight' => 50, 'prefix' => '&5&lLEG&d&lEND', 'compact' => '&5LEG&dEND', 'about' => 'Редкий статус'],

        // Персонал: тестер, медиа, модератор и админ следуют за ролью на сайте
        'tester' => ['name' => 'Тестер', 'kind' => 'staff', 'group' => 'tester', 'weight' => 60, 'prefix' => '&e&lТЕСТЕР', 'compact' => '&eТЕСТЕР', 'about' => 'Выдаётся роли «Тестер»', 'role' => 'tester'],
        'media' => ['name' => 'Медиа', 'kind' => 'staff', 'group' => 'media', 'weight' => 65, 'prefix' => '&5&lМЕДИА', 'compact' => '&5МЕДИА', 'about' => 'Выдаётся роли «Медийка»', 'role' => 'media'],
        'helper' => ['name' => 'Хелпер', 'kind' => 'staff', 'group' => 'helper', 'weight' => 70, 'prefix' => '&a&lХЕЛПЕР', 'compact' => '&aХЕЛПЕР', 'about' => 'Выдаётся вручную'],
        'moderator' => ['name' => 'Модератор', 'kind' => 'staff', 'group' => 'moderator', 'weight' => 80, 'prefix' => '&9&lМОДЕР', 'compact' => '&9МОДЕР', 'about' => 'Выдаётся роли «Модератор»', 'role' => 'moderator'],
        'admin' => ['name' => 'Админ', 'kind' => 'staff', 'group' => 'admin', 'weight' => 100, 'prefix' => '&c&lАДМИН', 'compact' => '&cАДМИН', 'about' => 'Выдаётся роли «Администратор»', 'role' => 'admin'],
    ];

    /** Срок по умолчанию в днях для быстрых кнопок админки. */
    public const DURATIONS = [7, 30, 90];

    /** Выдать ранг: days = null значит навсегда. */
    public function grant(User $user, string $rank, ?int $days, ?User $by = null, string $source = 'admin'): array
    {
        $def = self::RANKS[$rank] ?? null;

        if (! $def) {
            throw new RuntimeException('Такого ранга нет.');
        }

        if ($days !== null && ($days < 1 || $days > 3650)) {
            throw new RuntimeException('Срок от 1 до 3650 дней.');
        }

        $expires = $days ? now()->addDays($days) : null;

        DB::table('server_rank_grants')->updateOrInsert(
            ['user_id' => $user->id, 'rank' => $rank],
            ['source' => $source, 'expires_at' => $expires, 'granted_by' => $by?->id, 'updated_at' => now(), 'created_at' => now()],
        );

        $this->queue($user, [$this->addCommand($def['group'], $days)]);

        return $this->state($user);
    }

    /** Снять ранг. */
    public function revoke(User $user, string $rank): array
    {
        $def = self::RANKS[$rank] ?? null;

        if (! $def) {
            throw new RuntimeException('Такого ранга нет.');
        }

        DB::table('server_rank_grants')->where('user_id', $user->id)->where('rank', $rank)->delete();

        $this->queue($user, ["lp user {player} parent remove {$def['group']}"]);

        return $this->state($user);
    }

    /**
     * Привести ранги по ролям в соответствие с ролью игрока.
     *
     * Ранг от старой роли снимается, ранг новой выдаётся навсегда. Ранги,
     * выданные админом вручную, не трогаем.
     */
    /** То же для всех игроков с ролью, которой положен ранг: догоняет тех, у кого роль выдали до появления рангов. */
    public function syncAllRoles(): void
    {
        $roles = array_values(array_filter(array_column(self::RANKS, 'role')));

        User::query()->whereIn('role', $roles)->get()->each(fn (User $u) => $this->syncRole($u));
    }

    public function syncRole(User $user): void
    {
        $wanted = null;

        foreach (self::RANKS as $key => $def) {
            if (($def['role'] ?? null) === $user->role) {
                $wanted = $key;
            }
        }

        $byRole = DB::table('server_rank_grants')->where('user_id', $user->id)->where('source', 'role')->pluck('rank')->all();

        foreach ($byRole as $rank) {
            if ($rank !== $wanted) {
                $this->revoke($user, $rank);
            }
        }

        if ($wanted && ! in_array($wanted, $byRole, true)) {
            $this->grant($user, $wanted, null, null, 'role');
        }
    }

    /** После привязки ника: выдать на сервер всё, что игроку уже положено. */
    public function deliver(User $user): void
    {
        if (! $this->validNick($user)) {
            return;
        }

        $commands = [];

        foreach ($this->active($user) as $row) {
            $days = $row->expires_at ? max(1, (int) ceil(now()->diffInHours($row->expires_at, false) / 24)) : null;
            $commands[] = $this->addCommand(self::RANKS[$row->rank]['group'], $days);
        }

        if ($commands) {
            $this->queue($user, $commands);
        }
    }

    /** Ранги игрока и каталог для админки. */
    public function state(User $user): array
    {
        $own = $this->active($user)->keyBy('rank');

        return [
            'nick' => $user->minecraft_username,
            'linked' => $this->validNick($user),
            'ranks' => collect(self::RANKS)->map(fn ($def, $key) => [
                'key' => $key,
                'name' => $def['name'],
                'kind' => $def['kind'],
                'about' => $def['about'],
                'role' => $def['role'] ?? null,
                'active' => $own->has($key),
                'source' => $own[$key]->source ?? null,
                'expires_at' => $own[$key]->expires_at ?? null,
            ])->values()->all(),
        ];
    }

    /**
     * Команды для консоли сервера: создать группы, веса и префиксы.
     * Выполняются один раз; повтор безопасен (LuckPerms скажет «уже есть»).
     *
     * @return array<int, string>
     */
    public function setupCommands(): array
    {
        $commands = [];

        foreach (self::RANKS as $def) {
            $commands[] = "lp creategroup {$def['group']}";
            $commands[] = "lp group {$def['group']} setweight {$def['weight']}";
            $commands[] = "lp group {$def['group']} meta setprefix {$def['weight']} \"{$def['prefix']} &r\"";
        }

        return $commands;
    }

    private function active(User $user)
    {
        return DB::table('server_rank_grants')
            ->where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get()
            ->filter(fn ($row) => isset(self::RANKS[$row->rank]))
            ->values();
    }

    private function addCommand(string $group, ?int $days): string
    {
        return $days
            ? "lp user {player} parent addtemp {$group} {$days}d replace"
            : "lp user {player} parent add {$group}";
    }

    private function validNick(User $user): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]{3,16}$/', (string) $user->minecraft_username);
    }

    /** Положить команды в очередь выдачи; без привязанного ника ждём привязки. */
    private function queue(User $user, array $commands): void
    {
        if (! $this->validNick($user)) {
            return;
        }

        $nick = (string) $user->minecraft_username;

        MinecraftGrant::create([
            'user_id' => $user->id,
            'shop_item_id' => null,
            'minecraft_username' => $nick,
            'commands' => array_map(fn (string $c) => str_replace('{player}', $nick, $c), $commands),
            'status' => MinecraftGrant::PENDING,
        ]);
    }
}
