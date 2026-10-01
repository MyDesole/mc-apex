<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\ShopItem;
use App\Models\TierTest;
use App\Models\User;
use App\Services\CoinService;
use App\Services\RewardService;
use Database\Seeders\ShopItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApexShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShopItemSeeder::class);
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'apex_coins' => 5000,
        ], $attributes));
    }

    public function test_shop_catalog_is_public_and_hides_nothing(): void
    {
        $response = $this->getJson('/api/shop');

        $response->assertOk()
            ->assertJsonStructure(['items', 'tier_rewards', 'currency']);

        $this->assertNotEmpty($response->json('items'));
    }

    public function test_user_can_buy_cosmetic_and_it_appears_in_inventory(): void
    {
        $user = $this->user();
        $item = ShopItem::where('slug', 'frame-gold')->firstOrFail();

        $response = $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase");

        $response->assertCreated();
        $this->assertSame(5000 - $item->price, $response->json('balance'));

        $this->assertDatabaseHas('user_inventory', [
            'user_id' => $user->id,
            'shop_item_id' => $item->id,
        ]);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $user->id,
            'source' => 'purchase',
            'amount' => -$item->price,
        ]);
    }

    public function test_purchase_fails_without_enough_coins(): void
    {
        $user = $this->user(['apex_coins' => 10]);
        $item = ShopItem::where('slug', 'frame-legendary')->firstOrFail();

        $this->actingAs($user)
            ->postJson("/api/shop/{$item->id}/purchase")
            ->assertStatus(422);

        $this->assertDatabaseMissing('user_inventory', [
            'user_id' => $user->id,
            'shop_item_id' => $item->id,
        ]);
    }

    public function test_equipped_frame_is_written_to_profile(): void
    {
        $user = $this->user();
        $item = ShopItem::where('slug', 'frame-rainbow')->firstOrFail();

        $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();
        $this->actingAs($user)->postJson("/api/shop/{$item->id}/equip")->assertOk();

        $this->assertSame('rainbow', $user->fresh()->avatar_frame);
    }

    public function test_tier_test_completion_awards_coins_idempotently(): void
    {
        $user = $this->user(['apex_coins' => 0]);

        $test = TierTest::create([
            'user_id' => $user->id,
            'mode' => 'pvp',
            'contact_type' => 'discord',
            'contact_value' => 'apex#1',
            'preferred_time' => 'вечер',
            'status' => 'completed',
            'result_tier' => 'B',
            'result_score' => 60,
            'completed_at' => now(),
        ]);

        $expected = RewardService::coinsForTier('B');

        $this->assertGreaterThan(0, $expected);

        RewardService::forTierTest($test);
        $first = $user->fresh()->apex_coins;

        // Повторный вызов не должен начислить второй раз
        RewardService::forTierTest($test->fresh());

        $this->assertSame($first, $user->fresh()->apex_coins);
        $this->assertSame($expected, $first);
    }

    public function test_achievement_awards_coins_once(): void
    {
        $user = $this->user(['apex_coins' => 0]);

        $achievement = Achievement::create([
            'code' => 'test_coin_achievement',
            'name' => 'Тестовая ачивка',
            'description' => 'Создана тестом ApexShop для проверки начисления монет',
            'icon' => 'coin',
            'color' => '#facc15',
            'rarity' => 'common',
            'points' => 10,
            'is_active' => true,
            'is_system' => false,
        ]);

        RewardService::forAchievement($user, $achievement);
        $balance = $user->fresh()->apex_coins;

        RewardService::forAchievement($user, $achievement);

        $this->assertGreaterThan(0, $balance);
        $this->assertSame($balance, $user->fresh()->apex_coins);
    }

    public function test_gift_requires_friendship(): void
    {
        $sender = $this->user();
        $stranger = $this->user();

        $this->actingAs($sender)
            ->postJson("/api/gifts/{$stranger->id}", ['amount' => 100])
            ->assertStatus(422);

        $this->assertSame(5000, $sender->fresh()->apex_coins);
    }

    public function test_gift_transfers_coins_with_fee(): void
    {
        $sender = $this->user();
        $friend = $this->user(['apex_coins' => 0]);

        \App\Models\Friendship::create([
            'user_id' => $sender->id,
            'friend_id' => $friend->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($sender)
            ->postJson("/api/gifts/{$friend->id}", ['amount' => 1000]);

        $response->assertCreated();

        $fee = (int) floor(1000 * config('apex.coins.gift.fee_percent') / 100);

        $this->assertSame(5000 - 1000, $sender->fresh()->apex_coins);
        $this->assertSame(1000 - $fee, $friend->fresh()->apex_coins);
    }

    public function test_buying_priority_moves_request_to_front_of_tester_queue(): void
    {
        $player = $this->user(['apex_coins' => 5000]);
        $tester = $this->user(['role' => 'tester']);

        $old = TierTest::create([
            'user_id' => $player->id, 'mode' => 'pvp', 'contact_type' => 'discord',
            'contact_value' => 'a', 'preferred_time' => 'x', 'status' => 'pending',
            'created_at' => now()->subDays(2),
        ]);

        $new = TierTest::create([
            'user_id' => $player->id, 'mode' => 'bedwars', 'contact_type' => 'discord',
            'contact_value' => 'b', 'preferred_time' => 'x', 'status' => 'pending',
        ]);

        $item = ShopItem::where('slug', 'tier-priority-pass')->firstOrFail();

        $this->actingAs($player)
            ->postJson("/api/shop/{$item->id}/purchase", ['tier_test_id' => $new->id])
            ->assertCreated();

        $this->assertTrue($new->fresh()->is_priority);

        $queue = $this->actingAs($tester)
            ->getJson('/api/tester/tier-tests?status=pending')
            ->json('data');

        $this->assertSame($new->id, $queue[0]['id']);
    }

    public function test_priority_can_be_bought_in_advance_and_applied_later(): void
    {
        // Игрок покупает приоритет ДО создания заявки — заряд ждёт в инвентаре
        $player = $this->user(['apex_coins' => 5000]);
        $item = ShopItem::where('slug', 'tier-priority-pass')->firstOrFail();

        $this->actingAs($player)
            ->postJson("/api/shop/{$item->id}/purchase")
            ->assertCreated()
            ->assertJsonPath('tier_test', null);

        $this->assertSame(1, \App\Services\ShopService::priorityCharges($player));

        // Только теперь появляется заявка
        $test = TierTest::create([
            'user_id' => $player->id, 'mode' => 'pvp', 'contact_type' => 'discord',
            'contact_value' => 'a', 'preferred_time' => 'x', 'status' => 'pending',
        ]);

        $this->actingAs($player)
            ->postJson("/api/tier-tests/{$test->id}/priority")
            ->assertOk()
            ->assertJsonPath('charges', 0);

        $this->assertTrue($test->fresh()->is_priority);

        // Заряд израсходован — повторно применить нечего
        $second = TierTest::create([
            'user_id' => $player->id, 'mode' => 'bedwars', 'contact_type' => 'discord',
            'contact_value' => 'b', 'preferred_time' => 'x', 'status' => 'pending',
        ]);

        $this->actingAs($player)
            ->postJson("/api/tier-tests/{$second->id}/priority")
            ->assertStatus(422);

        $this->assertFalse($second->fresh()->is_priority);
    }

    public function test_priority_pack_gives_five_charges(): void
    {
        $player = $this->user(['apex_coins' => 10000]);
        $pack = ShopItem::where('slug', 'tier-priority-pass-x5')->firstOrFail();

        $this->actingAs($player)->postJson("/api/shop/{$pack->id}/purchase")->assertCreated();

        $this->assertSame(5, \App\Services\ShopService::priorityCharges($player));
    }

    public function test_priority_is_not_applied_to_foreign_request(): void
    {
        $player = $this->user(['apex_coins' => 5000]);
        $stranger = $this->user();
        $item = ShopItem::where('slug', 'tier-priority-pass')->firstOrFail();

        $this->actingAs($player)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $foreign = TierTest::create([
            'user_id' => $stranger->id, 'mode' => 'pvp', 'contact_type' => 'discord',
            'contact_value' => 'x', 'preferred_time' => 'x', 'status' => 'pending',
        ]);

        $this->actingAs($player)
            ->postJson("/api/tier-tests/{$foreign->id}/priority")
            ->assertStatus(422);

        $this->assertFalse($foreign->fresh()->is_priority);
        $this->assertSame(1, \App\Services\ShopService::priorityCharges($player));
    }

    public function test_daily_bonus_is_claimed_once_per_day(): void
    {
        $user = $this->user(['apex_coins' => 0]);

        $first = CoinService::claimDailyBonus($user);
        $second = CoinService::claimDailyBonus($user);

        $this->assertGreaterThan(0, $first);
        $this->assertSame(0, $second);
        $this->assertSame($first, $user->fresh()->apex_coins);
    }

    /**
     * Дымовой прогон: все роуты магазина подключены и отвечают ожидаемым кодом.
     * Ловит опечатки в routes/api.php и забытые middleware.
     */
    public function test_all_shop_routes_are_registered(): void
    {
        $user = $this->user();
        $guest = $this->user();
        $item = ShopItem::where('slug', 'frame-gold')->firstOrFail();

        // Публичные — доступны без авторизации
        $this->getJson('/api/shop')->assertOk();
        $this->getJson("/api/shop/{$item->id}")->assertOk();

        // Без авторизации защищённые роуты дают 401
        $this->getJson('/api/shop/inventory')->assertUnauthorized();
        $this->getJson('/api/shop/priority-candidates')->assertUnauthorized();
        $this->getJson('/api/wallet')->assertUnauthorized();
        $this->getJson('/api/wallet/transactions')->assertUnauthorized();
        $this->postJson('/api/wallet/daily-bonus')->assertUnauthorized();
        $this->getJson('/api/gifts/limits')->assertUnauthorized();
        $this->postJson("/api/gifts/{$guest->id}", ['amount' => 10])->assertUnauthorized();
        $this->postJson("/api/shop/{$item->id}/purchase")->assertUnauthorized();
        $this->postJson('/api/tier-tests/1/priority')->assertUnauthorized();

        // Авторизованные
        $this->actingAs($user)->getJson('/api/shop/inventory')->assertOk();
        $this->actingAs($user)->getJson('/api/shop/priority-candidates')->assertOk();
        $this->actingAs($user)->getJson('/api/wallet')->assertOk();
        $this->actingAs($user)->getJson('/api/wallet/transactions')->assertOk();
        $this->actingAs($user)->getJson('/api/gifts/limits')->assertOk();

        // Админка: обычному игроку закрыта
        $this->actingAs($user)->getJson('/api/admin/shop-items')->assertForbidden();
        $this->actingAs($user)->getJson('/api/admin/shop-rewards')->assertForbidden();
        $this->actingAs($user)->getJson('/api/admin/coin-transactions')->assertForbidden();

        // Админка: модератору доступен каталог, но не леджер монет
        $moderator = $this->user(['role' => 'moderator']);
        $this->actingAs($moderator)->getJson('/api/admin/shop-items')->assertOk();
        $this->actingAs($moderator)->getJson('/api/admin/shop-rewards')->assertOk();
        $this->actingAs($moderator)->getJson('/api/admin/coin-transactions')->assertForbidden();

        // Админка: админу доступно всё
        $admin = $this->user(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/admin/shop-items')->assertOk();
        $this->actingAs($admin)->getJson('/api/admin/coin-transactions')->assertOk();
        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$user->id}/coins", ['amount' => 250, 'reason' => 'тест'])
            ->assertOk();
        $this->assertSame(5250, $user->fresh()->apex_coins);
    }

    public function test_shop_catalog_never_leaks_other_players_data(): void
    {
        $user = $this->user();
        $item = ShopItem::where('slug', 'frame-gold')->firstOrFail();

        $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        // Именно гость: сбрасываем авторизацию из предыдущего запроса
        auth()->forgetGuards();

        $guestView = $this->getJson('/api/shop')->assertOk()->json('items');
        $gold = collect($guestView)->firstWhere('slug', 'frame-gold');

        $this->assertNotNull($gold, 'Предмет frame-gold должен быть в каталоге');
        $this->assertArrayHasKey('owned', $gold);
        $this->assertFalse($gold['owned']);

        // А владелец видит флаг owned и что предмет у него в инвентаре
        $ownerView = $this->actingAs($user)->getJson('/api/shop')->assertOk()->json('items');
        $ownerGold = collect($ownerView)->firstWhere('slug', 'frame-gold');

        $this->assertTrue($ownerGold['owned']);
    }

    public function test_invite_registration_links_referrer_and_pays_both_sides(): void
    {
        $referrer = $this->user(['apex_coins' => 0, 'referral_code' => 'INVITE01']);

        // Код из ссылки сохраняется при регистрации: проверяем саму связку
        $invited = $this->user([
            'username' => 'invited_player',
            'referred_by' => $referrer->id,
        ]);

        $this->assertSame($referrer->id, $invited->referred_by);

        // Что делает RewardService при такой связке
        $balanceBefore = (int) $invited->apex_coins;

        $reward = \App\Services\RewardService::forReferral($referrer, $invited);
        $welcome = \App\Services\RewardService::welcomeBonus($invited);

        $this->assertGreaterThan(0, $reward);
        $this->assertGreaterThan(0, $welcome);
        $this->assertSame($reward, $referrer->fresh()->apex_coins);
        $this->assertSame($balanceBefore + $welcome, $invited->fresh()->apex_coins);

        // Награда записана в леджер
        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $referrer->id,
            'source' => 'referral',
            'amount' => $reward,
        ]);
    }

    public function test_referral_reward_is_paid_once_per_invited_user(): void
    {
        $referrer = $this->user(['apex_coins' => 0, 'referral_code' => 'INVITE02']);
        $invited = $this->user(['referred_by' => $referrer->id]);

        \App\Services\RewardService::forReferral($referrer, $invited);
        $after = $referrer->fresh()->apex_coins;

        \App\Services\RewardService::forReferral($referrer, $invited);

        $this->assertSame($after, $referrer->fresh()->apex_coins);
    }

    public function test_wallet_returns_invite_link_and_stats(): void
    {
        $user = $this->user(['referral_code' => 'MYCODE01']);
        $this->user(['referred_by' => $user->id]);
        $this->user(['referred_by' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/wallet')->assertOk();

        $response->assertJsonPath('referral.code', 'MYCODE01')
            ->assertJsonPath('referral.invited_count', 2);

        $this->assertStringContainsString('ref=MYCODE01', $response->json('referral.link'));
    }

    public function test_wallet_generates_referral_code_on_first_request(): void
    {
        $user = $this->user(['referral_code' => null]);

        $response = $this->actingAs($user)->getJson('/api/wallet')->assertOk();

        $code = $response->json('referral.code');

        $this->assertNotEmpty($code);
        $this->assertSame($code, $user->fresh()->referral_code);
    }

    public function test_badges_are_shown_in_profile_after_equip(): void
    {
        $user = $this->user(['apex_coins' => 20000]);
        $badge = ShopItem::where('slug', 'badge-apex')->firstOrFail();

        $this->actingAs($user)->postJson("/api/shop/{$badge->id}/purchase")->assertCreated();
        $this->actingAs($user)->postJson("/api/shop/{$badge->id}/equip")->assertOk();

        $badges = $user->fresh()->equipped_badges;

        $this->assertIsArray($badges);
        $this->assertCount(1, $badges);
        $this->assertSame('badge-apex', $badges[0]['slug']);
        $this->assertSame('trophy', $badges[0]['icon']);
    }

    public function test_badge_equip_limit_is_enforced(): void
    {
        $user = $this->user(['apex_coins' => 50000]);
        $limit = (int) config('apex.shop.max_equipped_badges', 3);

        $badges = ShopItem::where('type', 'badge')->where('is_active', true)->take($limit + 1)->get();

        foreach ($badges as $badge) {
            $this->actingAs($user)->postJson("/api/shop/{$badge->id}/purchase")->assertCreated();
        }

        // Первые limit бейджей надеваются
        foreach ($badges->take($limit) as $badge) {
            $this->actingAs($user)->postJson("/api/shop/{$badge->id}/equip")->assertOk();
        }

        // Следующий — уже сверх лимита
        $this->actingAs($user)
            ->postJson("/api/shop/{$badges->last()->id}/equip")
            ->assertStatus(422);

        $this->assertCount($limit, $user->fresh()->equipped_badges);
    }

    public function test_priority_request_is_exposed_to_tester_api(): void
    {
        $player = $this->user(['apex_coins' => 5000]);
        $tester = $this->user(['role' => 'tester']);
        $item = ShopItem::where('slug', 'tier-priority-pass')->firstOrFail();

        $test = TierTest::create([
            'user_id' => $player->id, 'mode' => 'pvp', 'contact_type' => 'discord',
            'contact_value' => 'a', 'preferred_time' => 'x', 'status' => 'pending',
        ]);

        $this->actingAs($player)
            ->postJson("/api/shop/{$item->id}/purchase", ['tier_test_id' => $test->id])
            ->assertCreated();

        $queue = $this->actingAs($tester)
            ->getJson('/api/tester/tier-tests?status=pending')
            ->assertOk()
            ->json('data');

        // Тестер видит флаг приоритета у заявки
        $this->assertTrue((bool) $queue[0]['is_priority']);
        $this->assertSame($test->id, $queue[0]['id']);
    }

    public function test_priority_charge_is_visible_in_inventory(): void
    {
        $player = $this->user(['apex_coins' => 5000]);
        $item = ShopItem::where('slug', 'tier-priority-pass')->firstOrFail();

        $this->actingAs($player)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $inventory = $this->actingAs($player)->getJson('/api/shop/inventory')->assertOk();

        $this->assertSame(1, $inventory->json('priority_charges'));
        $this->assertContains(
            'tier_priority',
            collect($inventory->json('items'))->pluck('type')->all()
        );
    }
}
