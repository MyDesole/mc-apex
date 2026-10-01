<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\TierTest;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\RewardService;
use App\Services\ShopSettingService;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AchievementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AchievementSeeder::class);
    }

    private function player(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['apex_coins' => 0], $attributes));
    }

    /**
     * Прогоняет тир-тест через ручку тестера и возвращает заявку.
     */
    private function completeTierTest(User $player, int $sum = 60, string $mode = 'pvp'): TierTest
    {
        $tester = User::factory()->tester()->create();

        $test = TierTest::create([
            'user_id' => $player->id,
            'mode' => $mode,
            'contact_type' => 'discord',
            'contact_value' => 'apex#1',
            'preferred_time' => 'сегодня',
            'status' => 'pending',
        ]);

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/claim")->assertOk();

        $per = (int) ($sum / 5);

        $payload = $mode === 'pvp'
            ? [
                'block_placing' => $per,
                'rotka' => $per,
                'movement' => $per,
                'aim' => $per,
                'game_sense' => $per,
            ]
            : [
                'pvp' => $per,
                'game_sense' => $per,
                'bed_play' => $per,
                'teamplay' => $per,
                'building' => $per,
            ];

        $this->actingAs($tester)->postJson("/api/tester/tier-tests/{$test->id}/complete", $payload)->assertOk();

        return $test->fresh();
    }

    // ------------------------------------------------------------------
    // Доступ и выдача списка
    // ------------------------------------------------------------------

    public function test_guest_gets_401_on_achievement_endpoints(): void
    {
        $this->getJson('/api/achievements')->assertUnauthorized();
        $this->getJson('/api/players/1/achievements')->assertUnauthorized();
    }

    public function test_index_lists_every_achievement_with_earned_flag(): void
    {
        $player = $this->player();

        $response = $this->actingAs($player)->getJson('/api/achievements');

        $response->assertOk()
            ->assertJsonStructure([
                'achievements' => [[
                    'id', 'code', 'name', 'description', 'icon',
                    'color', 'rarity', 'points', 'earned', 'earned_at',
                ]],
                'earned_count',
                'total_count',
                'points',
            ]);

        $this->assertSame(Achievement::count(), $response->json('total_count'));
        $this->assertSame(0, $response->json('earned_count'));
        $this->assertSame(0, $response->json('points'));

        foreach ($response->json('achievements') as $achievement) {
            $this->assertFalse($achievement['earned']);
            $this->assertNull($achievement['earned_at']);
        }
    }

    public function test_index_reflects_granted_achievements_and_points(): void
    {
        $player = $this->player();

        AchievementService::grant($player, 'tier_e');

        $response = $this->actingAs($player)->getJson('/api/achievements')->assertOk();

        $this->assertSame(1, $response->json('earned_count'));

        $tierE = Achievement::byCode('tier_e');

        $this->assertSame((int) $tierE->points, $response->json('points'));

        $earnedRow = collect($response->json('achievements'))->firstWhere('code', 'tier_e');

        $this->assertTrue($earnedRow['earned']);
        $this->assertNotNull($earnedRow['earned_at']);
    }

    public function test_index_is_ordered_by_points(): void
    {
        $points = collect($this->actingAs($this->player())->getJson('/api/achievements')->json('achievements'))
            ->pluck('points')
            ->all();

        $sorted = $points;
        sort($sorted);

        $this->assertSame($sorted, $points);
    }

    public function test_achievements_of_another_player_are_visible_to_authenticated_users(): void
    {
        $player = $this->player();
        $viewer = $this->player();

        AchievementService::grant($player, 'tier_e');

        $response = $this->actingAs($viewer)->getJson("/api/players/{$player->id}/achievements");

        $response->assertOk()
            ->assertJsonStructure(['achievements', 'points']);

        $this->assertCount(1, $response->json('achievements'));
        $this->assertSame('tier_e', $response->json('achievements.0.code'));
        $this->assertSame((int) Achievement::byCode('tier_e')->points, $response->json('points'));

        // Чужой профиль не отдаёт лишние поля
        $this->assertArrayNotHasKey('earned', $response->json('achievements.0'));
    }

    public function test_achievements_of_player_without_any_are_empty(): void
    {
        $player = $this->player();

        $this->actingAs($player)->getJson("/api/players/{$player->id}/achievements")
            ->assertOk()
            ->assertJsonPath('achievements', [])
            ->assertJsonPath('points', 0);
    }

    // ------------------------------------------------------------------
    // Выдача ачивок и награда в ApexCoin
    // ------------------------------------------------------------------

    public function test_grant_awards_coins_once_and_is_idempotent(): void
    {
        $player = $this->player();

        $achievement = Achievement::byCode('tier_c');
        $expected = RewardService::coinsForAchievement($achievement);

        $this->assertGreaterThan(0, $expected);

        $this->assertTrue(AchievementService::grant($player, 'tier_c'));
        $this->assertSame($expected, (int) $player->fresh()->apex_coins);

        // Повторная выдача ачивки не платит второй раз
        $this->assertFalse(AchievementService::grant($player->fresh(), 'tier_c'));
        $this->assertSame($expected, (int) $player->fresh()->apex_coins);

        // Повторное начисление через RewardService тоже ничего не добавляет
        RewardService::forAchievement($player->fresh(), $achievement);
        $this->assertSame($expected, (int) $player->fresh()->apex_coins);

        $this->assertSame(
            1,
            DB::table('coin_transactions')
                ->where('user_id', $player->id)
                ->where('source', 'achievement')
                ->count()
        );
    }

    public function test_achievement_reward_is_written_to_the_ledger_with_idempotency_key(): void
    {
        $player = $this->player(['apex_coins' => 100]);

        $achievement = Achievement::byCode('tier_d');
        $expected = RewardService::coinsForAchievement($achievement);

        AchievementService::grant($player, 'tier_d');

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $player->id,
            'source' => 'achievement',
            'amount' => $expected,
            'balance_after' => 100 + $expected,
            'idempotency_key' => 'achievement:'.$player->id.':'.$achievement->id,
            'reference_type' => Achievement::class,
            'reference_id' => $achievement->id,
        ]);
    }

    public function test_coin_reward_column_overrides_the_formula(): void
    {
        $player = $this->player();

        $achievement = Achievement::create([
            'code' => 'test_flat_reward',
            'name' => 'Плоская награда',
            'description' => 'Тестовая ачивка с явной наградой',
            'icon' => 'coin',
            'color' => '#facc15',
            'rarity' => 'common',
            'points' => 100,
            'coin_reward' => 7,
            'is_active' => true,
            'is_system' => false,
        ]);

        $this->assertSame(7, RewardService::coinsForAchievement($achievement));

        AchievementService::grant($player, 'test_flat_reward');

        $this->assertSame(7, (int) $player->fresh()->apex_coins);
    }

    public function test_achievement_formula_is_base_plus_points_times_per_point(): void
    {
        $achievement = Achievement::create([
            'code' => 'test_formula',
            'name' => 'Формула',
            'description' => 'Тестовая ачивка без coin_reward',
            'icon' => 'coin',
            'color' => '#facc15',
            'rarity' => 'common',
            'points' => 30,
            'coin_reward' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        $base = (int) config('apex.coins.achievement.base');
        $perPoint = (int) config('apex.coins.achievement.per_point');

        $this->assertSame($base + 30 * $perPoint, RewardService::coinsForAchievement($achievement));
    }

    public function test_achievement_coins_can_be_switched_off_by_source_toggle(): void
    {
        $player = $this->player();

        ShopSettingService::put('sources', array_merge(
            config('apex.coins.sources'),
            ['achievement' => false]
        ));

        $achievement = Achievement::byCode('tier_e');

        $this->assertSame(0, RewardService::coinsForAchievement($achievement));

        // Ачивка всё равно выдаётся — просто без монет
        $this->assertTrue(AchievementService::grant($player, 'tier_e'));
        $this->assertSame(0, (int) $player->fresh()->apex_coins);
        $this->assertTrue($player->fresh()->hasAchievement('tier_e'));
    }

    public function test_grant_returns_false_for_unknown_code(): void
    {
        $player = $this->player();

        $this->assertFalse(AchievementService::grant($player, 'nope_code'));
        $this->assertSame(0, (int) $player->fresh()->apex_coins);
        $this->assertCount(0, $player->fresh()->achievements);
    }

    // ------------------------------------------------------------------
    // Ачивки как следствие тир-теста
    // ------------------------------------------------------------------

    public function test_completing_a_tier_test_grants_tier_achievements(): void
    {
        $player = $this->player(['tier' => 'E']);

        // Сумма 60 → тир B
        $test = $this->completeTierTest($player, 60);

        $this->assertSame('B', $test->result_tier);

        $player->refresh();

        $this->assertSame('B', $player->tier);

        foreach (['tier_e', 'tier_d', 'tier_c', 'tier_b', 'tier_test_first'] as $code) {
            $this->assertTrue($player->hasAchievement($code), "Ачивка {$code} должна быть выдана");
        }

        $this->assertFalse($player->hasAchievement('tier_a'), 'Тир A ещё не достигнут');
        $this->assertFalse($player->hasAchievement('tier_s'), 'S за тир-тесты не выдаётся');
    }

    public function test_completing_a_tier_test_pays_tier_coins_and_achievement_coins(): void
    {
        $player = $this->player(['tier' => 'E']);

        $test = $this->completeTierTest($player, 75);

        $this->assertSame('A', $test->result_tier);

        $tierCoins = RewardService::coinsForTier('A');

        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $player->id,
            'source' => 'tier_test',
            'amount' => $tierCoins,
            'idempotency_key' => 'tier_test:'.$test->id,
        ]);

        $achievementCoins = (int) DB::table('coin_transactions')
            ->where('user_id', $player->id)
            ->where('source', 'achievement')
            ->sum('amount');

        $this->assertGreaterThan(0, $achievementCoins);

        // Баланс = сумма всех начислений в леджере (истина — леджер)
        $ledgerTotal = (int) DB::table('coin_transactions')->where('user_id', $player->id)->sum('amount');

        $this->assertSame($ledgerTotal, (int) $player->fresh()->apex_coins);
    }

    public function test_tier_test_completion_does_not_grant_achievements_twice(): void
    {
        $player = $this->player(['tier' => 'E']);

        $test = $this->completeTierTest($player, 60);

        $coinsAfterFirst = (int) $player->fresh()->apex_coins;
        $rowsAfterFirst = DB::table('user_achievements')->where('user_id', $player->id)->count();

        AchievementService::check($player->fresh());
        RewardService::forTierTest($test);

        $this->assertSame($coinsAfterFirst, (int) $player->fresh()->apex_coins);
        $this->assertSame($rowsAfterFirst, DB::table('user_achievements')->where('user_id', $player->id)->count());
    }

    public function test_tier_achievements_are_reported_through_the_api_after_completion(): void
    {
        $player = $this->player(['tier' => 'E']);

        $this->completeTierTest($player, 45);

        $response = $this->actingAs($player->fresh())->getJson('/api/achievements');

        $response->assertOk();

        $this->assertGreaterThan(0, $response->json('earned_count'));
        $this->assertGreaterThan(0, $response->json('points'));

        $earnedCodes = collect($response->json('achievements'))
            ->where('earned', true)
            ->pluck('code')
            ->all();

        $this->assertContains('tier_c', $earnedCodes);
        $this->assertContains('tier_test_first', $earnedCodes);
    }
}
