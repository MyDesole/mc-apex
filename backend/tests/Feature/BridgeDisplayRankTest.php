<?php

namespace Tests\Feature;

use App\Domains\Bridge\Models\BridgeRank;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Звание бриджера вместо тира — только у тех, кто выбрал bridge-профиль.
 */
class BridgeDisplayRankTest extends TestCase
{
    use RefreshDatabase;

    private function rank(): BridgeRank
    {
        return BridgeRank::create(['key' => 'master', 'label' => 'Мастер моста', 'color' => '#22c55e', 'sort_order' => 1, 'is_active' => true]);
    }

    public function test_bridge_profile_with_rank_shows_rank(): void
    {
        $rank = $this->rank();
        $user = User::factory()->create(['tier' => 'C', 'bridge_rank_id' => $rank->id, 'profile_mode' => 'bridge']);

        $this->assertSame(
            ['type' => 'bridge', 'label' => 'Мастер моста', 'color' => '#22c55e'],
            $user->fresh()->display_rank,
        );
    }

    public function test_pvp_profile_shows_tier_even_with_rank_and_strong_bridge(): void
    {
        $rank = $this->rank();
        $user = User::factory()->create([
            'tier' => 'A', 'bridge_rank_id' => $rank->id, 'profile_mode' => 'pvp', 'bridge_primary' => true,
        ]);

        $this->assertSame(['type' => 'tier', 'label' => 'A', 'color' => null], $user->fresh()->display_rank);

        $this->getJson("/api/players/{$user->id}")->assertOk()->assertJsonPath('profile_mode', 'pvp');
    }

    public function test_bridge_profile_without_rank_shows_tier(): void
    {
        $user = User::factory()->create(['tier' => 'B', 'profile_mode' => 'bridge']);

        $this->assertSame('tier', $user->fresh()->display_rank['type']);
    }

    public function test_ranking_exposes_display_rank(): void
    {
        $rank = $this->rank();
        User::factory()->create([
            'tier' => 'C', 'tier_score' => 40, 'rating_points' => 40,
            'bridge_rank_id' => $rank->id, 'profile_mode' => 'bridge',
        ]);

        $this->getJson('/api/ranking')
            ->assertOk()
            ->assertJsonPath('data.0.display_rank.label', 'Мастер моста');
    }
}
