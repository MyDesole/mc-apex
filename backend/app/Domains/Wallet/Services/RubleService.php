<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * «Рубли» — валюта Рублёвых копей, эндгейм-подземелья.
 *
 * Привязана к аккаунту: переводов, подарков и торговли нет — Рубли падают
 * только в Рублёвых копях и тратятся на ускорения и привилегии. Баланс считается в PHP под
 * блокировкой строки, а не вычитанием в SQL: колонка беззнаковая.
 * Связки с Minecraft-сервером и магазина привилегий пока нет.
 */
class RubleService
{
    public const SOURCE_DUNGEON_BOSS = 'dungeon_boss';
    public const SOURCE_DUNGEON_CLEAR = 'dungeon_clear';
    public const SOURCE_FARM = 'farm';
    public const SOURCE_SPEEDUP = 'speedup';

    public const SOURCE_RUBLE_DUNGEON = 'ruble_dungeon';
    public const SOURCE_RAID = 'raid';
    public const SOURCE_PRIVILEGE = 'privilege';

    /** Ускорение: рублей за каждый оставшийся час (с округлением вверх). */
    public const SPEEDUP_PER_HOUR = 2;

    /** Сколько стоит ускорить то, что закончится в $endsAt; 0 — уже закончилось. */
    public static function speedUpCost(mixed $endsAt): int
    {
        $seconds = now()->diffInSeconds(\Illuminate\Support\Carbon::parse($endsAt), false);

        return $seconds > 0 ? (int) ceil($seconds / 3600) * self::SPEEDUP_PER_HOUR : 0;
    }

    public static function balance(User $user): int
    {
        return (int) DB::table('users')->where('id', $user->id)->value('rubles');
    }

    /**
     * Начислить. Суточного предела нет: темп задают уровень питомцев и
     * одноразовый пропуск в Рублёвые копи. Повтор с тем же ключом не
     * начисляет ничего. Возвращает, сколько выдано.
     */
    public static function earn(User $user, int $amount, string $source, string $description, ?string $key = null): int
    {
        if ($amount <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($user, $amount, $source, $description, $key) {
            $row = DB::table('users')->where('id', $user->id)->lockForUpdate()->first(['id', 'rubles']);

            if ($key && DB::table('ruble_transactions')->where('idempotency_key', $key)->exists()) {
                return 0;
            }

            self::write($user, (int) $row->rubles, $amount, $source, $description, $key);

            return $amount;
        });
    }

    /** Списать; не хватает — исключение с понятным текстом. */
    public static function spend(User $user, int $amount, string $description, ?string $key = null, string $source = self::SOURCE_SPEEDUP): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $amount, $description, $key, $source) {
            $row = DB::table('users')->where('id', $user->id)->lockForUpdate()->first(['id', 'rubles']);

            if ((int) $row->rubles < $amount) {
                throw new RuntimeException('Не хватает Рублей: нужно ' . $amount . ', есть ' . (int) $row->rubles . '.');
            }

            self::write($user, (int) $row->rubles, -$amount, $source, $description, $key);
        });
    }

    private static function write(User $user, int $before, int $delta, string $source, string $description, ?string $key): void
    {
        $after = $before + $delta;

        DB::table('users')->where('id', $user->id)->update(['rubles' => $after]);
        DB::table('ruble_transactions')->insert([
            'user_id' => $user->id,
            'amount' => $delta,
            'balance_after' => $after,
            'source' => $source,
            'description' => $description,
            'idempotency_key' => $key,
            'created_at' => now(),
        ]);

        $user->setAttribute('rubles', $after);
        $user->syncOriginalAttributes(['rubles']);
    }
}
