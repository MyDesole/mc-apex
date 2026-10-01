<?php

namespace Tests\Feature;

use App\Models\ShopItem;
use App\Models\TierTest;
use App\Models\User;
use App\Services\RewardService;
use App\Services\ShopService;
use Database\Seeders\ShopItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TierTestPlayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShopItemSeeder::class);
    }

    private function player(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    private function request(User $player, array $overrides = []): TierTest
    {
        return TierTest::create(array_merge([
            'user_id' => $player->id,
            'mode' => 'pvp',
            'contact_type' => 'discord',
            'contact_value' => 'apex#1234',
            'preferred_time' => 'сегодня 20:00-22:00',
            'status' => 'pending',
        ], $overrides));
    }

    private function priorityItem(): ShopItem
    {
        return ShopItem::where('slug', 'tier-priority-pass')->firstOrFail();
    }

    // ------------------------------------------------------------------
    // Гостевой доступ
    // ------------------------------------------------------------------

    public function test_guest_gets_401_on_every_player_tier_test_route(): void
    {
        $routes = [
            ['GET', '/api/tier-tests'],
            ['POST', '/api/tier-tests'],
            ['GET', '/api/tier-tests/1'],
            ['PUT', '/api/tier-tests/1'],
            ['POST', '/api/tier-tests/1/priority'],
            ['GET', '/api/players/1/tier-history'],
            ['GET', '/api/shop/priority-candidates'],
        ];

        foreach ($routes as [$method, $url]) {
            $response = $this->json($method, $url);

            $this->assertSame(
                401,
                $response->status(),
                "{$method} {$url} должен отдавать 401 гостю, а отдал {$response->status()}"
            );
        }
    }

    // ------------------------------------------------------------------
    // Создание заявки
    // ------------------------------------------------------------------

    public function test_player_can_create_a_tier_test_request(): void
    {
        $player = $this->player();

        $response = $this->actingAs($player)->postJson('/api/tier-tests', [
            'mode' => 'bedwars',
            'contact_type' => 'telegram',
            'contact_value' => '@apex_player',
            'preferred_time' => 'завтра вечером',
            'notes' => 'первый раз',
        ]);

        $response->assertCreated()
            ->assertJsonPath('tier_test.user_id', $player->id)
            ->assertJsonPath('tier_test.mode', 'bedwars')
            ->assertJsonPath('tier_test.status', 'pending')
            ->assertJsonPath('tier_test.notes', 'первый раз');

        $this->assertDatabaseHas('tier_tests', [
            'user_id' => $player->id,
            'mode' => 'bedwars',
            'contact_type' => 'telegram',
            'contact_value' => '@apex_player',
            'status' => 'pending',
            'is_priority' => false,
        ]);
    }

    public function test_creating_a_request_notifies_every_tester_and_admin(): void
    {
        $player = $this->player();
        $tester = $this->player(['role' => 'tester']);
        $admin = $this->player(['role' => 'admin']);
        $other = $this->player(['role' => 'user']);

        $this->actingAs($player)->postJson('/api/tier-tests', [
            'mode' => 'pvp',
            'contact_type' => 'discord',
            'contact_value' => 'apex#1',
            'preferred_time' => 'сейчас',
        ])->assertCreated();

        foreach ([$tester, $admin] as $notifiable) {
            $this->assertDatabaseHas('notifications', [
                'notifiable_type' => User::class,
                'notifiable_id' => $notifiable->id,
            ]);
        }

        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $other->id,
        ]);
    }

    public function test_create_request_validation_rules(): void
    {
        $player = $this->player();

        $this->actingAs($player)->postJson('/api/tier-tests', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mode', 'contact_type', 'contact_value', 'preferred_time']);

        $this->actingAs($player)->postJson('/api/tier-tests', [
            'mode' => 'skywars',
            'contact_type' => 'carrier-pigeon',
            'contact_value' => str_repeat('x', 129),
            'preferred_time' => str_repeat('y', 129),
            'notes' => str_repeat('z', 1001),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mode', 'contact_type', 'contact_value', 'preferred_time', 'notes']);

        $this->assertDatabaseCount('tier_tests', 0);
    }

    // ------------------------------------------------------------------
    // Список и просмотр
    // ------------------------------------------------------------------

    public function test_index_returns_own_requests_and_requests_where_i_am_the_tester(): void
    {
        $player = $this->player();
        $tester = $this->player(['role' => 'tester']);

        $own = $this->request($player);
        $asTester = $this->request($this->player(), ['tester_id' => $tester->id]);

        $response = $this->actingAs($tester)->getJson('/api/tier-tests');

        $response->assertOk()
            ->assertJsonStructure(['my_tests', 'as_tester']);

        $this->assertSame([], $response->json('my_tests'));
        $this->assertSame($asTester->id, $response->json('as_tester.0.id'));

        $playerView = $this->actingAs($player)->getJson('/api/tier-tests')->assertOk();

        $this->assertSame($own->id, $playerView->json('my_tests.0.id'));
        $this->assertSame([], $playerView->json('as_tester'));
    }

    public function test_show_allows_owner_and_tester_but_not_strangers(): void
    {
        $player = $this->player();
        $tester = $this->player(['role' => 'tester']);
        $stranger = $this->player();

        $test = $this->request($player, ['tester_id' => $tester->id]);

        $this->actingAs($player)->getJson("/api/tier-tests/{$test->id}")
            ->assertOk()
            ->assertJsonPath('tier_test.id', $test->id);

        $this->actingAs($tester)->getJson("/api/tier-tests/{$test->id}")->assertOk();
        $this->actingAs($stranger)->getJson("/api/tier-tests/{$test->id}")->assertForbidden();
    }

    public function test_update_is_forbidden_for_strangers_only(): void
    {
        $player = $this->player();
        $stranger = $this->player();

        $test = $this->request($player);

        $this->actingAs($stranger)->putJson("/api/tier-tests/{$test->id}", ['notes' => 'взлом'])
            ->assertForbidden();

        $this->assertSame('apex#1234', $test->fresh()->contact_value);
    }

    // ------------------------------------------------------------------
    // Завершение заявки со стороны игрока
    // ------------------------------------------------------------------

    /**
     * ТЕКУЩЕЕ ПОВЕДЕНИЕ (подозрительно): ничто не мешает самому игроку
     * «завершить» свою заявку и выставить себе тир — ручки тестера для этого
     * не нужны. Тест фиксирует это, чтобы рефакторинг не сделал хуже.
     */
    public function test_player_can_complete_his_own_request_and_gets_rewarded(): void
    {
        $player = $this->player(['apex_coins' => 0, 'tier' => 'E']);

        $test = $this->request($player);

        $response = $this->actingAs($player)->putJson("/api/tier-tests/{$test->id}", [
            'status' => 'completed',
            'result_tier' => 'A',
            'result_score' => 75,
            'aspects' => [
                'block_placing' => 15,
                'rotka' => 15,
                'movement' => 15,
                'aim' => 15,
                'game_sense' => 15,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('tier_test.status', 'completed')
            ->assertJsonPath('tier_test.result_tier', 'A');

        $test->refresh();

        $this->assertSame('completed', $test->status);
        $this->assertNotNull($test->completed_at);

        $player->refresh();

        $this->assertSame('A', $player->tier);
        $this->assertEquals(75, (float) $player->tier_score);

        // Аспекты записаны в профиль игрока
        $this->assertDatabaseHas('player_aspects_pvp', [
            'user_id' => $player->id,
            'block_placing' => 15,
            'aim' => 15,
        ]);

        $reward = RewardService::coinsForTier('A');

        $this->assertGreaterThan(0, $reward);
        $this->assertSame($reward, (int) $player->apex_coins);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $player->id,
            'source' => 'tier_test',
            'amount' => $reward,
            'idempotency_key' => 'tier_test:'.$test->id,
        ]);
    }

    public function test_completing_the_same_request_twice_does_not_pay_twice(): void
    {
        $player = $this->player(['apex_coins' => 0]);

        $test = $this->request($player);

        $payload = [
            'status' => 'completed',
            'result_tier' => 'C',
            'result_score' => 45,
        ];

        $this->actingAs($player)->putJson("/api/tier-tests/{$test->id}", $payload)->assertOk();

        $afterFirst = (int) $player->fresh()->apex_coins;

        $this->actingAs($player)->putJson("/api/tier-tests/{$test->id}", [
            'status' => 'completed',
            'result_tier' => 'A',
            'result_score' => 80,
        ])->assertOk();

        $this->assertSame($afterFirst, (int) $player->fresh()->apex_coins, 'Монеты за второй прогон не начисляются');
        $this->assertSame(
            1,
            $test->fresh()->user->coinTransactions()->where('source', 'tier_test')->count(),
            'В леджере ровно одно начисление за тест'
        );

        // Но тир и score при этом молча перезаписываются (S/S+ не понижаются)
        $this->assertSame('A', $player->fresh()->tier);
    }

    public function test_s_or_s_plus_tier_is_not_lowered_by_a_worse_result(): void
    {
        $player = $this->player(['tier' => 'S', 'tier_score' => 90, 'apex_coins' => 0]);

        $test = $this->request($player);

        $this->actingAs($player)->putJson("/api/tier-tests/{$test->id}", [
            'status' => 'completed',
            'result_tier' => 'D',
            'result_score' => 25,
        ])->assertOk();

        $this->assertSame('S', $player->fresh()->tier);
        $this->assertEquals(25, (float) $player->fresh()->tier_score);
    }

    public function test_update_validation_rules(): void
    {
        $player = $this->player();
        $test = $this->request($player);

        $this->actingAs($player)->putJson("/api/tier-tests/{$test->id}", [
            'status' => 'unknown-status',
            'result_tier' => 'Z',
            'result_score' => 150,
            'aspects' => ['block_placing' => 21],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'status',
                'result_tier',
                'result_score',
                'aspects.block_placing',
            ]);

        $this->assertSame('pending', $test->fresh()->status);
    }

    public function test_update_accepts_bedwars_aspects(): void
    {
        $player = $this->player(['apex_coins' => 0]);

        $test = $this->request($player, ['mode' => 'bedwars']);

        $this->actingAs($player)->putJson("/api/tier-tests/{$test->id}", [
            'status' => 'completed',
            'result_tier' => 'C',
            'result_score' => 45,
            'aspects' => [
                'pvp' => 9,
                'game_sense' => 9,
                'bed_play' => 9,
                'teamplay' => 9,
                'building' => 9,
            ],
        ])->assertOk();

        $this->assertDatabaseHas('player_aspects_bedwars', [
            'user_id' => $player->id,
            'pvp' => 9,
            'building' => 9,
        ]);

        $this->assertDatabaseMissing('player_aspects_pvp', ['user_id' => $player->id]);
    }

    // ------------------------------------------------------------------
    // История
    // ------------------------------------------------------------------

    public function test_history_returns_only_completed_tests(): void
    {
        $player = $this->player();
        $viewer = $this->player();

        $this->request($player, [
            'status' => 'completed',
            'result_tier' => 'B',
            'result_score' => 60,
            'completed_at' => now(),
            'aspects' => ['block_placing' => 12],
        ]);

        $this->request($player, ['status' => 'pending']);
        $this->request($player, ['status' => 'cancelled']);

        $response = $this->actingAs($viewer)->getJson("/api/players/{$player->id}/tier-history");

        $response->assertOk()->assertJsonStructure(['history']);

        $history = $response->json('history');

        $this->assertCount(1, $history);
        $this->assertSame('B', $history[0]['result_tier']);
        $this->assertArrayNotHasKey('contact_value', $history[0], 'История не должна светить контакты игрока');
    }

    // ------------------------------------------------------------------
    // Приоритет (покупка за ApexCoin + применение)
    // ------------------------------------------------------------------

    public function test_priority_pass_can_be_bought_for_a_specific_request(): void
    {
        $player = $this->player(['apex_coins' => 5000]);
        $item = $this->priorityItem();
        $test = $this->request($player);

        $response = $this->actingAs($player)->postJson("/api/shop/{$item->id}/purchase", [
            'tier_test_id' => $test->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('balance', 5000 - $item->price)
            ->assertJsonPath('tier_test.is_priority', true);

        $test->refresh();

        $this->assertTrue($test->is_priority);
        $this->assertSame(
            (int) config('apex.priority.default_weight'),
            (int) $test->priority_weight
        );
        $this->assertSame($item->price, (int) $test->priority_price_paid);
        $this->assertNotNull($test->priority_purchased_at);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $player->id,
            'source' => 'purchase',
            'amount' => -$item->price,
        ]);
    }

    public function test_priority_pass_bought_in_advance_is_applied_later(): void
    {
        $player = $this->player(['apex_coins' => 5000]);
        $item = $this->priorityItem();

        // Покупаем заряд заранее — он ждёт в инвентаре
        $this->actingAs($player)->postJson("/api/shop/{$item->id}/purchase")
            ->assertCreated()
            ->assertJsonPath('tier_test', null);

        $this->assertSame(1, ShopService::priorityCharges($player));

        $test = $this->request($player);

        $response = $this->actingAs($player)->postJson("/api/tier-tests/{$test->id}/priority");

        $response->assertOk()
            ->assertJsonPath('tier_test.is_priority', true)
            ->assertJsonPath('charges', 0);

        $this->assertTrue($test->fresh()->is_priority);
        $this->assertSame(0, ShopService::priorityCharges($player->fresh()));
    }

    public function test_priority_can_not_be_applied_without_a_purchased_charge(): void
    {
        $player = $this->player(['apex_coins' => 0]);
        $test = $this->request($player);

        $response = $this->actingAs($player)->postJson("/api/tier-tests/{$test->id}/priority");

        $response->assertStatus(422);
        $this->assertStringContainsString('Нет купленного приоритета', (string) $response->json('message'));

        $this->assertFalse($test->fresh()->is_priority);
    }

    public function test_priority_can_not_be_applied_to_a_foreign_request(): void
    {
        $player = $this->player(['apex_coins' => 5000]);
        $stranger = $this->player();
        $item = $this->priorityItem();

        $foreign = $this->request($stranger);

        $this->actingAs($player)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $response = $this->actingAs($player)->postJson("/api/tier-tests/{$foreign->id}/priority");

        $response->assertStatus(422);
        $this->assertStringContainsString('не ваша заявка', (string) $response->json('message'));

        $this->assertFalse($foreign->fresh()->is_priority);
        $this->assertSame(1, ShopService::priorityCharges($player->fresh()), 'Заряд не должен сгорать');
    }

    public function test_priority_can_not_be_applied_to_a_finished_request(): void
    {
        $player = $this->player(['apex_coins' => 5000]);
        $item = $this->priorityItem();

        $finished = $this->request($player, [
            'status' => 'completed',
            'result_tier' => 'C',
            'result_score' => 45,
            'completed_at' => now(),
        ]);

        $this->actingAs($player)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $response = $this->actingAs($player)->postJson("/api/tier-tests/{$finished->id}/priority");

        $response->assertStatus(422);
        $this->assertStringContainsString('только к активной заявке', (string) $response->json('message'));

        $this->assertFalse($finished->fresh()->is_priority);
        $this->assertSame(1, ShopService::priorityCharges($player->fresh()));
    }

    public function test_priority_pass_can_not_be_bought_without_enough_coins(): void
    {
        $player = $this->player(['apex_coins' => 10]);
        $item = $this->priorityItem();
        $test = $this->request($player);

        $response = $this->actingAs($player)->postJson("/api/shop/{$item->id}/purchase", [
            'tier_test_id' => $test->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Недостаточно ApexCoin. Не хватает '.($item->price - 10).'.');

        $this->assertFalse($test->fresh()->is_priority);
        $this->assertSame(10, (int) $player->fresh()->apex_coins);
        $this->assertSame(0, ShopService::priorityCharges($player->fresh()));
        $this->assertDatabaseMissing('user_inventory', [
            'user_id' => $player->id,
            'shop_item_id' => $item->id,
        ]);
    }

    public function test_priority_candidates_lists_only_active_requests_of_the_player(): void
    {
        $player = $this->player(['apex_coins' => 0]);
        $other = $this->player();

        $pending = $this->request($player);
        $inProgress = $this->request($player, ['mode' => 'bedwars', 'status' => 'in_progress']);
        $this->request($player, ['status' => 'completed', 'completed_at' => now()]);
        $foreign = $this->request($other);

        $response = $this->actingAs($player)->getJson('/api/shop/priority-candidates');

        $response->assertOk()->assertJsonPath('charges', 0);

        $ids = collect($response->json('tier_tests'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$pending->id, $inProgress->id], $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_completing_without_aspects_keeps_tier_from_the_result_only(): void
    {
        $player = $this->player(['apex_coins' => 0, 'tier' => 'E']);

        $test = $this->request($player);

        $this->actingAs($player)->putJson("/api/tier-tests/{$test->id}", [
            'status' => 'completed',
            'result_tier' => 'B',
            'result_score' => 60,
        ])->assertOk();

        // Аспекты не пришли — строк аспектов нет, но тир всё равно выставляется
        $this->assertDatabaseCount('player_aspects_pvp', 0);
        $this->assertDatabaseCount('player_aspects_bedwars', 0);
        $this->assertSame('B', $player->fresh()->tier);
        $this->assertEquals(60, (float) $player->fresh()->tier_score);
    }
}
