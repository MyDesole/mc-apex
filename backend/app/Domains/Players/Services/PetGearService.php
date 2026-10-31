<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Support\Dungeons;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Экипировка питомцев: что надето, какие бонусы даёт, как надеть и снять.
 *
 * Предметы экипировки — обычные строки инвентаря с предметом магазина
 * типа pet_gear. Каждый экземпляр лежит отдельной строкой, чтобы два
 * одинаковых меча можно было надеть на двух разных питомцев.
 *
 * Что надето — в json-поле pet_gear у строки питомца: слот => id строки
 * экипировки. Один предмет нельзя надеть дважды: проверяем, что его id
 * не встречается у других питомцев.
 */
final class PetGearService
{
    /**
     * Бонусы от надетой экипировки.
     *
     * @return array{atk: int, def: int, hp: int, spd: int}
     */
    public function bonus(?UserInventory $pet): array
    {
        $bonus = ['atk' => 0, 'def' => 0, 'hp' => 0, 'spd' => 0];

        if (! $pet) {
            return $bonus;
        }

        foreach ($this->equipped($pet) as $gear) {
            $bonus['atk'] += (int) ($gear['atk'] ?? 0);
            $bonus['def'] += (int) ($gear['def'] ?? 0);
            $bonus['hp'] += (int) ($gear['hp'] ?? 0);
            $bonus['spd'] += (int) ($gear['spd'] ?? 0);
        }

        return $bonus;
    }

    /** Надетые предметы: слот => описание. */
    public function equipped(?UserInventory $pet): array
    {
        $map = $pet?->pet_gear;

        if (! is_array($map) || ! $map) {
            return [];
        }

        $ids = array_filter(array_map('intval', array_values($map)));

        if (! $ids) {
            return [];
        }

        $rows = UserInventory::with('item')->whereIn('id', $ids)->get();

        $result = [];

        foreach ($map as $slot => $id) {
            $row = $rows->firstWhere('id', (int) $id);

            if (! $row || ! $row->item) {
                continue;
            }

            $result[$slot] = $this->present($row);
        }

        return $result;
    }

    /** Вся экипировка игрока: что надето, на кого и какие характеристики. */
    public function bag(User $user): array
    {
        $rows = UserInventory::with('item')
            ->where('user_id', $user->id)
            ->whereHas('item', fn ($q) => $q->where('type', ShopItem::TYPE_PET_GEAR))
            ->orderByDesc('id')
            ->get();

        $pets = UserInventory::where('user_id', $user->id)->whereNotNull('pet_traits')->get(['id', 'pet_gear', 'pet_name']);

        // Куда надет каждый предмет: id строки экипировки => id питомца
        $worn = [];

        foreach ($pets as $pet) {
            foreach ((array) ($pet->pet_gear ?? []) as $id) {
                $worn[(int) $id] = $pet->id;
            }
        }

        return $rows->map(function (UserInventory $row) use ($worn) {
            $item = $this->present($row);

            $item['worn_by'] = $worn[$row->id] ?? null;

            return $item;
        })->values()->all();
    }

    /** Надеть предмет экипировки на питомца. */
    public function equip(User $user, int $petId, int $gearId): array
    {
        $pet = $this->ownPet($user, $petId);
        $gear = UserInventory::with('item')->where('user_id', $user->id)->find($gearId);

        if (! $gear || ! $gear->item || $gear->item->type !== ShopItem::TYPE_PET_GEAR) {
            throw new RuntimeException('Это не экипировка.');
        }

        $slot = (string) ($gear->item->metadata['slot'] ?? '');

        if (! in_array($slot, Dungeons::SLOTS, true)) {
            throw new RuntimeException('У этого предмета нет слота.');
        }

        if ($this->wornBy($user, $gear->id) !== null) {
            throw new RuntimeException('Этот предмет уже надет на другого питомца.');
        }

        $map = (array) ($pet->pet_gear ?? []);
        $map[$slot] = $gear->id;

        $pet->forceFill(['pet_gear' => $map])->save();

        return $this->petState($pet->fresh('item'));
    }

