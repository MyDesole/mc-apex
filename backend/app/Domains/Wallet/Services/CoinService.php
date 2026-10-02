<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Domains\Shop\Services\ShopSettingService;

/**
 * Единственная точка изменения баланса ApexCoin.
 *
 * Правила:
 *  - любой перевод/начисление идёт через DB-транзакцию с блокировкой строки пользователя;
 *  - каждая операция пишет запись в ledger coin_transactions (источник истины);
 *  - начисления с idempotency_key повторно не применяются (защита от двойного завершения теста).
 */
class CoinService
{
    /**
     * Начислить или списать монеты.
     *
     * @param  int  $amount  положительное — начисление, отрицательное — списание
     * @return int применённая сумма (0, если операция пропущена как дубль)
     */
    /**
     * Приводит полиморфный тип к псевдониму из morphMap.
     *
     * Поле пишется напрямую, поэтому Eloquent не применяет карту сам:
     * без этого в базе оказывается полное имя класса, которое ломается
     * при переносе моделей.
     */
    private static function morphType(?string $type): ?string
    {
        if (! $type || ! class_exists($type)) {
          return $type;
        }

        if (! is_subclass_of($type, Model::class)) {
          return $type;
        }

        return (new $type)->getMorphClass();
    }

    public static function credit(
        User $user,
        int $amount,
        string $source,
        ?string $description = null,
        ?string $idempotencyKey = null,
        array $options = []
    ): int {
        if ($amount === 0) {
            return 0;
        }

        if ($idempotencyKey && CoinTransaction::where('idempotency_key', $idempotencyKey)->exists()) {
            return 0;
        }

        return DB::transaction(function () use ($user, $amount, $source, $description, $idempotencyKey, $options) {
            $query = User::whereKey($user->id);

            // SQLite не умеет SELECT ... FOR UPDATE, поэтому блокировку строки
            // применяем только на драйверах, которые её поддерживают
            if (self::supportsRowLock()) {
                $query->lockForUpdate();
            }

            /** @var User $locked */
            $locked = $query->firstOrFail();

            $balance = (int) $locked->apex_coins;

            if ($amount < 0 && $balance + $amount < 0) {
                throw new RuntimeException('Недостаточно ApexCoin на балансе.');
            }

            $balance += $amount;

            $locked->apex_coins = $balance;

            if ($amount < 0) {
                $locked->apex_coins_spent = (int) $locked->apex_coins_spent + abs($amount);
            }

            $locked->save();

            CoinTransaction::create([
                'user_id' => $locked->id,
                'amount' => $amount,
                'balance_after' => $balance,
                'source' => $source,
                'description' => $description,
                'reference_type' => self::morphType($options['reference_type'] ?? null),
                'reference_id' => $options['reference_id'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'actor_id' => $options['actor_id'] ?? null,
                'meta' => $options['meta'] ?? null,
            ]);

            // Синхронизируем переданный извне инстанс, чтобы вызывающий код видел новый баланс
            $user->setAttribute('apex_coins', $balance);
            $user->setAttribute('apex_coins_spent', $locked->apex_coins_spent);
            $user->syncOriginalAttributes(['apex_coins', 'apex_coins_spent']);

            return $amount;
        });
    }

    public static function debit(
        User $user,
        int $amount,
        string $source,
        ?string $description = null,
        array $options = []
    ): int {
        return self::credit($user, -abs($amount), $source, $description, null, $options);
    }

    /**
     * Списание с проверкой достаточности средств (без исключения).
     */
    public static function canAfford(User $user, int $amount): bool
    {
        return (int) $user->apex_coins >= $amount;
    }

    public static function balance(User $user): int
    {
        return (int) $user->fresh()->apex_coins;
    }

    /**
     * Поддерживает ли текущая БД SELECT ... FOR UPDATE.
     */
    private static function supportsRowLock(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql', 'sqlsrv'], true);
    }

    /**
     * Подарок другу. Возвращает [сколько дошло, сколько удержано комиссией].
     */
    public static function gift(User $from, User $to, int $amount): array
    {
        // Правила подарков берём через ShopSettingService, чтобы значения,
        // изменённые в админке, действительно применялись.
        $gift = [
            'enabled' => ShopSettingService::getBool('gift.enabled'),
            'min' => ShopSettingService::getInt('gift.min'),
            'max' => ShopSettingService::getInt('gift.max'),
            'daily_limit' => ShopSettingService::getInt('gift.daily_limit'),
            'fee_percent' => ShopSettingService::getInt('gift.fee_percent'),
        ];

        if (! $gift['enabled']) {
            throw new RuntimeException('Подарки временно отключены.');
        }

        if ($amount < $gift['min'] || $amount > $gift['max']) {
            throw new RuntimeException("Сумма подарка должна быть от {$gift['min']} до {$gift['max']} ApexCoin.");
        }

        if ($from->id === $to->id) {
            throw new RuntimeException('Нельзя подарить монеты самому себе.');
        }

        if (! $from->isFriendsWith($to->id)) {
            throw new RuntimeException('Дарить ApexCoin можно только друзьям.');
        }

        $sentToday = self::giftedToday($from);

        if ($sentToday + $amount > $gift['daily_limit']) {
            $left = max(0, $gift['daily_limit'] - $sentToday);
            throw new RuntimeException("Дневной лимит подарков исчерпан. Сегодня можно подарить ещё {$left} ApexCoin.");
        }

        $fee = (int) floor($amount * $gift['fee_percent'] / 100);
        $received = $amount - $fee;

        DB::transaction(function () use ($from, $to, $amount, $fee, $received) {
            self::credit(
                $from,
                -$amount,
                CoinTransaction::SOURCE_GIFT_OUT,
                "Подарок для {$to->username}",
                null,
                ['actor_id' => $from->id, 'meta' => ['to_user_id' => $to->id, 'fee' => $fee]]
            );

            self::credit(
                $to,
                $received,
                CoinTransaction::SOURCE_GIFT_IN,
                "Подарок от {$from->username}",
                null,
                ['actor_id' => $from->id, 'meta' => ['from_user_id' => $from->id, 'fee' => $fee]]
            );
        });

        return [$received, $fee];
    }

    public static function giftedToday(User $user): int
    {
        return (int) CoinTransaction::where('user_id', $user->id)
            ->where('source', CoinTransaction::SOURCE_GIFT_OUT)
            ->whereDate('created_at', today())
            ->sum(DB::raw('abs(amount)'));
    }

    public static function dailyBonusAvailable(User $user): bool
    {
        // Флаг читаем через настройки, а не напрямую из config:
        // иначе переключатель в админке ни на что не влиял.
        if (! ShopSettingService::getBool('daily_bonus.enabled')) {
            return false;
        }

        return ! CoinTransaction::where('user_id', $user->id)
            ->where('source', CoinTransaction::SOURCE_DAILY_BONUS)
            ->whereDate('created_at', today())
            ->exists();
    }

    /**
     * Выдать ежедневный бонус. Возвращает начисленную сумму или 0, если уже получал.
     */
    public static function claimDailyBonus(User $user): int
    {
        if (! self::dailyBonusAvailable($user)) {
            return 0;
        }

        $amount = ShopSettingService::getInt('daily_bonus.amount');

        return self::credit(
            $user,
            $amount,
            CoinTransaction::SOURCE_DAILY_BONUS,
            'Ежедневный бонус за вход',
            'daily_bonus:' . $user->id . ':' . today()->toDateString()
        );
    }

    public static function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return CoinTransaction::where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Сумма начислений по источникам — для витрины «откуда монеты».
     */
    public static function earnedBySource(User $user): array
    {
        // Ключи важны: фронтенд показывает расшифровку «откуда монеты».
        // array_values() здесь срезал названия источников.
        return CoinTransaction::where('user_id', $user->id)
            ->where('amount', '>', 0)
            ->selectRaw('source, sum(amount) as total')
            ->groupBy('source')
            ->pluck('total', 'source')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
