<?php

namespace Database\Seeders;

use App\Domains\Shop\Models\ShopItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DesoleSignatureSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (['avatar_frame' => 'Рамка', 'profile_layout' => 'Профиль', 'skin_backdrop' => 'Фон скина', 'name_effect' => 'Эффект ника', 'profile_entrance' => 'Появление'] as $type => $name) {
                ShopItem::firstOrCreate(['slug' => 'desole-signature-'.$type], [
                    'name' => $name.' «MyDesole · Signature»', 'description' => 'Персональное оформление MyDesole: серебро, обсидиан и северное сияние.',
                    'type' => $type, 'effect_value' => 'desole_signature', 'rarity' => 'legendary', 'price' => 0,
                    'is_active' => false, 'is_consumable' => false, 'is_repeatable' => false, 'sort_order' => 0,
                    'metadata' => ['icon' => 'sparkles', 'color' => '#c1b8ed', 'collection' => 'desole-signature'],
                ]);
            }
        });
    }
}
