<?php

namespace Tests\Feature;

use App\Domains\Friends\Models\Friendship;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Продажа предмета магазину и подарок другу.
 */
class InventoryTradeTest extends TestCase
{
    use RefreshDatabase;

    private function frame(int $price = 1000): ShopItem
    {
        return ShopItem::create([
            'slug' => 'frame-' . $price, 'name' => 'Рамка', 'type' => 'avatar_frame',
            'effect_value' => 'gold', 'price' => $price, 'is_active' => true, 'rarity' => 'rare',
        ]);
    }

    public function test_bought_item_sells_for_half_and_case_drop_for_less(): void
    {
        $item = $this->frame();
        $user = User::factory()->create(['apex_coins' => 1000]);

        $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertSuccessful();
        $this->actingAs($user)->postJson("/api/shop/{$item->id}/equip")->assertOk();

        $row = UserInventory::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1000, (int) $row->paid_price);

        // Надетое тоже продаётся: сначала снимаем
        $this->actingAs($user)->postJson("/api/shop/inventory/{$row->id}/sell")
            ->assertOk()
            ->assertJsonPath('coins', 500)
            ->assertJsonPath('balance', 500);

        $this->assertDatabaseMissing('user_inventory', ['id' => $row->id]);
        $this->assertSame('default', $user->fresh()->avatar_frame);

        // Выпало из сундука (без цены покупки) — 15%
        $drop = UserInventory::create(['user_id' => $user->id, 'shop_item_id' => $this->frame(2000)->id, 'quantity' => 1]);
        $this->actingAs($user)->postJson("/api/shop/inventory/{$drop->id}/sell")->assertOk()->assertJsonPath('coins', 300);

        // Чужое — нельзя
        $other = UserInventory::create(['user_id' => User::factory()->create()->id, 'shop_item_id' => $item->id, 'quantity' => 1]);
        $this->actingAs($user)->postJson("/api/shop/inventory/{$other->id}/sell")->assertNotFound();
    }

    public function test_gift_only_to_friends_and_resells_cheaper(): void
    {
        $item = $this->frame();
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();

        $row = UserInventory::create(['user_id' => $user->id, 'shop_item_id' => $item->id, 'quantity' => 1, 'paid_price' => 1000]);

        $this->actingAs($user)->postJson("/api/shop/inventory/{$row->id}/gift", ['user_id' => $stranger->id])->assertStatus(422);

        Friendship::create(['user_id' => $user->id, 'friend_id' => $friend->id, 'status' => 'accepted']);

        $this->actingAs($user)->postJson("/api/shop/inventory/{$row->id}/gift", ['user_id' => $friend->id])->assertOk();

        $row->refresh();
        $this->assertSame($friend->id, $row->user_id);
        $this->assertSame($user->id, $row->gifted_by);
        $this->assertNull($row->paid_price);
        $this->assertSame(1, $friend->notifications()->count());

        // У друга подарок продаётся как выпавший — 15%, монеты через подарки не отмыть
        $this->actingAs($friend)->postJson("/api/shop/inventory/{$row->id}/sell")->assertOk()->assertJsonPath('coins', 150);
    }
}
