<?php

namespace Database\Seeders;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Services\ShopService;
use App\Domains\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DesolePetSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $owner = User::where('username', 'MyDesole')->lockForUpdate()->firstOrFail();
            $item = ShopItem::firstOrCreate(['slug' => 'desole-starlit-fox'], [
                'name' => 'Астер · звёздный лис', 'description' => 'Личный питомец MyDesole. Серебристый лис с хвостом северного сияния, живыми ушами и звёздочками.',
                'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'desole_fox', 'rarity' => 'legendary', 'price' => 0,
                'is_active' => false, 'is_consumable' => false, 'is_repeatable' => false, 'sort_order' => 0,
                'metadata' => ['icon' => 'sparkles', 'color' => '#b7c9fa', 'collection' => 'desole-signature', 'personal_owner' => 'MyDesole'],
            ]);
            ShopItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $existing = UserInventory::where('shop_item_id', $item->id)->get();
            if ($existing->count() > 1 || ($existing->isNotEmpty() && $existing->first()->user_id !== $owner->id)) {
                throw new RuntimeException('Personal pet already exists outside the target inventory.');
            }
            if ($existing->isNotEmpty()) {
                return;
            }
            $row = new UserInventory;
            $row->forceFill([
                'user_id' => $owner->id, 'shop_item_id' => $item->id, 'quantity' => 1, 'paid_price' => 0,
                'pet_name' => 'Астер', 'pet_traits' => ['model' => 'classic', 'backdrop' => 'none', 'accessory' => 'none'], 'acquired_at' => now(),
            ])->save();
            $equipped = UserInventory::where('user_id', $owner->id)->whereNotNull('equipped_at')
                ->whereHas('item', fn ($q) => $q->where('type', ShopItem::TYPE_PROFILE_PET))->count();
            if ($equipped < ShopService::MAX_PETS) {
                ShopService::equip($owner->refresh(), $item);
            }
        });
    }
}
