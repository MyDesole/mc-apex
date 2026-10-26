<?php

namespace App\Domains\Shop\Services;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Стикеры в чате. Набор — предмет магазина (sticker_pack): effect_value
 * — ключ набора, metadata.stickers — ключи стикеров. Стикер уходит в
 * чат текстом «[sticker:набор/стикер]», страница рисует его картинкой.
 */
class StickerService
{
    public const PATTERN = '/^\[sticker:([a-z0-9_]+)\/([a-z0-9_]+)\]$/';

    /** Наборы, из которых игрок может отправлять стикеры. */
    public function ownedPacks(User $user): Collection
    {
        $packs = ShopItem::where('type', ShopItem::TYPE_STICKER_PACK)->orderBy('sort_order')->get();

        if (! ShopService::ownsAllCosmetics($user)) {
            $owned = UserInventory::where('user_id', $user->id)->pluck('shop_item_id')->all();
            $packs = $packs->filter(fn (ShopItem $pack) => in_array($pack->id, $owned));
        }

        return $packs->map(fn (ShopItem $pack) => [
            'id' => $pack->effect_value,
            'name' => $pack->name,
            'stickers' => array_values($pack->metadata['stickers'] ?? []),
        ])->values();
    }

    /**
     * Стикер можно отправить, только если набор есть у игрока. Обычный
     * текст пропускаем как есть.
     */
    public function guard(User $user, string $body): void
    {
        if (! preg_match(self::PATTERN, $body, $match)) {
            return;
        }

        [, $packKey, $stickerKey] = $match;

        $pack = $this->ownedPacks($user)->firstWhere('id', $packKey);

        if (! $pack || ! in_array($stickerKey, $pack['stickers'], true)) {
            throw ValidationException::withMessages([
                'body' => ['Этого стикера у вас нет — набор можно купить в магазине.'],
            ]);
        }
    }
}
