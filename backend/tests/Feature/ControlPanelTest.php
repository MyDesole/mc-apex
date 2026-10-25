<?php

namespace Tests\Feature;

use App\Domains\Control\Models\PromoCode;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * «Пульт»: объявление, переключатели, событие xN, раздачи, промокоды, подарки.
 */
class ControlPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function control(array $data): void
    {
        $this->actingAs($this->admin)->putJson('/api/admin/control', $data)->assertOk();
    }

    public function test_announcement_is_public_and_expires(): void
    {
        $this->control(['announcement' => ['text' => 'Турнир в субботу!', 'tone' => 'success', 'link' => '/tournaments']]);

        $this->getJson('/api/site')
            ->assertOk()
            ->assertJsonPath('control.announcement.text', 'Турнир в субботу!');

        $this->control(['announcement' => ['text' => 'Старое', 'until' => now()->subMinute()->toIso8601String()]]);

        $this->getJson('/api/site')->assertOk()->assertJsonPath('control.announcement', null);
    }

    public function test_disabled_feature_blocks_players_but_not_admin(): void
    {
        $item = ShopItem::create(['slug' => 'test-frame', 'name' => 'Рамка', 'type' => 'avatar_frame', 'effect_value' => 'gold', 'price' => 10, 'is_active' => true]);
        $player = User::factory()->create(['apex_coins' => 100]);

        $this->control(['features' => ['shop' => false]]);

        $this->actingAs($player)->postJson("/api/shop/{$item->id}/purchase")
            ->assertStatus(423)
            ->assertJsonPath('feature', 'shop');

        $this->actingAs($this->admin)->postJson("/api/shop/{$item->id}/purchase")->assertSuccessful();

        $this->control(['features' => ['shop' => true]]);
        $this->actingAs($player)->postJson("/api/shop/{$item->id}/purchase")->assertSuccessful();
    }

    public function test_coin_event_multiplies_earnings_only(): void
    {
        $player = User::factory()->create(['apex_coins' => 0]);
        $this->control(['multiplier' => 2, 'multiplier_until' => now()->addDay()->toIso8601String()]);

        CoinService::credit($player, 100, CoinTransaction::SOURCE_ACHIEVEMENT, 'Ачивка');
        CoinService::credit($player, 50, CoinTransaction::SOURCE_ADMIN, 'Подарок');

        $this->assertSame(250, $player->fresh()->apex_coins);
        $this->getJson('/api/site')->assertJsonPath('control.event.multiplier', 2);

        // Событие кончилось — множитель 1
        $this->control(['multiplier' => 2, 'multiplier_until' => now()->subMinute()->toIso8601String()]);
        CoinService::credit($player, 100, CoinTransaction::SOURCE_ACHIEVEMENT, 'Ачивка');
        $this->assertSame(350, $player->fresh()->apex_coins);
    }

    public function test_giveaway_counts_and_credits_audience(): void
    {
        User::factory()->count(2)->create(['apex_coins' => 0, 'last_seen_at' => now()]);
        User::factory()->create(['apex_coins' => 0, 'last_seen_at' => now()->subDays(3)]);
        User::factory()->create(['apex_coins' => 0, 'last_seen_at' => now(), 'is_banned' => true]);

        // Онлайн: два игрока и сам админ (его запрос отметил его активным); забаненный — нет
        $this->actingAs($this->admin)->postJson('/api/admin/control/giveaway', ['audience' => 'online', 'dry_run' => true])
            ->assertOk()->assertJsonPath('count', 3);

        $this->actingAs($this->admin)->postJson('/api/admin/control/giveaway', ['audience' => 'online', 'coins' => 300, 'message' => 'С праздником!'])
            ->assertOk()->assertJsonPath('count', 3);

        $this->assertSame(2, User::where('apex_coins', 300)->count());
        $this->assertSame(5300, $this->admin->fresh()->apex_coins);
        $this->assertSame(0, User::where('is_banned', true)->value('apex_coins'));
    }

    public function test_promo_code_once_per_player_with_limit(): void
    {
        $this->actingAs($this->admin)->postJson('/api/admin/control/promo-codes', ['code' => 'apex2026', 'coins' => 500, 'max_uses' => 1])
            ->assertCreated()->assertJsonPath('promo_code.code', 'APEX2026');

        $first = User::factory()->create(['apex_coins' => 0]);
        $second = User::factory()->create(['apex_coins' => 0]);

        $this->actingAs($first)->postJson('/api/promo/redeem', ['code' => 'Apex2026'])->assertOk()->assertJsonPath('balance', 500);
        $this->actingAs($first)->postJson('/api/promo/redeem', ['code' => 'APEX2026'])->assertUnprocessable();
        $this->actingAs($second)->postJson('/api/promo/redeem', ['code' => 'APEX2026'])->assertUnprocessable();
        $this->actingAs($second)->postJson('/api/promo/redeem', ['code' => 'NOPE'])->assertUnprocessable();

        $this->assertSame(1, PromoCode::first()->uses);
    }

    public function test_gift_item_and_online_list(): void
    {
        $item = ShopItem::create(['slug' => 'gift-frame', 'name' => 'Подарочная рамка', 'type' => 'avatar_frame', 'effect_value' => 'gold', 'price' => 10, 'is_active' => true]);
        $player = User::factory()->create(['last_seen_at' => now()]);

        $this->actingAs($this->admin)->postJson("/api/admin/users/{$player->id}/give-item", ['shop_item_id' => $item->id])->assertOk();
        $this->actingAs($this->admin)->postJson("/api/admin/users/{$player->id}/give-item", ['shop_item_id' => $item->id])->assertUnprocessable();

        $this->assertDatabaseHas('user_inventory', ['user_id' => $player->id, 'shop_item_id' => $item->id, 'gifted_by' => $this->admin->id]);

        $this->actingAs($this->admin)->getJson('/api/admin/control/online')
            ->assertOk()
            ->assertJsonFragment(['id' => $player->id]);

        $moderator = User::factory()->create(['role' => 'moderator']);
        $this->actingAs($moderator)->getJson('/api/admin/control')->assertForbidden();
    }
}
