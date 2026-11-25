<?php

namespace Database\Seeders;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class KllivvKoalaSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $owner = User::where('username', 'kllivv')->lockForUpdate()->firstOrFail();
            $item = ShopItem::firstOrCreate(['slug' => 'kllivv-cozy-koala'], [
                'name' => 'Моти · коала', 'description' => 'Личный питомец kllivv. Пушистая коала с веточкой эвкалипта, живыми ушками и сонной улыбкой.',
                'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'kllivv_koala', 'rarity' => 'legendary', 'price' => 0,
                'is_active' => false, 'is_consumable' => false, 'is_repeatable' => false, 'sort_order' => 0,
                'metadata' => ['icon' => 'sparkles', 'color' => '#b7c9fa', 'collection' => 'kllivv-personal', 'personal_owner' => 'kllivv'],
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
                'pet_name' => 'Моти', 'pet_traits' => ['model' => 'classic', 'backdrop' => 'none', 'accessory' => 'none'], 'acquired_at' => now(),
            ])->save();
        });
    }
}
