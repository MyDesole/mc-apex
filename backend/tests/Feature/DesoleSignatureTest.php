<?php

namespace Tests\Feature;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use Database\Seeders\DesoleSignatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesoleSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_cosmetics_cannot_be_bought_but_granted_owner_can_equip_without_spending(): void
    {
        $this->seed(DesoleSignatureSeeder::class);
        $this->seed(DesoleSignatureSeeder::class);
        $items = ShopItem::where('effect_value', 'desole_signature')->get();
        $this->assertCount(5, $items);
        $owner = User::factory()->create(['apex_coins' => 100, 'avatar' => 'original-avatar.gif']);
        foreach ($items as $item) {
            $this->assertFalse($item->is_active);
            $this->actingAs($owner)->postJson("/api/shop/{$item->id}/purchase")->assertUnprocessable();
            UserInventory::create(['user_id' => $owner->id, 'shop_item_id' => $item->id, 'quantity' => 1, 'acquired_at' => now()]);
            $this->postJson("/api/shop/{$item->id}/equip")->assertOk();
            $this->assertSame('desole_signature', $owner->fresh()->{$item->type});
        }
        $this->assertSame(100, (int) $owner->fresh()->apex_coins);
        $this->assertSame('original-avatar.gif', $owner->fresh()->avatar);
        $this->getJson('/api/shop')->assertOk()->assertJsonMissing(['effect_value' => 'desole_signature']);
    }
}
