<?php

namespace Tests\Feature;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Досье: админ смотрит и меняет инвентарь чужого игрока.
 */
class AdminInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $type, string $value): ShopItem
    {
        return ShopItem::create([
            'slug' => "test-{$type}-{$value}", 'name' => "Тест {$value}", 'type' => $type,
            'effect_value' => $value, 'price' => 10, 'rarity' => 'rare', 'is_active' => true,
        ]);
    }

    public function test_admin_manages_player_inventory(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create();

        $pet = $this->item('profile_pet', 'fox');
        $priority = $this->item('tier_priority', 'priority');

        $petRow = UserInventory::create(['user_id' => $player->id, 'shop_item_id' => $pet->id, 'quantity' => 1, 'acquired_at' => now()]);
        $prioRow = UserInventory::create(['user_id' => $player->id, 'shop_item_id' => $priority->id, 'quantity' => 1, 'acquired_at' => now()]);

        $base = "/api/admin/users/{$player->id}/inventory";

        // Обычному игроку — нельзя
        $this->actingAs($player)->getJson($base)->assertForbidden();

        $this->actingAs($admin)->getJson($base)->assertOk()->assertJsonCount(2, 'items');

        // Надеть питомца игроку
        $this->actingAs($admin)->postJson("{$base}/items/{$pet->id}/equip")->assertOk();
        $this->assertSame('fox', $player->fresh()->profile_pet);

        // Перебросить черты: номер тот же
        $serial = $petRow->fresh()->pet_serial;
        $this->actingAs($admin)->postJson("{$base}/{$petRow->id}/reroll-pet")->assertOk();
        $this->assertSame($serial, $petRow->fresh()->pet_serial);

        // Количество
        $this->actingAs($admin)->putJson("{$base}/{$prioRow->id}", ['quantity' => 5])->assertOk();
        $this->assertSame(5, $prioRow->fresh()->quantity);

        // Забрать надетого питомца — снимается с профиля
        $this->actingAs($admin)->deleteJson("{$base}/{$petRow->id}")->assertOk()->assertJsonCount(1, 'items');
        $this->assertNull($player->fresh()->profile_pet);

        // Чужая строка инвентаря через этого игрока — 404
        $other = User::factory()->create();
        $foreign = UserInventory::create(['user_id' => $other->id, 'shop_item_id' => $priority->id, 'quantity' => 1, 'acquired_at' => now()]);
        $this->actingAs($admin)->deleteJson("{$base}/{$foreign->id}")->assertNotFound();
    }
}
