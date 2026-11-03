<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * «Рубли» — валюта подземелий и ферм.
 *
 * Привязана к аккаунту: переводов, подарков и торговли нет — только
 * заработок в игре и трата на ускорения. Баланс считается в PHP под
 * блокировкой строки, а не вычитанием в SQL: колонка беззнаковая.
 * Связки с Minecraft-сервером и магазина привилегий пока нет.
 */
class RubleService
{
    public const SOURCE_DUNGEON_BOSS = 'dungeon_boss';
    public const SOURCE_DUNGEON_CLEAR = 'dungeon_clear';
    public const SOURCE_FARM = 'farm';
    public const SOURCE_SPEEDUP = 'speedup';

    /** Больше этого из игры за сутки не набрать (ускорения не в счёт). */
    public const DAILY_EARN_CAP = 60;

    /** Награда за босса по ступени подземелья. */
    public const BOSS_REWARD = [1 => 1, 2 => 2, 3 => 3, 4 => 5, 5 => 8];

    /** Первое прохождение подземелья: множитель к награде босса. */
    public const FIRST_CLEAR_MULTIPLIER = 5;

    /** Скрещивание на эпическую и легендарную ступени, спаривание. */
    public const FARM_REWARD = ['cross_epic' => 3, 'cross_legendary' => 6, 'mating' => 1];

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
     * Начислить, соблюдая суточный предел. Возвращает, сколько реально выдано.
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

            $today = (int) DB::table('ruble_transactions')
                ->where('user_id', $user->id)
                ->where('amount', '>', 0)
                ->where('created_at', '>=', now()->startOfDay())
                ->sum('amount');

            $granted = min($amount, max(0, self::DAILY_EARN_CAP - $today));

            if ($granted <= 0) {
                return 0;
            }

            self::write($user, (int) $row->rubles, $granted, $source, $description, $key);

            return $granted;
        });
    }

    /** Списать; не хватает — исключение с понятным текстом. */
    public static function spend(User $user, int $amount, string $description, ?string $key = null): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $amount, $description, $key) {
            $row = DB::table('users')->where('id', $user->id)->lockForUpdate()->first(['id', 'rubles']);

            if ((int) $row->rubles < $amount) {
                throw new RuntimeException('Не хватает Рублей: нужно ' . $amount . ', есть ' . (int) $row->rubles . '.');
            }

            self::write($user, (int) $row->rubles, -$amount, self::SOURCE_SPEEDUP, $description, $key);
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
