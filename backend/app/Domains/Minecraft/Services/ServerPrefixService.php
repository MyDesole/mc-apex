<?php

namespace App\Domains\Minecraft\Services;

use App\Domains\Minecraft\Models\MinecraftGrant;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Префикс игрока на сервере: «донат, тир, ник» — без скобок.
 *
 * Собираем его на сайте (ранг выше всех по весу + тир или бридж-звание
 * с цветом сайта) и кладём в LuckPerms как префикс игрока с высоким
 * приоритетом. TAB и чат берут `%luckperms-prefix%`, поэтому тир виден
 * и в списке игроков, и над головой, и в чате. Меняем только то, что
 * изменилось: что отправили в прошлый раз, лежит в server_prefix_state.
 */
class ServerPrefixService
{
    /** Приоритет префикса игрока в LuckPerms: выше префиксов групп. */
    public const PRIORITY = 1000;

    public function __construct(private readonly ModPlayerService $players)
    {
    }

    /** Префикс для игрока в формате `&…`, либо null, если показывать нечего. */
    public function compose(User $user): ?string
    {
        $parts = [];

        $rank = $this->topRank($user);
        if ($rank) {
            $parts[] = $rank['prefix'];
        }

        $badge = $this->players->badge($user);
        if (! empty($badge['short'])) {
            $parts[] = $this->hex((string) $badge['color']) . '&l' . $badge['short'];
        }

        return $parts ? implode(' ', $parts) . ' &r' : null;
    }

    /**
     * Привести сервер в соответствие с сайтом. Возвращает, сколько игроков обновлено.
     */
    public function sync(): int
    {
        $changed = 0;

        $state = DB::table('server_prefix_state')->get()->keyBy('user_id');

        User::query()
            ->whereNotNull('minecraft_username')
            ->where('is_banned', false)
            ->orWhereIn('id', $state->keys())
            ->chunkById(200, function ($users) use ($state, &$changed) {
                foreach ($users as $user) {
                    $changed += $this->syncOne($user, $state->get($user->id)) ? 1 : 0;
                }
            });

        return $changed;
    }

    public function syncOne(User $user, ?object $last = null): bool
    {
        $last ??= DB::table('server_prefix_state')->where('user_id', $user->id)->first();

        $nick = (string) $user->minecraft_username;
        $valid = (bool) preg_match('/^[A-Za-z0-9_]{3,16}$/', $nick);
        $prefix = $valid && ! $user->is_banned ? $this->compose($user) : null;

        $lastNick = $last->nick ?? null;
        $lastPrefix = $last->prefix ?? null;

        if ($lastNick === ($valid ? $nick : null) && $lastPrefix === $prefix) {
            return false;
        }

        $commands = [];

        // Ник сменили или отвязали: со старого ника префикс убираем
        if ($lastPrefix !== null && $lastNick !== null && $lastNick !== ($valid ? $nick : null)) {
            $commands[$lastNick] = ["lp user {$lastNick} meta removeprefix " . self::PRIORITY];
        }

        if ($valid) {
            $commands[$nick] = [$prefix === null
                ? 'lp user ' . $nick . ' meta removeprefix ' . self::PRIORITY
                : 'lp user ' . $nick . ' meta setprefix ' . self::PRIORITY . ' "' . $prefix . '"'];
        }

        foreach ($commands as $target => $list) {
            MinecraftGrant::create([
                'user_id' => $user->id,
                'shop_item_id' => null,
                'minecraft_username' => $target,
                'commands' => $list,
                'status' => MinecraftGrant::PENDING,
            ]);
        }

        if ($valid) {
            DB::table('server_prefix_state')->updateOrInsert(
                ['user_id' => $user->id],
                ['nick' => $nick, 'prefix' => $prefix, 'updated_at' => now()],
            );
        } else {
            DB::table('server_prefix_state')->where('user_id', $user->id)->delete();
        }

        return true;
    }

    /**
     * Игрок зашёл на сервер: отправляем его префикс ещё раз.
     *
     * LuckPerms не знает игрока, который ни разу не заходил, и команда
     * `lp user …` для него молча не срабатывает, хотя плагин честно
     * отвечает «выполнено». Поэтому при входе шлём префикс повторно.
     *
     * @param  array<int, string>  $nicks  ники в нижнем регистре
     */
    public function onJoin(array $nicks): void
    {
        $rows = DB::table('server_prefix_state')
            ->whereNotNull('prefix')
            ->whereIn(DB::raw('LOWER(nick)'), array_map('mb_strtolower', $nicks))
            ->get();

        foreach ($rows as $row) {
            MinecraftGrant::create([
                'user_id' => $row->user_id,
                'shop_item_id' => null,
                'minecraft_username' => $row->nick,
                'commands' => ['lp user ' . $row->nick . ' meta setprefix ' . self::PRIORITY . ' "' . $row->prefix . '"'],
                'status' => MinecraftGrant::PENDING,
            ]);
        }
    }

    /** Самый весомый из действующих рангов игрока. */
    private function topRank(User $user): ?array
    {
        $rows = DB::table('server_rank_grants')
            ->where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('rank')
            ->all();

        $best = null;

        foreach ($rows as $key) {
            $def = ServerRankService::RANKS[$key] ?? null;

            if ($def && (! $best || $def['weight'] > $best['weight'])) {
                $best = $def;
            }
        }

        return $best;
    }

    /** #rrggbb → &#rrggbb (TAB и чат понимают такую запись). */
    private function hex(string $color): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? '&' . strtolower($color) : '&7';
    }
}
