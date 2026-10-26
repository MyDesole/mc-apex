<?php

namespace Tests\Feature;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Наборы косметики и бесплатная косметика для админов.
 */
class ShopBundleTest extends TestCase
{
    use RefreshDatabase;

    private ShopItem $frame;
    private ShopItem $effect;
    private ShopItem $exclusive;
    private ShopItem $bundle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->frame = $this->item('frame-test', ShopItem::TYPE_AVATAR_FRAME, 'test', 1000);
        $this->effect = $this->item('effect-test', ShopItem::TYPE_PROFILE_EFFECT, 'test', 1000);
        // Эксклюзив набора: отдельно не продаётся
        $this->exclusive = $this->item('badge-test', ShopItem::TYPE_BADGE, 'test', 2000, false);

        $this->bundle = ShopItem::create([
            'slug' => 'bundle-test', 'name' => 'Набор', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'test',
            'rarity' => 'epic', 'price' => 2000, 'is_active' => true,
            'metadata' => ['items' => ['frame-test', 'effect-test', 'badge-test'], 'theme' => 'nether'],
        ]);
    }

    private function item(string $slug, string $type, string $value, int $price, bool $active = true): ShopItem
    {
        return ShopItem::create([
            'slug' => $slug, 'name' => $slug, 'type' => $type, 'effect_value' => $value,
            'rarity' => 'rare', 'price' => $price, 'is_active' => $active,
        ]);
    }

    public function test_bundle_grants_all_items_including_exclusive(): void
    {
        $user = User::factory()->create(['apex_coins' => 5000]);

        $this->actingAs($user)->postJson("/api/shop/{$this->bundle->id}/purchase")
            ->assertCreated()
            ->assertJsonCount(3, 'granted')
            ->assertJsonPath('paid', 2000);

        $this->assertSame(3000, (int) $user->fresh()->apex_coins);
        $this->assertSame(3, UserInventory::where('user_id', $user->id)->count());

        // Повторно купить нельзя — всё уже есть
        $this->actingAs($user)->postJson("/api/shop/{$this->bundle->id}/purchase")->assertStatus(422);
    }

    public function test_bundle_price_drops_for_owned_items(): void
    {
        $user = User::factory()->create(['apex_coins' => 5000]);
        UserInventory::create(['user_id' => $user->id, 'shop_item_id' => $this->frame->id, 'quantity' => 1]);

        // Ценность 4000, недостаёт на 3000 → 2000 × 3/4 = 1500
        $catalog = collect($this->actingAs($user)->getJson('/api/shop')->json('items') ?? $this->actingAs($user)->getJson('/api/shop')->json());
        $bundle = $catalog->firstWhere('slug', 'bundle-test');

        $this->assertSame(1500, $bundle['bundle']['price_for_you']);
        $this->assertTrue($bundle['bundle']['items'][2]['exclusive']);

        $this->actingAs($user)->postJson("/api/shop/{$this->bundle->id}/purchase")
            ->assertCreated()
            ->assertJsonCount(2, 'granted')
            ->assertJsonPath('paid', 1500);
    }

    public function test_exclusive_item_is_not_sold_separately(): void
    {
        $user = User::factory()->create(['apex_coins' => 5000]);

        $this->actingAs($user)->postJson("/api/shop/{$this->exclusive->id}/purchase")->assertStatus(422);
    }

    public function test_admin_has_all_cosmetics_for_free(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'apex_coins' => 0]);

        $inventory = collect($this->actingAs($admin)->getJson('/api/shop/inventory')->json('items') ?? []);
        if ($inventory->isEmpty()) {
            $inventory = collect($this->actingAs($admin)->getJson('/api/shop/inventory')->json());
        }

        $this->assertTrue($inventory->contains('slug', 'frame-test'));
        $this->assertTrue($inventory->contains('slug', 'badge-test'));
        $this->assertFalse($inventory->contains('slug', 'bundle-test'));

        // Надевается без покупки — запись появляется сама
        $this->actingAs($admin)->postJson("/api/shop/{$this->frame->id}/equip")->assertOk();
        $this->assertSame('test', $admin->fresh()->avatar_frame);

        // Купить за монеты админу не нужно
        $this->actingAs($admin)->postJson("/api/shop/{$this->effect->id}/purchase")->assertStatus(422);
    }

    public function test_bundle_equips_all_owned_items_at_once(): void
    {
        $user = User::factory()->create(['apex_coins' => 5000]);
        $this->actingAs($user)->postJson("/api/shop/{$this->bundle->id}/purchase")->assertCreated();

        $inventory = $this->actingAs($user)->getJson('/api/shop/inventory')->assertOk();
        $inventory->assertJsonPath('bundles.0.slug', 'bundle-test')
            ->assertJsonPath('bundles.0.equipped_all', false);

        $this->actingAs($user)->postJson("/api/shop/{$this->bundle->id}/equip-bundle")
            ->assertOk()
            ->assertJsonPath('bundles.0.equipped_all', true);

        $user->refresh();
        $this->assertSame('test', $user->avatar_frame);
        $this->assertSame('badge-test', $user->equipped_badges[0]['slug']);
    }

    public function test_bundle_equip_needs_at_least_one_item(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/api/shop/{$this->bundle->id}/equip-bundle")->assertStatus(422);
    }

    public function test_randomize_equips_random_owned_items(): void
    {
        $user = User::factory()->create(['apex_coins' => 5000]);

        // Пусто — перемешивать нечего
        $this->actingAs($user)->postJson('/api/shop/randomize')->assertStatus(422);

        $this->actingAs($user)->postJson("/api/shop/{$this->bundle->id}/purchase")->assertCreated();

        $this->actingAs($user)->postJson('/api/shop/randomize')->assertOk();

        $user->refresh();
        $this->assertSame('test', $user->avatar_frame);
        $this->assertSame('test', $user->profile_effect);
        $this->assertCount(1, $user->equipped_badges);
    }

    public function test_flex_cosmetics_equip_into_user_fields(): void
    {
        $user = User::factory()->create(['apex_coins' => 10000]);

        foreach ([
            [ShopItem::TYPE_NAME_EFFECT, 'gold', 'name_effect'],
            [ShopItem::TYPE_PROFILE_ENTRANCE, 'portal', 'profile_entrance'],
            [ShopItem::TYPE_CURSOR_TRAIL, 'sparkles', 'cursor_trail'],
        ] as [$type, $value, $field]) {
            $item = $this->item("{$type}-x", $type, $value, 100);

            $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();
            $this->actingAs($user)->postJson("/api/shop/{$item->id}/equip")->assertOk();
            $this->assertSame($value, $user->fresh()->{$field});

            $this->actingAs($user)->postJson("/api/shop/{$item->id}/unequip")->assertOk();
            $this->assertNull($user->fresh()->{$field});
        }
    }

    public function test_regular_player_cannot_equip_without_buying(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/api/shop/{$this->frame->id}/equip")->assertStatus(422);
    }
}
