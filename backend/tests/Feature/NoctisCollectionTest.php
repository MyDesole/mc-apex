<?php

namespace Tests\Feature;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Services\BundleService;
use App\Domains\Users\Models\User;
use Database\Seeders\NoctisCollectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoctisCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_purchase_and_equip_deliver_all_five_cosmetic_slots(): void
    {
        $this->seed(NoctisCollectionSeeder::class);
        $bundle = ShopItem::where('slug', 'bundle-noctis')->firstOrFail();
        $user = User::factory()->create(['apex_coins' => 200000]);
        $this->actingAs($user)->postJson("/api/shop/{$bundle->id}/purchase")
            ->assertCreated()->assertJsonPath('paid', 149900)->assertJsonCount(5, 'granted');
        $this->assertSame(50100, (int) $user->fresh()->apex_coins);
        $this->assertSame(5, UserInventory::where('user_id', $user->id)->count());
        $this->postJson("/api/shop/{$bundle->id}/equip-bundle")->assertOk();
        $fresh = $user->fresh();
        foreach (['avatar_frame', 'skin_backdrop', 'profile_layout', 'name_effect', 'chat_bubble'] as $field) {
            $this->assertSame('noctis', $fresh->{$field});
        }
        $this->assertSame(5, UserInventory::where('user_id', $user->id)->whereNotNull('equipped_at')->count());
        $this->postJson("/api/shop/{$bundle->id}/purchase")->assertUnprocessable();
        $this->assertSame(50100, (int) $user->fresh()->apex_coins);
    }

    public function test_seed_is_idempotent_and_preserves_later_admin_price_changes(): void
    {
        $this->seed(NoctisCollectionSeeder::class);
        $bundle = ShopItem::where('slug', 'bundle-noctis')->firstOrFail();
        $bundle->update(['price' => 160000]);
        $this->seed(NoctisCollectionSeeder::class);
        $this->assertSame(6, ShopItem::where('effect_value', 'noctis')->count());
        $this->assertSame(160000, $bundle->fresh()->price);
    }

    public function test_collection_is_not_free_and_owned_parts_reduce_its_price(): void
    {
        $this->seed(NoctisCollectionSeeder::class);
        $bundle = ShopItem::where('slug', 'bundle-noctis')->firstOrFail();
        $user = User::factory()->create(['apex_coins' => 100]);
        $this->actingAs($user)->postJson("/api/shop/{$bundle->id}/purchase")->assertUnprocessable();
        $this->assertSame(0, UserInventory::where('user_id', $user->id)->count());
        $this->assertSame(100, (int) $user->fresh()->apex_coins);
        $parts = BundleService::items($bundle);
        $this->assertSame(215000, (int) $parts->sum('price'));
        $price = BundleService::pricing($bundle, $parts, [$parts->first()->id]);
        $this->assertLessThan(149900, $price['price']);
        $this->assertGreaterThan(0, $price['price']);
    }
}