    /** Снять предмет со слота. */
    public function unequip(User $user, int $petId, string $slot): array
    {
        $pet = $this->ownPet($user, $petId);

        if (! in_array($slot, Dungeons::SLOTS, true)) {
            throw new RuntimeException('Такого слота нет.');
        }

        $map = (array) ($pet->pet_gear ?? []);

        if (! isset($map[$slot])) {
            throw new RuntimeException('На этом слоте ничего нет.');
        }

        unset($map[$slot]);

        $pet->forceFill(['pet_gear' => $map ?: null])->save();

        return $this->petState($pet->fresh('item'));
    }

    /** Сводка по питомцу для интерфейса: уровни, экипировка, бонусы. */
    public function petState(UserInventory $pet): array
    {
        $level = PetService::levels([$pet->id])[$pet->id] ?? 1;

        return [
            'inventory_id' => $pet->id,
            'name' => $pet->pet_name ?: ($pet->item?->name ?? 'Питомец'),
            'level' => $level,
            'gear' => $this->equipped($pet),
            'bonus' => $this->bonus($pet),
        ];
    }

    /** На каком питомце надет предмет, если надет. */
    private function wornBy(User $user, int $gearId): ?int
    {
        $pets = UserInventory::where('user_id', $user->id)->whereNotNull('pet_traits')->get(['id', 'pet_gear']);

        foreach ($pets as $pet) {
            foreach ((array) ($pet->pet_gear ?? []) as $id) {
                if ((int) $id === $gearId) {
                    return $pet->id;
                }
            }
        }

        return null;
    }

    private function ownPet(User $user, int $petId): UserInventory
    {
        $pet = UserInventory::with('item')
            ->where('user_id', $user->id)
            ->whereNotNull('pet_traits')
            ->find($petId);

        if (! $pet) {
            throw new RuntimeException('Питомец не найден.');
        }

        return $pet;
    }

    /** Описание предмета экипировки из metadata предмета магазина. */
    private function present(UserInventory $row): array
    {
        $meta = (array) ($row->item->metadata ?? []);
        $own = (array) ($row->gear_stats ?? []);
        $rarity = (string) ($own['rarity'] ?? $row->item->rarity ?? 'common');

        return [
            'inventory_id' => $row->id,
            'slug' => $row->item->slug,
            'name' => $row->item->name,
            'rarity' => $rarity,
            'rarity_name' => Dungeons::rarityName($rarity),
            'slot' => (string) ($meta['slot'] ?? ''),
            'slot_name' => Dungeons::slotName((string) ($meta['slot'] ?? '')),
            // Пиксель-арт для карточки: у каждого предмета свой спрайт
            'sprite' => $meta['sprite'] ?? null,
            'atk' => (int) ($own['atk'] ?? $meta['atk'] ?? 0),
            'def' => (int) ($own['def'] ?? $meta['def'] ?? 0),
            'hp' => (int) ($own['hp'] ?? $meta['hp'] ?? 0),
            'spd' => (int) ($own['spd'] ?? $meta['spd'] ?? 0),
        ];
    }

    /**
     * Выдать предмет экипировки: отдельной строкой инвентаря.
     *
     * Возвращает id строки или null, если предмета нет в базе (сидер не
     * запускали) — тогда добыча просто не выдаётся, чтобы не падать.
     */
    public function grant(User $user, string $slug, ?string $rarity = null): ?int
    {
        $item = ShopItem::where('slug', $slug)->first();

        if (! $item) {
            return null;
        }

        // Редкость и статы — свои у каждого экземпляра
        $rarity ??= 'common';
        $gear = Dungeons::GEAR[$slug] ?? ['atk' => 0, 'def' => 0, 'hp' => 0, 'spd' => 0];

        return DB::transaction(function () use ($user, $item, $rarity, $gear) {
            $row = UserInventory::create([
                'user_id' => $user->id,
                'shop_item_id' => $item->id,
                'quantity' => 1,
                'acquired_at' => now(),
                'gear_stats' => [
                    'rarity' => $rarity,
                    ...Dungeons::scaledStats($gear, $rarity),
                ],
            ]);

            return $row->id;
        });
    }
}
