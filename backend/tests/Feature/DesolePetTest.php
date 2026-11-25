<?php

namespace Tests\Feature;

use App\Domains\Players\Services\PetService;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Services\ShopService;
use App\Domains\Users\Models\User;
use Database\Seeders\DesolePetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesolePetTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_pet_is_unique_careable_and_preserves_both_equipped_pets(): void
    {
        $owner = User::factory()->create(['username' => 'MyDesole', 'apex_coins' => 100]);
        foreach (['cat', 'dog'] as $species) {
            $item = ShopItem::create(['name' => $species, 'slug' => 'test-'.$species, 'type' => 'profile_pet', 'effect_value' => $species, 'rarity' => 'common', 'price' => 0, 'is_active' => false]);
            UserInventory::create(['user_id' => $owner->id, 'shop_item_id' => $item->id, 'quantity' => 1, 'acquired_at' => now()]);
            ShopService::equip($owner->refresh(), $item);
        }
        $before = collect($owner->fresh()->profile_pets)->pluck('inventory_id');
        $this->seed(DesolePetSeeder::class);
        $this->seed(DesolePetSeeder::class);
        $pet = ShopItem::where('slug', 'desole-starlit-fox')->firstOrFail();
        $this->assertSame(1, UserInventory::where('shop_item_id', $pet->id)->count());
        $row = UserInventory::where('shop_item_id', $pet->id)->firstOrFail();
        $this->assertSame($owner->id, $row->user_id);
        $this->assertSame('Астер', $row->pet_name);
        $walking = collect($owner->fresh()->profile_pets);
        $this->assertCount(3, $walking);
        $this->assertEmpty($before->diff($walking->pluck('inventory_id')));
        $this->assertSame(100, (int) $owner->fresh()->apex_coins);
        $other = User::factory()->create();
        $this->actingAs($other)->postJson("/api/shop/{$pet->id}/purchase")->assertUnprocessable();
        $stats = app(PetService::class)->interact($owner->fresh(), $owner, 'feed', $row->id);
        $this->assertSame('Астер', $stats['name']);
        $this->assertGreaterThan(0, $stats['xp']);
    }

    public function test_full_pet_slots_are_preserved_and_personal_pet_stays_in_inventory(): void
    {
        $owner = User::factory()->create(['username' => 'MyDesole']);
        foreach (['cat', 'dog', 'fox'] as $species) {
            $item = ShopItem::create(['name' => $species, 'slug' => 'full-'.$species, 'type' => 'profile_pet', 'effect_value' => $species, 'rarity' => 'common', 'price' => 0, 'is_active' => false]);
            UserInventory::create(['user_id' => $owner->id, 'shop_item_id' => $item->id, 'quantity' => 1, 'acquired_at' => now()]);
            ShopService::equip($owner->refresh(), $item);
        }
        $before = $owner->fresh()->profile_pets;
        $this->seed(DesolePetSeeder::class);
        $this->assertSame($before, $owner->fresh()->profile_pets);
        $this->assertSame(4, UserInventory::where('user_id', $owner->id)->count());
    }
}
