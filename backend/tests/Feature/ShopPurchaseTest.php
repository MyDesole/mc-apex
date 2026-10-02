<?php

namespace Tests\Feature;

use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Wallet\Services\RewardService;
use App\Domains\Shop\Services\ShopService;
use Database\Seeders\ShopItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Shop\Services\ShopSettingService;

/**
 * Покупки, инвентарь, надевание и приоритет тир-теста.
 *
 * Тесты с суффиксом _documents_bug фиксируют текущее (неверное) поведение.
 */
class ShopPurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShopItemSeeder::class);
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['apex_coins' => 5000], $attributes));
    }

    private function item(string $slug): ShopItem
    {
        return ShopItem::where('slug', $slug)->firstOrFail();
    }

    private function tierTest(User $user, array $attributes = []): TierTest
    {
        return TierTest::create(array_merge([
            'user_id' => $user->id,
            'mode' => 'pvp',
            'contact_type' => 'discord',
            'contact_value' => 'apex#1',
            'preferred_time' => 'вечером',
            'status' => 'pending',
        ], $attributes));
    }

    public function test_purchase_requires_authentication(): void
    {
        $item = $this->item('frame-gold');

        $this->postJson("/api/shop/{$item->id}/purchase")->assertUnauthorized();
        $this->getJson('/api/shop/inventory')->assertUnauthorized();
        $this->postJson("/api/shop/{$item->id}/equip")->assertUnauthorized();
        $this->postJson("/api/shop/{$item->id}/unequip")->assertUnauthorized();
    }

    public function test_purchase_charges_exact_price_and_writes_ledger_row(): void
    {
        $user = $this->user(['apex_coins' => 5000]);
        $item = $this->item('frame-gold'); // 900

        $response = $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $this->assertSame(4100, $response->json('balance'));
        $this->assertSame("«{$item->name}» куплено.", $response->json('message'));
        $this->assertNull($response->json('tier_test'));

        $this->assertSame(4100, $user->fresh()->apex_coins);
        $this->assertSame(900, $user->fresh()->apex_coins_spent);

        $tx = CoinTransaction::where('user_id', $user->id)->sole();

        $this->assertSame(-900, $tx->amount);
        $this->assertSame(4100, $tx->balance_after);
        $this->assertSame('purchase', $tx->source);
        $this->assertSame("Покупка: {$item->name}", $tx->description);
        // Полиморфный тип хранится псевдонимом из morphMap
        $this->assertSame('shop_item', $tx->reference_type);
        $this->assertSame($item->id, $tx->reference_id);
        $this->assertSame(['slug' => 'frame-gold', 'quantity' => 1], $tx->meta);
        $this->assertNull($tx->idempotency_key);

        $this->assertDatabaseHas('user_inventory', [
            'user_id' => $user->id,
            'shop_item_id' => $item->id,
            'quantity' => 1,
            'equipped_at' => null,
        ]);
    }

    public function test_purchase_response_contains_inventory_and_stale_item_flags(): void
    {
        $user = $this->user(['apex_coins' => 5000]);
        $item = $this->item('frame-gold');

        $response = $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $response->assertJsonStructure(['message', 'balance', 'item', 'tier_test', 'inventory']);

        $this->assertCount(1, $response->json('inventory'));
        $this->assertSame('frame-gold', $response->json('inventory.0.slug'));
        $this->assertSame(1, $response->json('inventory.0.quantity'));
        $this->assertFalse($response->json('inventory.0.equipped'));

        // Текущее поведение: блок item в ответе на покупку собран без флагов владения,
        // поэтому сразу после покупки он отдаёт owned=false / can_afford=false.
        $this->assertFalse($response->json('item.owned'));
        $this->assertSame(0, $response->json('item.quantity'));
        $this->assertFalse($response->json('item.can_afford'));
    }

    public function test_purchase_fails_without_enough_coins_and_changes_nothing(): void
    {
        $user = $this->user(['apex_coins' => 100]);
        $item = $this->item('frame-gold'); // 900

        $response = $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertStatus(422);

        $this->assertSame('Недостаточно ApexCoin. Не хватает 800.', $response->json('message'));

        $this->assertSame(100, $user->fresh()->apex_coins);
        $this->assertSame(0, $user->fresh()->apex_coins_spent);
        $this->assertSame(0, CoinTransaction::count());
        $this->assertSame(0, UserInventory::count());
    }

    public function test_inactive_item_is_hidden_from_show_and_cannot_be_bought(): void
    {
        $user = $this->user(['apex_coins' => 5000]);

        $item = ShopItem::create([
            'slug' => 'test-inactive-frame',
            'name' => 'Снятая с продажи рамка',
            'type' => ShopItem::TYPE_AVATAR_FRAME,
            'price' => 100,
            'is_active' => false,
        ]);

        $this->getJson("/api/shop/{$item->id}")->assertNotFound();

        $response = $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertStatus(422);

        $this->assertSame('Этот предмет больше не продаётся.', $response->json('message'));
        $this->assertSame(5000, $user->fresh()->apex_coins);
        $this->assertSame(0, UserInventory::count());
    }

    public function test_non_stackable_item_cannot_be_bought_twice(): void
    {
        $user = $this->user(['apex_coins' => 5000]);
        $item = $this->item('frame-purple'); // 250

        $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $second = $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertStatus(422);

        $this->assertSame('Этот предмет уже есть в вашем инвентаре.', $second->json('message'));
        $this->assertSame(4750, $user->fresh()->apex_coins);
        $this->assertSame(250, $user->fresh()->apex_coins_spent);
        $this->assertSame(1, CoinTransaction::count());
        $this->assertSame(1, UserInventory::where('user_id', $user->id)->sole()->quantity);
    }

    public function test_quantity_above_one_requires_stackable_item(): void
    {
        $user = $this->user(['apex_coins' => 5000]);
        $item = $this->item('frame-purple');

        $response = $this->actingAs($user)
            ->postJson("/api/shop/{$item->id}/purchase", ['quantity' => 2])
            ->assertStatus(422);

        $this->assertSame('Этот предмет можно купить только один раз.', $response->json('message'));
        $this->assertSame(5000, $user->fresh()->apex_coins);
        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_quantity_is_validated_by_the_request(): void
    {
        $user = $this->user(['apex_coins' => 5000]);
        $item = $this->item('tier-priority-pass');

        $this->actingAs($user)
            ->postJson("/api/shop/{$item->id}/purchase", ['quantity' => 11])
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');

        $this->actingAs($user)
            ->postJson("/api/shop/{$item->id}/purchase", ['quantity' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');

        $this->actingAs($user)
            ->postJson("/api/shop/{$item->id}/purchase", ['tier_test_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tier_test_id');

        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_consumable_purchase_stacks_and_charges_each_time(): void
    {
        $user = $this->user(['apex_coins' => 5000]);
        $item = $this->item('tier-priority-pass'); // 800, расходник, максимум 10

        $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();
        $second = $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $this->assertSame(3400, $second->json('balance'));
        $this->assertSame(2, ShopService::priorityCharges($user->fresh()));

        $row = UserInventory::where('user_id', $user->id)->sole();
        $this->assertSame(2, $row->quantity);

        $this->assertSame(2, CoinTransaction::count());
        $this->assertSame(-1600, (int) CoinTransaction::where('user_id', $user->id)->sum('amount'));
    }

    public function test_priority_pack_multiplies_charges_by_quantity(): void
    {
        $user = $this->user(['apex_coins' => 10000]);
        $pack = $this->item('tier-priority-pass-x5'); // 3500, charges = 5, максимум 10

        $response = $this->actingAs($user)
            ->postJson("/api/shop/{$pack->id}/purchase", ['quantity' => 2])
            ->assertCreated();

        $this->assertSame(3000, $response->json('balance'));
        $this->assertSame(10, ShopService::priorityCharges($user->fresh()));

        $tx = CoinTransaction::where('user_id', $user->id)->sole();
        $this->assertSame(-7000, $tx->amount);
        $this->assertSame(['slug' => 'tier-priority-pass-x5', 'quantity' => 2], $tx->meta);
        $this->assertSame("Покупка: {$pack->name} ×2", $tx->description);
    }

    public function test_max_quantity_is_enforced(): void
    {
        $user = $this->user(['apex_coins' => 10000]);
        $pack = $this->item('tier-priority-pass-x5'); // максимум 10 применений

        $this->actingAs($user)
            ->postJson("/api/shop/{$pack->id}/purchase", ['quantity' => 2])
            ->assertCreated();

        $response = $this->actingAs($user)
            ->postJson("/api/shop/{$pack->id}/purchase", ['quantity' => 1])
            ->assertStatus(422);

        $this->assertSame('Достигнут максимум по этому предмету.', $response->json('message'));
        $this->assertSame(3000, $user->fresh()->apex_coins);
        $this->assertSame(1, CoinTransaction::count());
    }

    public function test_coin_bundle_credits_balance_and_records_inventory(): void
    {
        $user = $this->user(['apex_coins' => 1000]);

        $bundle = ShopItem::create([
            'slug' => 'test-coin-bundle',
            'name' => 'Набор 500 ApexCoin',
            'type' => ShopItem::TYPE_COIN_BUNDLE,
            'price' => 300,
            'is_active' => true,
            'metadata' => ['amount' => 500],
        ]);

        $response = $this->actingAs($user)->postJson("/api/shop/{$bundle->id}/purchase")->assertCreated();

        $this->assertSame(1200, $response->json('balance'));
        $this->assertSame(1200, $user->fresh()->apex_coins);
        $this->assertSame(300, $user->fresh()->apex_coins_spent);
        // Запись в инвентаре обязательна: без неё не работают проверки
        // «уже куплено» и max_quantity, и набор покупался бесконечно.
        $this->assertSame(1, UserInventory::where('user_id', $user->id)->count());

        $rows = CoinTransaction::where('user_id', $user->id)->orderBy('id')->get();

        $this->assertSame([-300, 500], $rows->pluck('amount')->all());
        $this->assertSame([700, 1200], $rows->pluck('balance_after')->all());
        $this->assertSame(['purchase', 'other'], $rows->pluck('source')->all());
        $this->assertSame('Активация набора: Набор 500 ApexCoin', $rows[1]->description);
    }

    /**
     * Исправлено: набор монет возвращался из purchase() до записи в инвентарь,
     * поэтому не работала проверка «уже куплено» и набор с is_repeatable = false
     * покупался бесконечно.
     */
    public function test_non_repeatable_coin_bundle_can_be_bought_once(): void
    {
        $user = $this->user(['apex_coins' => 1000]);

        $bundle = ShopItem::create([
            'slug' => 'test-repeatable-bundle',
            'name' => 'Набор 500',
            'type' => ShopItem::TYPE_COIN_BUNDLE,
            'price' => 300,
            'is_active' => true,
            'is_repeatable' => false,
            'metadata' => ['amount' => 500],
        ]);

        $this->actingAs($user)->postJson("/api/shop/{$bundle->id}/purchase")->assertCreated();

        // Вторая покупка отклоняется
        $this->actingAs($user)
            ->postJson("/api/shop/{$bundle->id}/purchase")
            ->assertStatus(422);

        // Куплено один раз: 1000 − 300 + 500
        $this->assertSame(1200, $user->fresh()->apex_coins);
        $this->assertSame(1, UserInventory::where('user_id', $user->id)->count());
    }

    /**
     * Исправлено: нулевой набор монет можно было нажимать бесконечно.
     * Теперь действует общее правило владения.
     */
    public function test_zero_price_coin_bundle_is_not_an_infinite_coin_source(): void
    {
        $user = $this->user(['apex_coins' => 0]);

        $free = ShopItem::create([
            'slug' => 'test-free-bundle',
            'name' => 'Бесплатный набор',
            'type' => ShopItem::TYPE_COIN_BUNDLE,
            'price' => 0,
            'is_active' => true,
            'metadata' => ['amount' => 500],
        ]);

        $this->actingAs($user)->postJson("/api/shop/{$free->id}/purchase")->assertCreated();

        // Повторная покупка того же набора отклоняется
        $this->actingAs($user)
            ->postJson("/api/shop/{$free->id}/purchase")
            ->assertStatus(422);

        $this->assertSame(500, $user->fresh()->apex_coins);
    }

    public function test_equip_requires_ownership(): void
    {
        $user = $this->user();
        $item = $this->item('frame-gold');

        $response = $this->actingAs($user)->postJson("/api/shop/{$item->id}/equip")->assertStatus(422);

        $this->assertSame('Сначала нужно купить этот предмет.', $response->json('message'));
        $this->assertSame('default', $user->fresh()->avatar_frame);
    }

    public function test_non_equippable_item_cannot_be_equipped(): void
    {
        $user = $this->user();
        $item = $this->item('tier-priority-pass');

        $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $response = $this->actingAs($user)->postJson("/api/shop/{$item->id}/equip")->assertStatus(422);

        $this->assertSame('Этот предмет нельзя надеть.', $response->json('message'));
    }

    public function test_equipping_a_frame_replaces_the_previous_one_in_the_same_slot(): void
    {
        $user = $this->user(['apex_coins' => 20000]);
        $gold = $this->item('frame-gold');
        $cyan = $this->item('frame-cyan');

        $this->actingAs($user)->postJson("/api/shop/{$gold->id}/purchase")->assertCreated();
        $this->actingAs($user)->postJson("/api/shop/{$cyan->id}/purchase")->assertCreated();

        $this->actingAs($user)->postJson("/api/shop/{$gold->id}/equip")->assertOk();

        $this->assertSame('gold', $user->fresh()->avatar_frame);

        $this->actingAs($user)->postJson("/api/shop/{$cyan->id}/equip")->assertOk();

        $this->assertSame('cyan', $user->fresh()->avatar_frame);
        $this->assertNull(
            UserInventory::where('user_id', $user->id)->where('shop_item_id', $gold->id)->sole()->equipped_at
        );
        $this->assertNotNull(
            UserInventory::where('user_id', $user->id)->where('shop_item_id', $cyan->id)->sole()->equipped_at
        );
    }

    public function test_unequip_resets_profile_field(): void
    {
        $user = $this->user(['apex_coins' => 20000]);
        $frame = $this->item('frame-rainbow');
        $effect = $this->item('effect-glow');

        $this->actingAs($user)->postJson("/api/shop/{$frame->id}/purchase")->assertCreated();
        $this->actingAs($user)->postJson("/api/shop/{$frame->id}/equip")->assertOk();
        $this->assertSame('rainbow', $user->fresh()->avatar_frame);

        $this->actingAs($user)->postJson("/api/shop/{$frame->id}/unequip")->assertOk();
        $this->assertSame('default', $user->fresh()->avatar_frame, 'Рамка сбрасывается в default');

        $response = $this->actingAs($user)->postJson("/api/shop/{$frame->id}/unequip")->assertStatus(422);
        $this->assertSame('Этот предмет не надет.', $response->json('message'));

        $this->actingAs($user)->postJson("/api/shop/{$effect->id}/purchase")->assertCreated();
        $this->actingAs($user)->postJson("/api/shop/{$effect->id}/equip")->assertOk();
        $this->assertSame('glow', $user->fresh()->profile_effect);

        $this->actingAs($user)->postJson("/api/shop/{$effect->id}/unequip")->assertOk();
        $this->assertNull($user->fresh()->profile_effect, 'Эффект профиля сбрасывается в null');
    }

    public function test_accent_color_is_written_to_profile(): void
    {
        $user = $this->user(['apex_coins' => 5000]);
        $item = $this->item('accent-teal');

        $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();
        $this->actingAs($user)->postJson("/api/shop/{$item->id}/equip")->assertOk();

        $this->assertSame('#14b8a6', $user->fresh()->accent_color);
    }

    public function test_card_background_cannot_be_equipped_documents_bug(): void
    {
        $user = $this->user(['apex_coins' => 5000]);

        $item = ShopItem::create([
            'slug' => 'test-card-background',
            'name' => 'Фон карточки',
            'type' => ShopItem::TYPE_CARD_BACKGROUND,
            'effect_value' => 'bg-neon',
            'price' => 100,
            'is_active' => true,
        ]);

        $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $response = $this->actingAs($user)->postJson("/api/shop/{$item->id}/equip")->assertStatus(422);

        // BUG: ShopItem::EQUIPPABLE не содержит card_background, хотя
        // ShopService::profileFieldFor() умеет писать в поле card_background.
        // В итоге купленный фон карточки надеть невозможно.
        $this->assertSame('Этот предмет нельзя надеть.', $response->json('message'));
        $this->assertFalse($item->isEquippable());
        $this->assertNull($user->fresh()->card_background);
    }

    public function test_badge_equip_limit_and_badge_list_sync(): void
    {
        config(['apex.shop.max_equipped_badges' => 2]);

        $user = $this->user(['apex_coins' => 50000]);
        $badges = ShopItem::where('type', ShopItem::TYPE_BADGE)->where('is_active', true)->orderBy('id')->take(3)->get();

        $this->assertCount(3, $badges);

        foreach ($badges as $badge) {
            $this->actingAs($user)->postJson("/api/shop/{$badge->id}/purchase")->assertCreated();
        }

        $this->actingAs($user)->postJson("/api/shop/{$badges[0]->id}/equip")->assertOk();
        $this->actingAs($user)->postJson("/api/shop/{$badges[1]->id}/equip")->assertOk();

        $equipped = $user->fresh()->equipped_badges;
        $this->assertCount(2, $equipped);
        $this->assertSame([$badges[0]->slug, $badges[1]->slug], array_column($equipped, 'slug'));
        $this->assertSame('heart', $equipped[0]['icon']);

        $third = $this->actingAs($user)->postJson("/api/shop/{$badges[2]->id}/equip")->assertStatus(422);
        $this->assertSame('Можно надеть не больше 2 бейджей.', $third->json('message'));

        $this->assertCount(2, $user->fresh()->equipped_badges);
        $this->assertNull(
            UserInventory::where('user_id', $user->id)->where('shop_item_id', $badges[2]->id)->sole()->equipped_at
        );

        // Снятие бейджа пересобирает список в профиле
        $this->actingAs($user)->postJson("/api/shop/{$badges[0]->id}/unequip")->assertOk();

        $this->assertSame([$badges[1]->slug], array_column($user->fresh()->equipped_badges, 'slug'));
    }

    public function test_inventory_endpoint_shape(): void
    {
        $user = $this->user(['apex_coins' => 5000]);
        $pass = $this->item('tier-priority-pass');

        $this->actingAs($user)->postJson("/api/shop/{$pass->id}/purchase")->assertCreated();

        $response = $this->actingAs($user)->getJson('/api/shop/inventory')->assertOk();

        $response->assertJsonStructure([
            'items',
            'equipped_badges',
            'priority_charges',
            'priority_candidates',
            'max_equipped_badges',
        ]);

        $this->assertSame(1, $response->json('priority_charges'));
        $this->assertSame((int) config('apex.shop.max_equipped_badges'), $response->json('max_equipped_badges'));
        $this->assertContains('tier_priority', array_column($response->json('items'), 'type'));
        $this->assertSame([], $response->json('equipped_badges'));
    }

    public function test_catalog_marks_owned_and_affordable_items(): void
    {
        $item = $this->item('frame-gold'); // 900

        // Гость: набор флагов есть, но всё ложно
        $guestItem = collect($this->getJson('/api/shop')->assertOk()->json('items'))->firstWhere('slug', 'frame-gold');
        $this->assertFalse($guestItem['owned']);
        $this->assertFalse($guestItem['can_afford']);

        $user = $this->user(['apex_coins' => 1000]);
        $before = collect($this->actingAs($user)->getJson('/api/shop')->json('items'))->firstWhere('slug', 'frame-gold');
        $this->assertTrue($before['can_afford']);

        $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $after = collect($this->actingAs($user)->getJson('/api/shop')->json('items'))->firstWhere('slug', 'frame-gold');
        $this->assertTrue($after['owned']);
        $this->assertSame(1, $after['quantity']);
        $this->assertFalse($after['equipped']);
        $this->assertFalse($after['can_afford'], 'После покупки на дорогую рамку денег уже не хватает');
    }

    public function test_shop_item_page_is_public_for_active_items(): void
    {
        $item = $this->item('frame-gold');

        $this->getJson("/api/shop/{$item->id}")
            ->assertOk()
            ->assertJsonPath('item.slug', 'frame-gold')
            ->assertJsonPath('item.price', 900)
            ->assertJsonPath('item.owned', false);
    }

    public function test_priority_purchase_applies_flag_weight_and_price_to_target(): void
    {
        $player = $this->user(['apex_coins' => 5000]);
        $item = $this->item('tier-priority-pass'); // 800
        $test = $this->tierTest($player, ['priority_weight' => 50]);

        $response = $this->actingAs($player)
            ->postJson("/api/shop/{$item->id}/purchase", ['tier_test_id' => $test->id])
            ->assertCreated();

        $this->assertSame($test->id, $response->json('tier_test.id'));
        $this->assertTrue($response->json('tier_test.is_priority'));
        $this->assertSame(4200, $response->json('balance'));

        $fresh = $test->fresh();
        $this->assertTrue($fresh->is_priority);
        $this->assertSame(
            (int) config('apex.priority.default_weight') + 50,
            $fresh->priority_weight,
            'Вес приоритета складывается с уже имеющимся'
        );
        $this->assertSame($item->price, $fresh->priority_price_paid);
        $this->assertNotNull($fresh->priority_purchased_at);

        $this->assertSame(4200, $player->fresh()->apex_coins);
        $this->assertSame(800, $player->fresh()->apex_coins_spent);

        $tx = CoinTransaction::where('user_id', $player->id)->sole();
        $this->assertSame(['slug' => 'tier-priority-pass', 'quantity' => 1, 'tier_test_id' => $test->id], $tx->meta);

        // Заряд расходника сгорел на применении — запись в инвентаре удалена
        $this->assertSame(0, ShopService::priorityCharges($player->fresh()));
        $this->assertSame(0, UserInventory::where('user_id', $player->id)->count());
        $this->assertSame([], $response->json('inventory'));
    }

    public function test_priority_can_be_bought_in_advance_and_kept_as_charge(): void
    {
        $player = $this->user(['apex_coins' => 5000]);
        $item = $this->item('tier-priority-pass');

        $response = $this->actingAs($player)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $this->assertNull($response->json('tier_test'));
        $this->assertSame(1, ShopService::priorityCharges($player->fresh()));
        $this->assertSame(1, UserInventory::where('user_id', $player->id)->sole()->quantity);
    }

    public function test_priority_charge_applied_later_via_endpoint(): void
    {
        $player = $this->user(['apex_coins' => 5000]);
        $item = $this->item('tier-priority-pass');

        $this->actingAs($player)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $test = $this->tierTest($player);

        $response = $this->actingAs($player)
            ->postJson("/api/tier-tests/{$test->id}/priority")
            ->assertOk();

        $this->assertSame($test->id, $response->json('tier_test.id'));
        $this->assertTrue($response->json('tier_test.is_priority'));
        $this->assertSame((int) config('apex.priority.default_weight'), $response->json('tier_test.priority_weight'));
        $this->assertSame(0, $response->json('charges'), 'Заряд потрачен');

        $second = $this->tierTest($player);

        $response = $this->actingAs($player)
            ->postJson("/api/tier-tests/{$second->id}/priority")
            ->assertStatus(422);

        $this->assertSame('Нет купленного приоритета. Купи его в магазине.', $response->json('message'));
        $this->assertFalse($second->fresh()->is_priority);
    }

    public function test_priority_candidates_lists_only_active_own_requests(): void
    {
        $player = $this->user();
        $other = $this->user();

        $pending = $this->tierTest($player);
        $completed = $this->tierTest($player, ['status' => 'completed']);
        $foreign = $this->tierTest($other);

        $response = $this->actingAs($player)->getJson('/api/shop/priority-candidates')->assertOk();

        $response->assertJsonStructure(['tier_tests' => [['id', 'mode', 'status', 'is_priority', 'created_at']], 'charges']);

        $ids = array_column($response->json('tier_tests'), 'id');

        $this->assertContains($pending->id, $ids);
        $this->assertNotContains($completed->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
        $this->assertSame(0, $response->json('charges'));
    }

    public function test_priority_apply_rejects_foreign_and_finished_requests(): void
    {
        $player = $this->user(['apex_coins' => 5000]);
        $stranger = $this->user();
        $item = $this->item('tier-priority-pass');

        $this->actingAs($player)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $foreign = $this->tierTest($stranger);
        $foreignResponse = $this->actingAs($player)
            ->postJson("/api/tier-tests/{$foreign->id}/priority")
            ->assertStatus(422);
        $this->assertSame('Это не ваша заявка.', $foreignResponse->json('message'));
        $this->assertFalse($foreign->fresh()->is_priority);

        $finished = $this->tierTest($player, ['status' => 'completed']);
        $finishedResponse = $this->actingAs($player)
            ->postJson("/api/tier-tests/{$finished->id}/priority")
            ->assertStatus(422);
        $this->assertSame('Приоритет можно применить только к активной заявке.', $finishedResponse->json('message'));

        // Заряд не потрачен ни на одну неудачную попытку
        $this->assertSame(1, ShopService::priorityCharges($player->fresh()));
    }

    public function test_priority_purchase_target_validation_does_not_charge_coins(): void
    {
        $player = $this->user(['apex_coins' => 5000]);
        $stranger = $this->user();
        $item = $this->item('tier-priority-pass');

        $foreign = $this->tierTest($stranger);

        $response = $this->actingAs($player)
            ->postJson("/api/shop/{$item->id}/purchase", ['tier_test_id' => $foreign->id])
            ->assertStatus(422);

        $this->assertSame('Заявка не найдена или уже недоступна.', $response->json('message'));

        $alreadyPriority = $this->tierTest($player, ['is_priority' => true]);
        $second = $this->actingAs($player)
            ->postJson("/api/shop/{$item->id}/purchase", ['tier_test_id' => $alreadyPriority->id])
            ->assertStatus(422);

        $this->assertSame('У этой заявки уже есть приоритет.', $second->json('message'));

        $this->assertSame(5000, $player->fresh()->apex_coins);
        $this->assertSame(0, CoinTransaction::count());
        $this->assertSame(0, UserInventory::count());
    }

    public function test_tier_test_completion_awards_coins_once_and_writes_ledger(): void
    {
        $player = $this->user(['apex_coins' => 0]);
        $tester = $this->user(['role' => 'tester']);

        $test = $this->tierTest($player);

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/claim")->assertOk();

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/complete", [
            'block_placing' => 12,
            'rotka' => 12,
            'movement' => 12,
            'aim' => 12,
            'game_sense' => 12,
        ])->assertOk();

        $expected = RewardService::coinsForTier('B'); // 60 очков -> тир B

        $this->assertSame(220, $expected);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $player->id,
            'source' => 'tier_test',
            'amount' => $expected,
            'balance_after' => $expected,
            'idempotency_key' => 'tier_test:' . $test->id,
        ]);

        $this->assertSame($expected, $player->fresh()->apex_coins);

        // Повторное начисление за тот же тест невозможно
        $this->assertSame(0, RewardService::forTierTest($test->fresh()));
        $this->assertSame($expected, $player->fresh()->apex_coins);
        $this->assertSame(1, CoinTransaction::where('user_id', $player->id)->count());

        // Повторное завершение через API тоже отклоняется
        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/complete", [
            'block_placing' => 20,
            'rotka' => 20,
            'movement' => 20,
            'aim' => 20,
            'game_sense' => 20,
        ])->assertStatus(422);

        $this->assertSame($expected, $player->fresh()->apex_coins);
    }

    public function test_first_test_bonus_from_config_is_never_paid_documents_bug(): void
    {
        $player = $this->user(['apex_coins' => 0]);

        $test = $this->tierTest($player, [
            'status' => 'completed',
            'result_tier' => 'E',
            'result_score' => 10,
            'completed_at' => now(),
        ]);

        $paid = RewardService::forTierTest($test);

        // BUG: config('apex.coins.tier_test.first_test_bonus') = 150, но
        // RewardService::forTierTest() вызывает getInt('tier_test.first_test_bonus', 0)
        // с явным нулём — а ShopSettingService::get() возвращает $default, если он не null.
        // Поэтому разовый бонус за первую заявку не выплачивается никогда.
        $this->assertSame(60, $paid);
        $this->assertSame(150, (int) config('apex.coins.tier_test.first_test_bonus'));
        $this->assertSame(60, $player->fresh()->apex_coins);
    }

    public function test_catalog_hides_inactive_items_from_shoppers(): void
    {
        ShopItem::create([
            'slug' => 'test-hidden-item',
            'name' => 'Скрытый предмет',
            'type' => ShopItem::TYPE_BADGE,
            'price' => 10,
            'is_active' => false,
        ]);

        $slugs = array_column($this->getJson('/api/shop')->assertOk()->json('items'), 'slug');

        $this->assertNotContains('test-hidden-item', $slugs);
    }
}
