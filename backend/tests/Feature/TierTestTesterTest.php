<?php

namespace Tests\Feature;

use App\Models\PlayerAspectBedwars;
use App\Models\PlayerAspectPvp;
use App\Models\TierTest;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\RewardService;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TierTestTesterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AchievementSeeder::class);
    }

    private function tester(): User
    {
        return User::factory()->tester()->create();
    }

    private function player(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['apex_coins' => 0], $attributes));
    }

    private function request(User $player, array $overrides = []): TierTest
    {
        return TierTest::create(array_merge([
            'user_id' => $player->id,
            'mode' => 'pvp',
            'contact_type' => 'discord',
            'contact_value' => 'apex#1234',
            'preferred_time' => 'сегодня 20:00',
            'status' => 'pending',
        ], $overrides));
    }

    /**
     * Создаёт заявку с нужной датой создания (created_at не в $fillable).
     */
    private function requestCreatedAt(User $player, \DateTimeInterface $createdAt, array $overrides = []): TierTest
    {
        $test = $this->request($player, $overrides);
        $test->created_at = $createdAt;
        $test->save();

        return $test;
    }

    private function claimedPvpRequest(User $tester, User $player): TierTest
    {
        $test = $this->request($player);

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/claim")->assertOk();

        return $test;
    }

    // ------------------------------------------------------------------
    // Доступ
    // ------------------------------------------------------------------

    public function test_guest_gets_401_on_every_tester_route(): void
    {
        $routes = [
            ['GET', '/api/tester/tier-tests'],
            ['GET', '/api/tester/tier-tests/stats'],
            ['GET', '/api/tester/tier-tests/1'],
            ['POST', '/api/tester/tier-tests/1/claim'],
            ['POST', '/api/tester/tier-tests/1/unclaim'],
            ['POST', '/api/tester/tier-tests/1/complete'],
            ['POST', '/api/tester/tier-tests/1/cancel'],
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

    public function test_regular_player_gets_403_on_every_tester_route(): void
    {
        $player = $this->player();
        $test = $this->request($this->player());

        $routes = [
            ['GET', "/api/tester/tier-tests"],
            ['GET', "/api/tester/tier-tests/stats"],
            ['GET', "/api/tester/tier-tests/{$test->id}"],
            ['POST', "/api/tester/tier-tests/{$test->id}/claim"],
            ['POST', "/api/tester/tier-tests/{$test->id}/unclaim"],
            ['POST', "/api/tester/tier-tests/{$test->id}/complete"],
            ['POST', "/api/tester/tier-tests/{$test->id}/cancel"],
        ];

        foreach ($routes as [$method, $url]) {
            $response = $this->actingAs($player)->json($method, $url);

            $this->assertSame(
                403,
                $response->status(),
                "{$method} {$url} должен отдавать 403 обычному игроку, а отдал {$response->status()}"
            );
        }
    }

    public function test_media_and_moderator_are_not_testers(): void
    {
        $media = User::factory()->create(['role' => 'media']);
        $moderator = User::factory()->create(['role' => 'moderator']);

        $this->actingAs($media)->getJson('/api/tester/tier-tests')->assertForbidden();
        $this->actingAs($moderator)->getJson('/api/tester/tier-tests')->assertForbidden();
    }

    public function test_admin_has_full_tester_access(): void
    {
        $admin = User::factory()->admin()->create();
        $player = $this->player();

        $test = $this->request($player);

        $this->actingAs($admin)->getJson('/api/tester/tier-tests')->assertOk();
        $this->actingAs($admin)->postJson("/api/tester/tier-tests/{$test->id}/claim")->assertOk();

        $this->assertSame('in_progress', $test->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Очередь заявок
    // ------------------------------------------------------------------

    public function test_queue_is_paginated_and_exposes_expected_fields(): void
    {
        $tester = $this->tester();
        $this->request($this->player());

        $response = $this->actingAs($tester)->getJson('/api/tester/tier-tests');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'mode', 'status', 'is_priority', 'user', 'tester', 'claimer']],
                'current_page',
                'per_page',
                'total',
            ]);

        $this->assertSame(30, $response->json('per_page'));
        $this->assertSame(1, $response->json('total'));
    }

    public function test_queue_defaults_to_open_requests_only(): void
    {
        $tester = $this->tester();
        $player = $this->player();

        $pending = $this->request($player);
        $inProgress = $this->request($player, ['status' => 'in_progress']);
        $completed = $this->request($player, ['status' => 'completed', 'completed_at' => now()]);
        $cancelled = $this->request($player, ['status' => 'cancelled']);

        $default = collect($this->actingAs($tester)->getJson('/api/tester/tier-tests')->json('data'))->pluck('id');

        $this->assertEqualsCanonicalizing([$pending->id, $inProgress->id], $default->all());

        $onlyCompleted = collect(
            $this->actingAs($tester)->getJson('/api/tester/tier-tests?status=completed')->json('data')
        )->pluck('id')->all();

        $this->assertSame([$completed->id], $onlyCompleted);

        $onlyCancelled = collect(
            $this->actingAs($tester)->getJson('/api/tester/tier-tests?status=cancelled')->json('data')
        )->pluck('id')->all();

        $this->assertSame([$cancelled->id], $onlyCancelled);
    }

    public function test_queue_puts_priority_requests_first_then_fifo(): void
    {
        $tester = $this->tester();
        $player = $this->player();

        $oldestPriority = $this->requestCreatedAt($player, now()->subDays(3), [
            'is_priority' => true,
            'priority_weight' => (int) config('apex.priority.default_weight'),
        ]);

        $newerPriority = $this->requestCreatedAt($player, now()->subDay(), [
            'is_priority' => true,
            'priority_weight' => (int) config('apex.priority.default_weight'),
        ]);

        $oldNormal = $this->requestCreatedAt($player, now()->subDays(5), ['mode' => 'bedwars']);

        $queue = collect(
            $this->actingAs($tester)->getJson('/api/tester/tier-tests?status=pending')->json('data')
        )->pluck('id')->all();

        $this->assertSame([$oldestPriority->id, $newerPriority->id, $oldNormal->id], $queue);
    }

    public function test_queue_orders_by_priority_weight_descending(): void
    {
        $tester = $this->tester();
        $player = $this->player();

        $normalWeight = $this->requestCreatedAt($player, now()->subDay(), [
            'is_priority' => true,
            'priority_weight' => 100,
        ]);

        $heavyWeight = $this->requestCreatedAt($player, now(), [
            'is_priority' => true,
            'priority_weight' => 500,
        ]);

        $queue = collect(
            $this->actingAs($tester)->getJson('/api/tester/tier-tests?status=pending')->json('data')
        )->pluck('id')->all();

        $this->assertSame([$heavyWeight->id, $normalWeight->id], $queue);
    }

    public function test_queue_mode_free_and_mine_filters(): void
    {
        $tester = $this->tester();
        $otherTester = $this->tester();
        $player = $this->player();

        $pvpFree = $this->request($player);
        $bedwarsFree = $this->request($player, ['mode' => 'bedwars']);
        $takenByMe = $this->request($player);
        $takenByOther = $this->request($player);

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$takenByMe->id}/claim")->assertOk();
        $this->actingAs($otherTester)->postJson("/api/tester/tier-tests/{$takenByOther->id}/claim")->assertOk();

        $bedwars = collect(
            $this->actingAs($tester)->getJson('/api/tester/tier-tests?mode=bedwars')->json('data')
        )->pluck('id')->all();

        $this->assertSame([$bedwarsFree->id], $bedwars);

        $free = collect(
            $this->actingAs($tester)->getJson('/api/tester/tier-tests?free=1')->json('data')
        )->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$pvpFree->id, $bedwarsFree->id], $free);

        $mine = collect(
            $this->actingAs($tester)->getJson('/api/tester/tier-tests?mine=1')->json('data')
        )->pluck('id')->all();

        $this->assertSame([$takenByMe->id], $mine);
    }

    public function test_stats_endpoint_counts_completed_in_progress_and_pending(): void
    {
        $tester = $this->tester();
        $player = $this->player();

        $this->request($player);
        $this->request($player);

        $claimed = $this->request($player);
        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$claimed->id}/claim")->assertOk();

        $response = $this->actingAs($tester)->getJson('/api/tester/tier-tests/stats');

        $response->assertOk()
            ->assertJsonStructure(['total_completed', 'in_progress', 'pending_total', 'today'])
            ->assertJsonPath('in_progress', 1)
            ->assertJsonPath('pending_total', 2)
            ->assertJsonPath('total_completed', 0)
            ->assertJsonPath('today', 0);
    }

    /**
     * Исправлено: show() грузил 'user.aspects' — у User это аксессор,
     * а не отношение, поэтому карточка заявки отдавала 500.
     */
    public function test_show_returns_request_card(): void
    {
        $tester = $this->tester();
        $player = $this->player();
        $test = $this->request($player);

        $response = $this->actingAs($tester)
            ->getJson("/api/tester/tier-tests/{$test->id}")
            ->assertOk();

        $this->assertSame($test->id, $response->json('tier_test.id'));
        $this->assertSame($player->id, $response->json('user.id'));
        $this->assertSame($player->username, $response->json('user.username'));
    }

    // ------------------------------------------------------------------
    // Взятие / возврат заявки
    // ------------------------------------------------------------------

    public function test_claim_moves_request_to_in_progress(): void
    {
        $tester = $this->tester();
        $test = $this->request($this->player());

        $response = $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/claim");

        $response->assertOk()->assertJsonPath('tier_test.status', 'in_progress');

        $test->refresh();

        $this->assertSame('in_progress', $test->status);
        $this->assertSame($tester->id, $test->claimed_by);
        $this->assertSame($tester->id, $test->tester_id);
        $this->assertNotNull($test->claimed_at);
    }

    public function test_claim_is_rejected_when_request_is_already_taken_or_finished(): void
    {
        $tester = $this->tester();
        $otherTester = $this->tester();
        $player = $this->player();

        $test = $this->request($player);

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/claim")->assertOk();

        // Другой тестер
        $foreign = $this->actingAs($otherTester)->postJson("/api/tester/tier-tests/{$test->id}/claim");
        $foreign->assertStatus(422)
            ->assertJsonPath('message', 'Заявка уже не в статусе ожидания.');

        // Даже сам владелец заявки-тестера не может взять её дважды
        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/claim")
            ->assertStatus(422);

        $this->assertSame($tester->id, $test->fresh()->claimed_by);
    }

    public function test_claim_is_rejected_for_cancelled_request(): void
    {
        $tester = $this->tester();
        $test = $this->request($this->player(), ['status' => 'cancelled']);

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/claim")
            ->assertStatus(422);
    }

    public function test_unclaim_returns_request_to_the_pool(): void
    {
        $tester = $this->tester();
        $test = $this->claimedPvpRequest($tester, $this->player());

        $response = $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/unclaim");

        $response->assertOk()->assertJsonPath('ok', true);

        $test->refresh();

        $this->assertSame('pending', $test->status);
        $this->assertNull($test->claimed_by);
        $this->assertNull($test->claimed_at);
        $this->assertNull($test->tester_id);
    }

    public function test_unclaim_is_forbidden_for_another_tester(): void
    {
        $tester = $this->tester();
        $otherTester = $this->tester();
        $test = $this->claimedPvpRequest($tester, $this->player());

        $this->actingAs($otherTester)->postJson("/api/tester/tier-tests/{$test->id}/unclaim")
            ->assertForbidden();

        $this->assertSame('in_progress', $test->fresh()->status);
    }

    public function test_unclaim_is_rejected_when_request_is_no_longer_in_progress(): void
    {
        $tester = $this->tester();
        $test = $this->claimedPvpRequest($tester, $this->player());

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/complete", [
            'block_placing' => 10,
            'rotka' => 10,
            'movement' => 10,
            'aim' => 10,
            'game_sense' => 10,
        ])->assertOk();

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/unclaim")
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // Проведение теста
    // ------------------------------------------------------------------

    public function test_complete_requires_an_in_progress_request(): void
    {
        $tester = $this->tester();
        $player = $this->player();

        $payload = [
            'block_placing' => 10,
            'rotka' => 10,
            'movement' => 10,
            'aim' => 10,
            'game_sense' => 10,
        ];

        // Никем не взятую заявку проводить нельзя: сначала проверка «это не ваш тест» → 403
        $unclaimed = $this->request($player);

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$unclaimed->id}/complete", $payload)
            ->assertForbidden();

        $this->assertSame('pending', $unclaimed->fresh()->status);

        // Заявку взяли и отменили: она всё ещё «моя», но уже не в работе → 422
        $cancelled = $this->claimedPvpRequest($tester, $player);

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$cancelled->id}/cancel")->assertOk();

        $response = $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$cancelled->id}/complete", $payload);

        $response->assertStatus(422);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    public function test_complete_is_forbidden_for_another_tester(): void
    {
        $tester = $this->tester();
        $otherTester = $this->tester();
        $test = $this->claimedPvpRequest($tester, $this->player());

        $this->actingAs($otherTester)->postJson("/api/tester/tier-tests/{$test->id}/complete", [
            'block_placing' => 10,
            'rotka' => 10,
            'movement' => 10,
            'aim' => 10,
            'game_sense' => 10,
        ])->assertForbidden();

        $this->assertSame('in_progress', $test->fresh()->status);
    }

    public function test_complete_validates_aspects_for_the_mode(): void
    {
        $tester = $this->tester();
        $test = $this->claimedPvpRequest($tester, $this->player());

        // Ничего не передали
        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/complete")
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'block_placing',
                'rotka',
                'movement',
                'aim',
                'game_sense',
            ]);

        // Значения вне диапазона 0..20 и текст вместо числа
        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/complete", [
            'block_placing' => 21,
            'rotka' => -1,
            'movement' => 'много',
            'aim' => 10,
            'game_sense' => 10,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['block_placing', 'rotka', 'movement']);

        $this->assertSame('in_progress', $test->fresh()->status);
    }

    public function test_complete_pvp_calculates_tier_and_awards_coins(): void
    {
        $tester = $this->tester();
        $player = $this->player(['tier' => 'E']);
        $test = $this->claimedPvpRequest($tester, $player);

        $response = $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/complete", [
            'block_placing' => 15,
            'rotka' => 15,
            'movement' => 15,
            'aim' => 15,
            'game_sense' => 15,
            'notes' => 'хорошая игра',
        ]);

        $response->assertOk()
            ->assertJsonPath('tier_test.status', 'completed')
            ->assertJsonPath('tier_test.result_tier', 'A')
            ->assertJsonPath('tier_test.notes', 'хорошая игра')
            ->assertJsonPath('user.tier', 'A');

        $test->refresh();

        $this->assertSame('completed', $test->status);
        $this->assertNotNull($test->completed_at);
        $this->assertEquals(75, (float) $test->result_score);
        $this->assertSame(15, $test->aspects['block_placing']);

        // Аспекты записаны в профиль игрока
        $aspects = PlayerAspectPvp::where('user_id', $player->id)->firstOrFail();

        $this->assertSame(15, (int) $aspects->block_placing);
        $this->assertSame(15, (int) $aspects->game_sense);

        $player->refresh();

        $this->assertSame('A', $player->tier);
        $this->assertEquals(75, (float) $player->tier_score);

        // ApexCoin за тир
        $reward = RewardService::coinsForTier('A');

        $this->assertSame(350, $reward, 'Таблица наград из config/apex.php изменилась — обновите ожидание');
        $this->assertGreaterThanOrEqual($reward, (int) $player->apex_coins);

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $player->id,
            'source' => 'tier_test',
            'amount' => $reward,
            'idempotency_key' => 'tier_test:'.$test->id,
            'reference_id' => $test->id,
        ]);
    }

    public function test_complete_bedwars_maps_score_to_tier(): void
    {
        $tester = $this->tester();

        $cases = [
            ['A', [15, 15, 15, 15, 15], 75],
            ['B', [12, 12, 12, 12, 12], 60],
            ['C', [9, 9, 9, 9, 9], 45],
            ['D', [5, 5, 5, 5, 5], 25],
            ['E', [1, 1, 1, 1, 1], 5],
        ];

        foreach ($cases as [$expectedTier, $values, $expectedScore]) {
            $player = $this->player();
            $test = $this->claimedPvpRequest($tester, $player);

            $test->update(['mode' => 'bedwars']);

            $response = $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/complete", [
                'pvp' => $values[0],
                'game_sense' => $values[1],
                'bed_play' => $values[2],
                'teamplay' => $values[3],
                'building' => $values[4],
            ]);

            $response->assertOk();

            $this->assertSame($expectedTier, $response->json('tier_test.result_tier'));
            $this->assertEquals($expectedScore, (float) $response->json('tier_test.result_score'));

            $this->assertSame($expectedTier, $player->fresh()->tier);
            $this->assertSame(
                RewardService::coinsForTier($expectedTier),
                (int) DB::table('coin_transactions')
                    ->where('idempotency_key', 'tier_test:'.$test->id)
                    ->value('amount')
            );

            $this->assertInstanceOf(
                PlayerAspectBedwars::class,
                PlayerAspectBedwars::where('user_id', $player->id)->firstOrFail()
            );
        }
    }

    public function test_complete_grants_tier_achievements_and_their_coins(): void
    {
        $tester = $this->tester();
        $player = $this->player(['tier' => 'E']);
        $test = $this->claimedPvpRequest($tester, $player);

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/complete", [
            'block_placing' => 15,
            'rotka' => 15,
            'movement' => 15,
            'aim' => 15,
            'game_sense' => 15,
        ])->assertOk();

        $codes = $player->fresh()->achievements()->pluck('code')->all();

        foreach (['tier_e', 'tier_d', 'tier_c', 'tier_b', 'tier_a', 'tier_test_first'] as $code) {
            $this->assertContains($code, $codes, "Ачивка {$code} должна выдаваться за тир A");
        }

        $this->assertNotContains('tier_s', $codes, 'S/S+ за тир-тесты не выдаются');

        // Награды за ачивки попали в леджер ровно по разу
        $tierAAchievement = \App\Models\Achievement::byCode('tier_a');

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $player->id,
            'source' => 'achievement',
            'idempotency_key' => 'achievement:'.$player->id.':'.$tierAAchievement->id,
            'amount' => RewardService::coinsForAchievement($tierAAchievement),
        ]);

        $this->assertSame(
            1,
            DB::table('coin_transactions')
                ->where('user_id', $player->id)
                ->where('idempotency_key', 'achievement:'.$player->id.':'.$tierAAchievement->id)
                ->count()
        );
    }

    public function test_complete_can_not_be_submitted_twice(): void
    {
        $tester = $this->tester();
        $player = $this->player();
        $test = $this->claimedPvpRequest($tester, $player);

        $payload = [
            'block_placing' => 12,
            'rotka' => 12,
            'movement' => 12,
            'aim' => 12,
            'game_sense' => 12,
        ];

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/complete", $payload)
            ->assertOk();

        $coinsAfterFirst = (int) $player->fresh()->apex_coins;
        $ledgerAfterFirst = DB::table('coin_transactions')->where('user_id', $player->id)->count();

        // Вторая попытка: заявка уже не in_progress
        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/complete", [
            'block_placing' => 20,
            'rotka' => 20,
            'movement' => 20,
            'aim' => 20,
            'game_sense' => 20,
        ])->assertStatus(422);

        $this->assertSame($coinsAfterFirst, (int) $player->fresh()->apex_coins);
        $this->assertSame($ledgerAfterFirst, DB::table('coin_transactions')->where('user_id', $player->id)->count());
        $this->assertSame('B', $test->fresh()->result_tier);
        $this->assertSame(1, DB::table('coin_transactions')->where('idempotency_key', 'tier_test:'.$test->id)->count());
    }

    public function test_reward_service_is_idempotent_per_tier_test(): void
    {
        $tester = $this->tester();
        $player = $this->player();
        $test = $this->claimedPvpRequest($tester, $player);

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/complete", [
            'block_placing' => 12,
            'rotka' => 12,
            'movement' => 12,
            'aim' => 12,
            'game_sense' => 12,
        ])->assertOk();

        $after = (int) $player->fresh()->apex_coins;

        $this->assertSame(0, RewardService::forTierTest($test->fresh()), 'Повторное начисление должно вернуть 0');
        $this->assertSame($after, (int) $player->fresh()->apex_coins);
    }

    /**
     * ТЕКУЩЕЕ ПОВЕДЕНИЕ (баг): в config/apex.php есть first_test_bonus = 150,
     * но RewardService запрашивает его как ShopSettingService::getInt('tier_test.first_test_bonus', 0),
     * а get() возвращает переданный $default, если настройки нет в БД: `$default ?? config(...)`.
     * Ноль не null — поэтому надбавка за первую заявку не начисляется никогда.
     */
    public function test_first_test_bonus_from_config_is_never_paid(): void
    {
        $this->assertGreaterThan(0, (int) config('apex.coins.tier_test.first_test_bonus'));

        $tester = $this->tester();
        $player = $this->player();
        $test = $this->claimedPvpRequest($tester, $player);

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/complete", [
            'block_placing' => 12,
            'rotka' => 12,
            'movement' => 12,
            'aim' => 12,
            'game_sense' => 12,
        ])->assertOk();

        $this->assertDatabaseMissing('coin_transactions', [
            'idempotency_key' => 'tier_test_first:'.$player->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Отмена заявки тестером
    // ------------------------------------------------------------------

    public function test_cancel_marks_request_cancelled_with_a_reason(): void
    {
        $tester = $this->tester();
        $test = $this->claimedPvpRequest($tester, $this->player());

        $response = $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/cancel", [
            'reason' => 'игрок не вышел на связь',
        ]);

        $response->assertOk()->assertJsonPath('tier_test.status', 'cancelled');

        $test->refresh();

        $this->assertSame('cancelled', $test->status);
        $this->assertSame('игрок не вышел на связь', $test->notes);
    }

    public function test_cancel_is_forbidden_for_another_tester_and_for_unclaimed_requests(): void
    {
        $tester = $this->tester();
        $otherTester = $this->tester();
        $player = $this->player();

        $unclaimed = $this->request($player);

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$unclaimed->id}/cancel")
            ->assertForbidden();

        $claimed = $this->claimedPvpRequest($tester, $player);

        $this->actingAs($otherTester)->postJson("/api/tester/tier-tests/{$claimed->id}/cancel")
            ->assertForbidden();

        $this->assertSame('pending', $unclaimed->fresh()->status);
        $this->assertSame('in_progress', $claimed->fresh()->status);
    }

    public function test_cancel_validation_for_reason_length(): void
    {
        $tester = $this->tester();
        $test = $this->claimedPvpRequest($tester, $this->player());

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/cancel", [
            'reason' => str_repeat('x', 501),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->assertSame('in_progress', $test->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Полный цикл: заявка → тестер → награды → ачивки
    // ------------------------------------------------------------------

    /**
     * Проверяет, что начисленные за тир-тест монеты видны в кошельке.
     *
     * Исправлено: earnedBySource() больше не срезает ключи через array_values(),
     * поэтому в ответе приходит карта «источник => сумма».
     */
    public function test_wallet_reflects_coins_earned_from_a_tier_test(): void
    {
        $tester = $this->tester();
        $player = $this->player(['tier' => 'E']);
        $test = $this->claimedPvpRequest($tester, $player);

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/complete", [
            'block_placing' => 15,
            'rotka' => 15,
            'movement' => 15,
            'aim' => 15,
            'game_sense' => 15,
        ])->assertOk();

        $wallet = $this->actingAs($player->fresh())->getJson('/api/wallet')->assertOk();

        $this->assertSame((int) $player->fresh()->apex_coins, $wallet->json('balance'));
        $this->assertSame(0, $wallet->json('spent'));
        $this->assertSame((int) $player->fresh()->apex_coins, $wallet->json('earned'));

        $bySource = $wallet->json('earned_by_source');

        // Награда за тир-тест лежит под именем своего источника
        $this->assertSame(
            RewardService::coinsForTier('A'),
            $bySource['tier_test'] ?? null,
            'Начисление за тир-тест должно быть под ключом tier_test'
        );
    }

    public function test_full_tier_test_cycle_from_request_to_rewards(): void
    {
        $player = $this->player(['tier' => 'E']);
        $tester = $this->tester();

        // Игрок создаёт заявку
        $created = $this->actingAs($player)->postJson('/api/tier-tests', [
            'mode' => 'bedwars',
            'contact_type' => 'discord',
            'contact_value' => 'apex#777',
            'preferred_time' => 'сегодня',
        ])->assertCreated();

        $testId = $created->json('tier_test.id');

        // Тестер берёт и завершает
        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$testId}/claim")->assertOk();
        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$testId}/complete", [
            'pvp' => 12,
            'game_sense' => 12,
            'bed_play' => 12,
            'teamplay' => 12,
            'building' => 12,
        ])->assertOk();

        $this->assertSame('B', $player->fresh()->tier);
        $this->assertTrue($player->fresh()->hasAchievement('tier_b'));
        $this->assertTrue($player->fresh()->hasAchievement('tier_test_first'));
        $this->assertGreaterThan(0, (int) $player->fresh()->apex_coins);

        // Награда за тест ровно одна
        $this->assertSame(
            1,
            DB::table('coin_transactions')->where('idempotency_key', 'tier_test:'.$testId)->count()
        );

        // Тестер видит завершённый тест в своей статистике
        $stats = $this->actingAs($tester)->getJson('/api/tester/tier-tests/stats')->assertOk();

        $this->assertSame(1, $stats->json('total_completed'));
        $this->assertSame(1, $stats->json('today'));

        // История игрока содержит проведённый тест
        $history = $this->actingAs($player)->getJson("/api/players/{$player->id}/tier-history")->assertOk();

        $this->assertCount(1, $history->json('history'));
        $this->assertSame('B', $history->json('history.0.result_tier'));

        // Повторная выдача ачивки ничего не платит
        $coins = (int) $player->fresh()->apex_coins;
        $this->assertFalse(AchievementService::grant($player->fresh(), 'tier_b'));
        $this->assertSame($coins, (int) $player->fresh()->apex_coins);
    }
}
