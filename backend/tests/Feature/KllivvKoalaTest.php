<?php

namespace Tests\Feature;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Services\ShopService;
use App\Domains\Users\Models\User;
use Database\Seeders\KllivvKoalaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KllivvKoalaTest extends TestCase
{
    use RefreshDatabase;

    public function test_koala_is_granted_once_and_never_equipped_or_swapped_with_current_pets(): void
    {
        $owner = User::factory()->create(['username' => 'kllivv', 'apex_coins' => 12345, 'avatar' => 'unchanged.gif']);
        foreach (['cat', 'dragon', 'owl'] as $species) {
            $item = ShopItem::create(['name' => $species, 'slug' => 'test-'.$species, 'type' => 'profile_pet', 'effect_value' => $species, 'rarity' => 'common', 'price' => 0, 'is_active' => false]);
            UserInventory::create(['user_id' => $owner->id, 'shop_item_id' => $item->id, 'quantity' => 1, 'acquired_at' => now()]);
            ShopService::equip($owner->refresh(), $item);
        }
        $before = $owner->fresh()->only(['profile_pet', 'profile_pets', 'pet_traits', 'avatar', 'apex_coins']);
        $equipped = UserInventory::where('user_id', $owner->id)->whereNotNull('equipped_at')->pluck('id')->all();
        $this->seed(KllivvKoalaSeeder::class);
        $this->seed(KllivvKoalaSeeder::class);
        $item = ShopItem::where('slug', 'kllivv-cozy-koala')->firstOrFail();
        $rows = UserInventory::where('shop_item_id', $item->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame($owner->id, $rows->first()->user_id);
        $this->assertSame('Моти', $rows->first()->pet_name);
        $this->assertNull($rows->first()->equipped_at);
        $this->assertSame($before, $owner->fresh()->only(array_keys($before)));
        $this->assertSame($equipped, UserInventory::where('user_id', $owner->id)->whereNotNull('equipped_at')->pluck('id')->all());
        $this->actingAs($owner)->getJson('/api/shop/inventory')->assertOk()->assertJsonFragment(['effect_value' => 'kllivv_koala', 'equipped' => false]);
        $other = User::factory()->create();
        $this->actingAs($other)->postJson("/api/shop/{$item->id}/purchase")->assertUnprocessable();
    }
}
