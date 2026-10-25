<?php

namespace App\Domains\Control\Services;

use App\Domains\Control\Models\PromoCode;
use App\Domains\Control\Models\PromoRedemption;
use App\Domains\Moderation\Notifications\ModerationNotification;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use App\Support\Concerns\AbortsWithMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Подарки от администрации: предмет игроку, раздача по аудитории
 * и промокоды. Монеты идут через CoinService (источник admin),
 * предметы — прямо в инвентарь, как при покупке.
 */
class GiftService
{
    use AbortsWithMessage;

    public const AUDIENCES = ['all', 'online', 'active_week', 'clan', 'tier'];

    private const ONLINE_MINUTES = 5;

    /** Положить предмет в инвентарь (заряды — как у покупки). */
    public function giveItem(User $user, ShopItem $item, ?User $from = null): void
    {
        $row = UserInventory::firstOrNew(['user_id' => $user->id, 'shop_item_id' => $item->id]);

        // Неповторяемый предмет второй раз не кладём
        if ($row->exists && ! $item->is_consumable && ! $item->is_repeatable) {
            $this->abortUnprocessable("У игрока уже есть «{$item->name}».");
        }

        $row->quantity = ($row->quantity ?? 0) + (int) ($item->metadata['charges'] ?? 1);
        $row->acquired_at ??= now();
        $row->gifted_by = $from?->id;
        $row->save();
    }

    /** Игроки выбранной аудитории (без забаненных). */
    public function audience(string $audience, ?int $clanId = null, ?string $tier = null): Builder
    {
        $query = User::query()->where('is_banned', false);

        return match ($audience) {
            'online' => $query->where('last_seen_at', '>=', now()->subMinutes(self::ONLINE_MINUTES)),
            'active_week' => $query->where('last_seen_at', '>=', now()->subDays(7)),
            'clan' => $query->whereHas('clanMember', fn ($q) => $q->where('clan_id', $clanId)),
            'tier' => $query->where('tier', $tier)->where('tier_score', '>', 0),
            default => $query,
        };
    }

    /**
     * Раздача: монеты и/или уведомление каждому из аудитории.
     *
     * @return int сколько игроков получили
     */
    public function giveaway(User $admin, Builder $audience, int $coins, ?string $message): int
    {
        $count = 0;

        $audience->select('id', 'username', 'apex_coins', 'apex_coins_spent')->chunkById(200, function ($users) use ($admin, $coins, $message, &$count) {
            foreach ($users as $user) {
                if ($coins !== 0) {
                    CoinService::credit(
                        $user,
                        $coins,
                        CoinTransaction::SOURCE_ADMIN,
                        $message ?: 'Подарок от администрации APEX',
                        null,
                        ['actor_id' => $admin->id, 'meta' => ['giveaway' => true]],
                    );
                }

                $text = $message ?: 'Подарок от администрации APEX';

                $user->notify(new ModerationNotification(
                    'announcement',
                    $coins > 0 ? "{$text} · +{$coins} ApexCoin" : $text,
                ));

                $count++;
            }
        });

        return $count;
    }

    /** Активировать промокод. */
    public function redeem(User $user, string $code): array
    {
        $promo = PromoCode::with('item')->whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($code))])->first();

        if (! $promo || ! $promo->is_active) {
            $this->abortUnprocessable('Такого промокода нет.');
        }

        if ($promo->expires_at && $promo->expires_at->isPast()) {
            $this->abortUnprocessable('Срок действия промокода истёк.');
        }

        if ($promo->max_uses !== null && $promo->uses >= $promo->max_uses) {
            $this->abortUnprocessable('Промокод уже закончился.');
        }

        if (PromoRedemption::where('promo_code_id', $promo->id)->where('user_id', $user->id)->exists()) {
            $this->abortUnprocessable('Вы уже активировали этот промокод.');
        }

        DB::transaction(function () use ($promo, $user) {
            PromoRedemption::create(['promo_code_id' => $promo->id, 'user_id' => $user->id]);
            $promo->increment('uses');

            if ($promo->coins > 0) {
                CoinService::credit(
                    $user,
                    $promo->coins,
                    CoinTransaction::SOURCE_ADMIN,
                    "Промокод {$promo->code}",
                    "promo:{$promo->id}:{$user->id}",
                    ['meta' => ['promo_code_id' => $promo->id]],
                );
            }

            if ($promo->item) {
                $row = UserInventory::firstOrNew(['user_id' => $user->id, 'shop_item_id' => $promo->item->id]);

                // Предмет, который уже есть, не дублируем — монеты всё равно начислены
                if (! $row->exists || $promo->item->is_consumable || $promo->item->is_repeatable) {
                    $row->quantity = ($row->quantity ?? 0) + (int) ($promo->item->metadata['charges'] ?? 1);
                    $row->acquired_at ??= now();
                    $row->save();
                }
            }
        });

        return [
            'coins' => $promo->coins,
            'item' => $promo->item ? ['id' => $promo->item->id, 'name' => $promo->item->name] : null,
            'balance' => (int) $user->fresh()->apex_coins,
        ];
    }
}
