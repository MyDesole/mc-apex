<?php

namespace Database\Seeders;

use App\Domains\Shop\Models\ShopItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CloudKittenSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach ([
                ['cloud-kitten-frame', 'Рамка «Облачный котёнок»', 'avatar_frame', 'cloud_kitten', 65000, 'Жемчужная рамка с кошачьими ушками, розовым золотом, парящими сердечками и мягким блеском.'],
                ['cloud-hug-entrance', 'Появление «Облачные объятия»', 'profile_entrance', 'cloud_hug', 90000, 'Нежные облака раскрывают перламутровое сердечко с кошачьей лапкой. Маленькие сердечки поднимаются и мягко растворяются.'],
            ] as [$slug, $name, $type, $effect, $price, $description]) {
                ShopItem::firstOrCreate(['slug' => $slug], [
                    'name' => $name, 'description' => $description, 'type' => $type, 'effect_value' => $effect,
                    'rarity' => 'legendary', 'price' => $price, 'is_active' => true, 'is_consumable' => false, 'is_repeatable' => false,
                    'sort_order' => 0, 'metadata' => ['icon' => 'heart', 'color' => '#f2b8d0'],
                ]);
            }
        });
    }
}
