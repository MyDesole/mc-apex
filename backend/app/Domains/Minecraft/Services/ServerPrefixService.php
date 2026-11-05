<?php

namespace App\Domains\Minecraft\Services;

use App\Domains\Minecraft\Models\MinecraftGrant;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Префикс игрока на сервере: «донат, тир, ник» — без скобок.
 *
 * Сервер на 1.8.8: цвета только из 16 стандартных (`&a`, без hex), а
 * префикс ника над головой не длиннее 16 символов. Поэтому на каждого
 * игрока у нас две строки в LuckPerms:
 *  - prefix: полный, красивый (жирный, двухцветный) — для TAB-списка и чата;
 *  - suffix: короткий, до 16 символов — его TAB подставляет в префикс
 *    ника над головой (`tagprefix: %luckperms-suffix%`). В чате суффикс не
 *    выводится.
 * Меняем только то, что изменилось: что отправили в прошлый раз, лежит
 * в server_prefix_state.
 */
class ServerPrefixService
{
    /** Приоритет префикса игрока в LuckPerms: выше префиксов групп. */
    public const PRIORITY = 1000;

    /** Длина префикса ника над головой на 1.8. */
    public const TAG_LIMIT = 16;

    /** Цвета тиров в стандартной палитре: каждый тир отличим от соседей. */
    public const TIER_LEGACY = ['S+' => '6', 'S' => 'e', 'A' => 'c', 'B' => 'd', 'C' => 'b', 'D' => 'a', 'E' => '7'];

    private const LEGACY = [
        '0' => [0, 0, 0], '1' => [0, 0, 170], '2' => [0, 170, 0], '3' => [0, 170, 170],
        '4' => [170, 0, 0], '5' => [170, 0, 170], '6' => [255, 170, 0], '7' => [170, 170, 170],
        '8' => [85, 85, 85], '9' => [85, 85, 255], 'a' => [85, 255, 85], 'b' => [85, 255, 255],
        'c' => [255, 85, 85], 'd' => [255, 85, 255], 'e' => [255, 255, 85], 'f' => [255, 255, 255],
    ];

    public function __construct(private readonly ModPlayerService $players)
    {
    }

    /**
     * Две строки для игрока: полный префикс и короткий для ника над головой.
     *
     * @return array{prefix: string, tag: string}|null  null, если показывать нечего
     */
    public function compose(User $user): ?array
    {
        $rank = $this->topRank($user);
        $badge = $this->players->badge($user);
        $short = (string) ($badge['short'] ?? '');
        $color = $short !== '' ? $this->legacy($badge) : null;

        if (! $rank && $short === '') {
            return null;
        }

        $full = array_filter([$rank['prefix'] ?? null, $short !== '' ? "&{$color}&l{$short}" : null]);

        // Над головой не больше 16 символов: пробуем от красивого к компактному
        $donate = $rank['compact'] ?? null;
        $candidates = [];

        if ($donate && $short !== '') {
            $candidates[] = "{$donate} &{$color}&l{$short} &r";
            $candidates[] = "{$donate} &{$color}{$short} &r";
            $candidates[] = "{$donate} &{$color}{$short}&r";
        }
        if ($short !== '') {
            $candidates[] = "&{$color}&l{$short} &r";
            $candidates[] = "&{$color}{$short} &r";
        }
        if ($donate) {
            $candidates[] = "{$donate} &r";
        }

        $tag = '&r';
        foreach ($candidates as $candidate) {
            if (mb_strlen($candidate) <= self::TAG_LIMIT) {
                $tag = $candidate;
                break;
            }
        }

        return ['prefix' => implode(' ', $full) . ' &r', 'tag' => $tag];
    }

    /**
     * Привести сервер в соответствие с сайтом. Возвращает, сколько игроков обновлено.
     */
    public function sync(): int
    {
        $changed = 0;

        // Роли сайта (тестер, медиа, модератор, админ) -> ранги сервера
        app(ServerRankService::class)->syncAllRoles();

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
        $strings = $valid && ! $user->is_banned ? $this->compose($user) : null;

        $lastNick = $last->nick ?? null;

        if ($lastNick === ($valid ? $nick : null)
            && ($last->prefix ?? null) === ($strings['prefix'] ?? null)
            && ($last->tag ?? null) === ($strings['tag'] ?? null)) {
            return false;
        }

        $commands = [];

        // Ник сменили или отвязали: со старого ника префикс убираем
        if (($last->prefix ?? null) !== null && $lastNick !== null && $lastNick !== ($valid ? $nick : null)) {
            $commands[$lastNick] = $this->clearCommands($lastNick);
        }

        if ($valid) {
            $commands[$nick] = $strings ? $this->setCommands($nick, $strings['prefix'], $strings['tag']) : $this->clearCommands($nick);
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
                ['nick' => $nick, 'prefix' => $strings['prefix'] ?? null, 'tag' => $strings['tag'] ?? null, 'updated_at' => now()],
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
                'commands' => $this->setCommands($row->nick, $row->prefix, (string) ($row->tag ?? '&r')),
                'status' => MinecraftGrant::PENDING,
            ]);
        }
    }

    /** @return array<int, string> */
    private function setCommands(string $nick, string $prefix, string $tag): array
    {
        return [
            "lp user {$nick} meta setprefix " . self::PRIORITY . " \"{$prefix}\"",
            "lp user {$nick} meta setsuffix " . self::PRIORITY . " \"{$tag}\"",
        ];
    }

    /** @return array<int, string> */
    private function clearCommands(string $nick): array
    {
        return [
            "lp user {$nick} meta removeprefix " . self::PRIORITY,
            "lp user {$nick} meta removesuffix " . self::PRIORITY,
        ];
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

    /** Код цвета 1.8 для значка: тир — по таблице, звание бриджа — ближайший к цвету сайта. */
    private function legacy(array $badge): string
    {
        if (($badge['type'] ?? '') === 'tier' && isset(self::TIER_LEGACY[$badge['short'] ?? ''])) {
            return self::TIER_LEGACY[$badge['short']];
        }

        $hex = ltrim((string) ($badge['color'] ?? ''), '#');

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return '7';
        }

        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        $best = '7';
        $bestDistance = PHP_INT_MAX;

        foreach (self::LEGACY as $code => [$cr, $cg, $cb]) {
            $distance = ($r - $cr) ** 2 + ($g - $cg) ** 2 + ($b - $cb) ** 2;

            if ($distance < $bestDistance) {
                [$best, $bestDistance] = [$code, $distance];
            }
        }

        return $best;
    }
}
