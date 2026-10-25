<?php

namespace Tests\Feature;

use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Публичная статистика: тиры, тесты по месяцам, без учёта персонала.
 */
class PublicStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_stats_are_public_and_have_expected_shape(): void
    {
        $this->getJson('/api/stats')
            ->assertOk()
            ->assertJsonStructure([
                'totals' => ['players', 'ranked', 'tests', 'clans', 'clan_wars'],
                'tiers' => [['tier', 'players']],
                'aspects' => [['key', 'average']],
                'tests_by_month' => [['month', 'tests']],
            ])
            ->assertJsonCount(6, 'tiers')
            ->assertJsonCount(6, 'tests_by_month');
    }

    public function test_tier_distribution_and_monthly_tests(): void
    {
        User::factory()->create(['tier' => 'B', 'tier_score' => 60]);
        User::factory()->create(['tier' => 'B', 'tier_score' => 62]);
        $player = User::factory()->create(['tier' => 'D', 'tier_score' => 30]);
        // Без рейтинга — не попадает в распределение
        User::factory()->create(['tier' => 'E', 'tier_score' => 0]);

        TierTest::withoutEvents(function () use ($player) {
            foreach ([now(), now(), now()->subMonths(2), now()->subYear()] as $date) {
                TierTest::create([
                    'user_id' => $player->id,
                    'mode' => 'pvp',
                    'status' => 'completed',
                    'result_tier' => 'D',
                    'result_score' => 30,
                    'completed_at' => $date,
                ]);
            }
        });

        $response = $this->getJson('/api/stats')->assertOk();

        $tiers = collect($response->json('tiers'))->pluck('players', 'tier');
        $this->assertSame(2, $tiers['B']);
        $this->assertSame(1, $tiers['D']);
        $this->assertSame(0, $tiers['E']);

        $months = collect($response->json('tests_by_month'))->pluck('tests', 'month');
        $this->assertSame(2, $months[now()->format('Y-m')]);
        $this->assertSame(1, $months[now()->startOfMonth()->subMonths(2)->format('Y-m')]);
        $this->assertSame(3, $months->sum());
        $this->assertSame(4, $response->json('totals.tests'));
    }
}
