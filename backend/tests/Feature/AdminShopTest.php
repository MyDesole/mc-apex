<?php

namespace Tests\Feature;

use App\Models\CoinTransaction;
use App\Models\ShopItem;
use App\Models\ShopSetting;
use App\Models\User;
use App\Services\CoinService;
use App\Services\RewardService;
use App\Services\ShopSettingService;
use Database\Seeders\ShopItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Админка магазина: CRUD предметов, настройки наград, ручные начисления и леджер.
 */
class AdminShopTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function player(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    public function test_admin_shop_routes_require_authentication(): void
    {
        $user = $this->player();

        $this->getJson('/api/admin/shop-items')->assertUnauthorized();
        $this->postJson('/api/admin/shop-items', [])->assertUnauthorized();
        $this->putJson('/api/admin/shop-items/1', [])->assertUnauthorized();
        $this->deleteJson('/api/admin/shop-items/1')->assertUnauthorized();
        $this->postJson('/api/admin/shop-items/1/toggle')->assertUnauthorized();
        $this->getJson('/api/admin/shop-rewards')->assertUnauthorized();
        $this->putJson('/api/admin/shop-rewards', [])->assertUnauthorized();
        $this->getJson('/api/admin/coin-transactions')->assertUnauthorized();
        $this->postJson("/api/admin/users/{$user->id}/coins", ['amount' => 10])->assertUnauthorized();
    }

    public function test_regular_player_is_forbidden_on_admin_shop_routes(): void
    {
        $user = $this->player();
        $target = $this->player();

        // Предмет должен существовать: SubstituteBindings выполняется раньше role-мидлвари,
        // и на несуществующий id вернулся бы 404 вместо 403
        $item = ShopItem::create([
            'slug' => 'guard-test-item',
            'name' => 'Предмет для проверки прав',
            'type' => ShopItem::TYPE_BADGE,
            'price' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($user)->getJson('/api/admin/shop-items')->assertForbidden();
        $this->actingAs($user)->postJson('/api/admin/shop-items', ['name' => 'x', 'type' => 'badge', 'price' => 1])->assertForbidden();
        $this->actingAs($user)->putJson("/api/admin/shop-items/{$item->id}", ['price' => 1])->assertForbidden();
        $this->actingAs($user)->deleteJson("/api/admin/shop-items/{$item->id}")->assertForbidden();
        $this->actingAs($user)->postJson("/api/admin/shop-items/{$item->id}/toggle")->assertForbidden();
        $this->actingAs($user)->getJson('/api/admin/shop-rewards')->assertForbidden();
        $this->actingAs($user)->putJson('/api/admin/shop-rewards', ['sources' => ['referral' => false]])->assertForbidden();
        $this->actingAs($user)->getJson('/api/admin/coin-transactions')->assertForbidden();
        $this->actingAs($user)->postJson("/api/admin/users/{$target->id}/coins", ['amount' => 10])->assertForbidden();

        $this->assertSame(0, CoinTransaction::count());
        $this->assertSame(10, $item->fresh()->price, 'Неадмин не может править каталог');
        $this->assertDatabaseHas('shop_items', ['id' => $item->id]);
    }

    public function test_moderator_can_manage_catalog_but_not_grant_coins(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator']);
        $target = $this->player();

        $this->actingAs($moderator)->getJson('/api/admin/shop-items')->assertOk();
        $this->actingAs($moderator)->getJson('/api/admin/shop-rewards')->assertOk();

        $this->actingAs($moderator)->getJson('/api/admin/coin-transactions')->assertForbidden();
        $this->actingAs($moderator)->postJson("/api/admin/users/{$target->id}/coins", ['amount' => 10])->assertForbidden();
    }

    public function test_admin_can_create_update_toggle_and_delete_shop_item(): void
    {
        $admin = $this->admin();

        $created = $this->actingAs($admin)->postJson('/api/admin/shop-items', [
            'name' => 'Test Badge Item',
            'type' => ShopItem::TYPE_BADGE,
            'price' => 1234,
            'rarity' => 'epic',
            'is_active' => true,
            'metadata' => ['icon' => 'star', 'color' => '#123456'],
        ])->assertCreated();

        $id = $created->json('item.id');

        $this->assertNotNull($id);
        // Слаг генерируется из имени
        $this->assertSame('test-badge-item', $created->json('item.slug'));
        $this->assertSame('star', $created->json('item.icon'));
        $this->assertSame('#123456', $created->json('item.color'));

        $this->assertDatabaseHas('shop_items', [
            'id' => $id,
            'name' => 'Test Badge Item',
            'type' => 'badge',
            'price' => 1234,
            'rarity' => 'epic',
        ]);

        $updated = $this->actingAs($admin)
            ->putJson("/api/admin/shop-items/{$id}", ['price' => 999, 'rarity' => 'legendary'])
            ->assertOk();

        $this->assertSame(999, $updated->json('item.price'));
        $this->assertSame('legendary', $updated->json('item.rarity'));
        $this->assertSame('Test Badge Item', $updated->json('item.name'), 'Имя не сбрасывается при частичном апдейте');

        $toggled = $this->actingAs($admin)->postJson("/api/admin/shop-items/{$id}/toggle")->assertOk();
        $this->assertFalse($toggled->json('item.is_active'));

        $this->actingAs($admin)->postJson("/api/admin/shop-items/{$id}/toggle")->assertOk();
        $this->assertTrue(ShopItem::find($id)->is_active);

        $this->actingAs($admin)->deleteJson("/api/admin/shop-items/{$id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('shop_items', ['id' => $id]);
    }

    public function test_admin_shop_item_validation_rules(): void
    {
        $admin = $this->admin();

        // Нет обязательных полей
        $this->actingAs($admin)->postJson('/api/admin/shop-items', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type', 'price']);

        // Неизвестный тип
        $this->actingAs($admin)->postJson('/api/admin/shop-items', [
            'name' => 'Плохой тип',
            'type' => 'weapon',
            'price' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors('type');

        // Отрицательная цена
        $this->actingAs($admin)->postJson('/api/admin/shop-items', [
            'name' => 'Отрицательная цена',
            'type' => 'badge',
            'price' => -1,
        ])->assertStatus(422)->assertJsonValidationErrors('price');

        // Дубликат слага
        $this->seed(ShopItemSeeder::class);

        $this->actingAs($admin)->postJson('/api/admin/shop-items', [
            'name' => 'Дубль',
            'slug' => 'frame-gold',
            'type' => 'badge',
            'price' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors('slug');

        $this->assertSame(0, ShopItem::where('name', 'Плохой тип')->count());
    }

    public function test_admin_index_lists_inactive_items_and_filters_by_type(): void
    {
        $admin = $this->admin();

        ShopItem::create(['slug' => 'active-frame', 'name' => 'Активная рамка', 'type' => 'avatar_frame', 'price' => 10, 'is_active' => true]);
        ShopItem::create(['slug' => 'inactive-frame', 'name' => 'Выключенная рамка', 'type' => 'avatar_frame', 'price' => 10, 'is_active' => false]);
        ShopItem::create(['slug' => 'active-badge', 'name' => 'Активный бейдж', 'type' => 'badge', 'price' => 10, 'is_active' => true]);

        $all = $this->actingAs($admin)->getJson('/api/admin/shop-items')->assertOk();

        $all->assertJsonStructure(['items', 'types', 'rarities']);
        $this->assertCount(3, $all->json('items'));
        $this->assertContains('tier_priority', $all->json('types'));
        $this->assertSame(['common', 'rare', 'epic', 'legendary'], $all->json('rarities'));

        $frames = $this->actingAs($admin)->getJson('/api/admin/shop-items?type=avatar_frame')->assertOk();

        $this->assertEqualsCanonicalizing(
            ['active-frame', 'inactive-frame'],
            array_column($frames->json('items'), 'slug')
        );
    }

    public function test_admin_rewards_endpoint_shape(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->getJson('/api/admin/shop-rewards')->assertOk();

        $response->assertJsonStructure([
            'tier_table',
            'first_test_bonus',
            'achievement_base',
            'achievement_per_point',
            'daily_bonus',
            'daily_bonus_enabled',
            'gift_fee_percent',
            'gift_daily_limit',
            'sources',
            'stored',
        ]);

        $this->assertSame(config('apex.coins.tier_test.per_tier'), $response->json('tier_table'));
        $this->assertSame((int) config('apex.coins.daily_bonus.amount'), $response->json('daily_bonus'));
        $this->assertSame((int) config('apex.coins.gift.fee_percent'), $response->json('gift_fee_percent'));
        $this->assertSame(config('apex.coins.sources'), $response->json('sources'));
    }

    public function test_admin_rewards_validation(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->putJson('/api/admin/shop-rewards', ['gift' => ['fee_percent' => 51]])
            ->assertStatus(422)->assertJsonValidationErrors('gift.fee_percent');

        $this->actingAs($admin)->putJson('/api/admin/shop-rewards', ['daily_bonus' => ['amount' => -1]])
            ->assertStatus(422)->assertJsonValidationErrors('daily_bonus.amount');

        $this->actingAs($admin)->putJson('/api/admin/shop-rewards', ['tier_test' => ['per_tier' => ['E' => 1000001]]])
            ->assertStatus(422)->assertJsonValidationErrors('tier_test.per_tier.E');

        $this->actingAs($admin)->putJson('/api/admin/shop-rewards', ['sources' => ['referral' => 'нет']])
            ->assertStatus(422)->assertJsonValidationErrors('sources.referral');

        $this->assertSame(0, ShopSetting::count());
    }

    public function test_admin_can_switch_coin_sources_off(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->putJson('/api/admin/shop-rewards', [
            'sources' => ['tier_test' => true, 'achievement' => true, 'daily_bonus' => true, 'admin' => true, 'referral' => false],
        ])->assertOk();

        $this->assertFalse(\App\Services\ShopSettingService::sourceEnabled('referral'));
        $this->assertTrue(\App\Services\ShopSettingService::sourceEnabled('tier_test'));

        $referrer = $this->player(['apex_coins' => 0]);
        $invited = $this->player(['referred_by' => $referrer->id]);

        $this->assertSame(0, RewardService::forReferral($referrer, $invited));
        $this->assertSame(0, RewardService::welcomeBonus($invited));
    }

    /**
     * Исправлено: правила для плоских ключей с точкой не срабатывали
     * (validated() трактует точку как путь), и значения молча выкидывались.
     * Теперь такой payload — ровно то, что отправляет админка — сохраняется.
     */
    public function test_flat_dotted_reward_keys_from_the_admin_ui_are_saved(): void
    {
        $admin = $this->admin();

        // Именно такой плоский payload отправляет frontend/src/components/admin/AdminShop.vue
        $this->actingAs($admin)->putJson('/api/admin/shop-rewards', [
            'tier_test.per_tier' => ['E' => 1, 'S' => 2],
            'tier_test.first_test_bonus' => 7,
            'achievement.base' => 9,
            'daily_bonus.amount' => 11,
            'daily_bonus.enabled' => false,
            'gift.fee_percent' => 25,
            'gift.daily_limit' => 123,
        ])->assertOk();

        // Значения дошли до читателей
        $this->assertSame(['E' => 1, 'S' => 2], RewardService::tierTable());
        $this->assertFalse(ShopSettingService::getBool('daily_bonus.enabled'));
        $this->assertSame(11, ShopSettingService::getInt('daily_bonus.amount'));
        $this->assertSame(7, ShopSettingService::getInt('tier_test.first_test_bonus'));
        $this->assertSame(9, ShopSettingService::getInt('achievement.base'));
        $this->assertSame(25, ShopSettingService::getInt('gift.fee_percent'));
        $this->assertSame(123, ShopSettingService::getInt('gift.daily_limit'));
    }

    /**
     * Исправлено: вложенный payload писался под ключом-родителем
     * ('tier_test'), а читатели ищут 'tier_test.per_tier'. Теперь вложенный
     * вид разворачивается в плоские ключи и читается корректно.
     */
    public function test_nested_reward_payload_is_flattened_and_readable(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->putJson('/api/admin/shop-rewards', [
            'tier_test' => ['per_tier' => ['E' => 1], 'first_test_bonus' => 7],
            'daily_bonus' => ['amount' => 11],
            'gift' => ['fee_percent' => 25],
        ])->assertOk();

        $keys = ShopSetting::query()->pluck('key')->all();

        $this->assertContains('tier_test.per_tier', $keys);
        $this->assertContains('tier_test.first_test_bonus', $keys);
        $this->assertContains('daily_bonus.amount', $keys);
        $this->assertContains('gift.fee_percent', $keys);

        $this->assertSame(['E' => 1], RewardService::tierTable());
        $this->assertSame(7, ShopSettingService::getInt('tier_test.first_test_bonus'));
        $this->assertSame(11, ShopSettingService::getInt('daily_bonus.amount'));
        $this->assertSame(25, ShopSettingService::getInt('gift.fee_percent'));
    }

    public function test_admin_rewards_shows_first_test_bonus_that_is_never_paid_documents_bug(): void
    {
        $admin = $this->admin();
        $player = $this->player(['apex_coins' => 0]);

        $test = \App\Models\TierTest::create([
            'user_id' => $player->id,
            'mode' => 'pvp',
            'contact_type' => 'discord',
            'contact_value' => 'apex#1',
            'preferred_time' => 'вечером',
            'status' => 'completed',
            'result_tier' => 'E',
            'result_score' => 10,
            'completed_at' => now(),
        ]);

        $shown = $this->actingAs($admin)->getJson('/api/admin/shop-rewards')->json('first_test_bonus');
        $paid = RewardService::forTierTest($test);

        // BUG: админка показывает 150 (config), но RewardService::forTierTest()
        // передаёт в getInt() явный дефолт 0 и бонус не начисляется.
        $this->assertSame(150, $shown);
        $this->assertSame(60, $paid);
        $this->assertSame(60, $player->fresh()->apex_coins);
    }

    public function test_admin_can_grant_coins_and_ledger_records_the_actor(): void
    {
        $admin = $this->admin();
        $player = $this->player(['apex_coins' => 100]);

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/users/{$player->id}/coins", ['amount' => 250, 'reason' => 'победа в турнире'])
            ->assertOk();

        $this->assertSame('Баланс обновлён.', $response->json('message'));
        $this->assertSame(350, $response->json('balance'));
        $this->assertSame(350, $player->fresh()->apex_coins);
        $this->assertSame(0, $player->fresh()->apex_coins_spent);

        $tx = CoinTransaction::where('user_id', $player->id)->sole();

        $this->assertSame(250, $tx->amount);
        $this->assertSame(350, $tx->balance_after);
        $this->assertSame('admin', $tx->source);
        $this->assertSame('победа в турнире', $tx->description);
        $this->assertSame($admin->id, $tx->actor_id);
        $this->assertNull($tx->idempotency_key);
    }

    public function test_admin_grant_uses_default_reason_and_can_be_negative(): void
    {
        $admin = $this->admin();
        $player = $this->player(['apex_coins' => 500]);

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$player->id}/coins", ['amount' => 100])
            ->assertOk();

        $this->assertSame('Ручное начисление администратором', CoinTransaction::where('user_id', $player->id)->where('amount', 100)->sole()->description);

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$player->id}/coins", ['amount' => -200, 'reason' => 'штраф'])
            ->assertOk();

        $this->assertSame(400, $player->fresh()->apex_coins);
        $this->assertSame(200, $player->fresh()->apex_coins_spent);

        $penalty = CoinTransaction::where('user_id', $player->id)->where('amount', -200)->sole();
        $this->assertSame(400, $penalty->balance_after);
    }

    public function test_admin_grant_validates_amount(): void
    {
        $admin = $this->admin();
        $player = $this->player(['apex_coins' => 100]);

        $this->actingAs($admin)->postJson("/api/admin/users/{$player->id}/coins", [])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->actingAs($admin)->postJson("/api/admin/users/{$player->id}/coins", ['amount' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->actingAs($admin)->postJson("/api/admin/users/{$player->id}/coins", ['amount' => 1000001])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->actingAs($admin)->postJson("/api/admin/users/{$player->id}/coins", ['amount' => 'много'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->assertSame(100, $player->fresh()->apex_coins);
        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_admin_grant_cannot_overdraw_a_player(): void
    {
        $admin = $this->admin();
        $player = $this->player(['apex_coins' => 50]);

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/users/{$player->id}/coins", ['amount' => -100])
            ->assertStatus(422);

        $this->assertSame('Недостаточно ApexCoin на балансе.', $response->json('message'));
        $this->assertSame(50, $player->fresh()->apex_coins);
        $this->assertSame(0, $player->fresh()->apex_coins_spent);
        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_admin_grant_to_missing_user_returns_404(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/admin/users/999999/coins', ['amount' => 10])->assertNotFound();
    }

    public function test_admin_coin_transactions_listing_and_filters(): void
    {
        $admin = $this->admin();
        $first = $this->player(['apex_coins' => 0]);
        $second = $this->player(['apex_coins' => 0]);

        $this->actingAs($admin)->postJson("/api/admin/users/{$first->id}/coins", ['amount' => 10, 'reason' => 'первому'])->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/users/{$second->id}/coins", ['amount' => 20, 'reason' => 'второму'])->assertOk();
        CoinService::credit($first, 30, CoinTransaction::SOURCE_TIER_TEST, 'тир-тест');

        $all = $this->actingAs($admin)->getJson('/api/admin/coin-transactions')->assertOk();

        $all->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
        $this->assertSame(3, $all->json('total'));
        $this->assertSame(50, $all->json('per_page'));

        // Связи подгружены: у админского начисления виден и получатель, и автор
        $adminRow = collect($all->json('data'))->firstWhere('user_id', $second->id);

        $this->assertNotNull($adminRow);
        $this->assertSame($second->username, $adminRow['user']['username']);
        $this->assertSame($admin->id, $adminRow['actor_id']);
        $this->assertSame($admin->username, $adminRow['actor']['username']);

        // У начисления за тир-тест автора нет
        $tierRow = collect($all->json('data'))->firstWhere('source', 'tier_test');
        $this->assertNull($tierRow['actor_id']);
        $this->assertNull($tierRow['actor']);

        $bySource = $this->actingAs($admin)->getJson('/api/admin/coin-transactions?source=tier_test')->assertOk();
        $this->assertSame(1, $bySource->json('total'));
        $this->assertSame('tier_test', $bySource->json('data.0.source'));

        $byUser = $this->actingAs($admin)->getJson("/api/admin/coin-transactions?user_id={$second->id}")->assertOk();
        $this->assertSame(1, $byUser->json('total'));
        $this->assertSame($second->id, $byUser->json('data.0.user_id'));
        $this->assertSame('второму', $byUser->json('data.0.description'));
    }

    public function test_admin_coin_transactions_sort_is_newest_first(): void
    {
        $admin = $this->admin();
        $player = $this->player(['apex_coins' => 0]);

        foreach ([1, 2, 3] as $i) {
            $this->travel($i)->minutes();
            CoinService::credit($player, $i * 10, CoinTransaction::SOURCE_ADMIN, "op {$i}");
        }

        $this->travelBack();

        $data = $this->actingAs($admin)->getJson('/api/admin/coin-transactions')->assertOk()->json('data');

        $this->assertSame([30, 20, 10], array_column($data, 'amount'));

        // Леджер виден только администратору
        $this->actingAs($player)->getJson('/api/admin/coin-transactions')->assertForbidden();
    }
}
