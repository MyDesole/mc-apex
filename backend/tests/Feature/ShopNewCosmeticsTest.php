<?php

namespace Tests\Feature;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Users\Models\User;
use Database\Seeders\ShopItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Фон 3D-скина и тема карточки игрока: покупаются, надеваются и
 * снимаются как остальная косметика, а профиль видят и чужие игроки.
 */
class ShopNewCosmeticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShopItemSeeder::class);
    }

    private function buyAndEquip(User $user, string $slug): ShopItem
    {
        $item = ShopItem::where('slug', $slug)->firstOrFail();

        $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();
        $this->actingAs($user)->postJson("/api/shop/{$item->id}/equip")->assertOk();

        return $item;
    }

    public function test_skin_backdrop_is_written_to_profile_and_replaced(): void
    {
        $user = User::factory()->create(['apex_coins' => 20000]);

        $this->buyAndEquip($user, 'backdrop-nether');
        $this->assertSame('nether', $user->fresh()->skin_backdrop);

        // Второй фон того же слота заменяет первый
        $this->buyAndEquip($user, 'backdrop-end');
        $this->assertSame('end', $user->fresh()->skin_backdrop);

        $equipped = $this->actingAs($user)->getJson('/api/shop/inventory')->json('items');

        $this->assertSame(
            ['backdrop-end'],
            collect($equipped)
                ->where('equipped', true)
                ->where('type', ShopItem::TYPE_SKIN_BACKDROP)
                ->pluck('slug')
                ->values()
                ->all(),
        );
    }

    public function test_card_theme_can_be_unequipped(): void
    {
        $user = User::factory()->create(['apex_coins' => 20000]);

        $item = $this->buyAndEquip($user, 'card-theme-gold');
        $this->assertSame('gold', $user->fresh()->card_theme);

        $this->actingAs($user)->postJson("/api/shop/{$item->id}/unequip")->assertOk();
        $this->assertNull($user->fresh()->card_theme);
    }

    public function test_other_players_see_equipped_cosmetics_in_profile(): void
    {
        $owner = User::factory()->create(['apex_coins' => 20000, 'username' => 'desole']);
        $viewer = User::factory()->create();

        $this->buyAndEquip($owner, 'backdrop-deep-dark');
        $this->buyAndEquip($owner, 'card-theme-nether');

        $this->actingAs($viewer)->getJson('/api/players/desole')
            ->assertOk()
            ->assertJsonPath('user.skin_backdrop', 'deep_dark')
            ->assertJsonPath('user.card_theme', 'nether');
    }
}
