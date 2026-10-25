<?php

namespace Tests\Feature;

use App\Domains\Bridge\Models\BridgeRank;
use App\Domains\Bridge\Models\BridgeTechnique;
use App\Domains\Bridge\Models\UserBridgeTechnique;
use App\Domains\Players\Services\RatingService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridgeDisplayRankTest extends TestCase
{
    use RefreshDatabase;

    private function rank(): BridgeRank
    {
        return BridgeRank::create(['key' => 'master', 'label' => 'Мастер моста', 'color' => '#22c55e', 'sort_order' => 1, 'is_active' => true]);
    }

    public function test_bridge_rank_replaces_tier_when_bridge_is_stronger(): void
    {
        $rank = $this->rank();
        $user = User::factory()->create(['tier' => 'C', 'bridge_rank_id' => $rank->id]);

        $technique = BridgeTechnique::create(['key' => 'godbridge', 'label' => 'Godbridge', 'sort_order' => 1]);
        UserBridgeTechnique::create([
            'user_id' => $user->id, 'technique_id' => $technique->id, 'status' => 'confirmed',
            'stability' => 90, 'speed' => 90, 'difficulty' => 90, 'score' => 9,
        ]);

        app(RatingService::class)->recalculate([$user->id]);

        $display = $user->fresh()->display_rank;
        $this->assertSame('bridge', $display['type']);
        $this->assertSame('Мастер моста', $display['label']);
        $this->assertSame('#22c55e', $display['color']);
    }

    public function test_tier_is_shown_without_rank_or_when_pvp_is_stronger(): void
    {
        $rank = $this->rank();

        $noRank = User::factory()->create(['tier' => 'B', 'bridge_primary' => true]);
        $this->assertSame(['type' => 'tier', 'label' => 'B', 'color' => null], $noRank->display_rank);

        $pvp = User::factory()->create(['tier' => 'A', 'bridge_rank_id' => $rank->id, 'bridge_primary' => false]);
        $this->assertSame('tier', $pvp->display_rank['type']);
    }

    public function test_ranking_exposes_display_rank(): void
    {
        $rank = $this->rank();
        User::factory()->create(['tier' => 'C', 'tier_score' => 40, 'rating_points' => 40, 'bridge_rank_id' => $rank->id, 'bridge_primary' => true]);

        $this->getJson('/api/ranking')
            ->assertOk()
            ->assertJsonPath('data.0.display_rank.label', 'Мастер моста');
    }
}
