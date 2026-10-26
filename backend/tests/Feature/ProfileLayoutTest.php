<?php

namespace Tests\Feature;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Макет профиля из магазина: покупка, надевание, отдача в профиле, снятие.
 */
class ProfileLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_buys_equips_and_unequips_profile_layout(): void
    {
        $this->seed(\Database\Seeders\ShopItemSeeder::class);

        $user = User::factory()->create(['apex_coins' => 20000]);
        $item = ShopItem::where('slug', 'layout-book')->firstOrFail();

        $this->assertSame('profile_layout', $item->type);
        $this->assertTrue($item->isEquippable());

        $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();
        $this->actingAs($user)->postJson("/api/shop/{$item->id}/equip")->assertOk();

        $this->assertSame('book', $user->fresh()->profile_layout);
        $this->getJson("/api/players/{$user->id}")->assertOk()->assertJsonPath('user.profile_layout', 'book');

        $this->actingAs($user)->postJson("/api/shop/{$item->id}/unequip")->assertOk();
        $this->assertNull($user->fresh()->profile_layout);
    }

    public function test_shop_lists_all_layouts(): void
    {
        $this->seed(\Database\Seeders\ShopItemSeeder::class);

        $layouts = collect($this->getJson('/api/shop')->assertOk()->json('items'))
            ->where('type', 'profile_layout');

        $this->assertCount(16, $layouts);
        $this->assertTrue($layouts->every(fn ($i) => $i['is_equippable']));
    }
}
