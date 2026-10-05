<?php

namespace Tests\Feature;

use App\Domains\Bridge\Models\BridgeRank;
use App\Domains\Bridge\Models\BridgeTechnique;
use App\Domains\Bridge\Services\BridgeService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Звания бриджера: вручную выдаёт бридж-тестер.
 *
 * В бридж-профиле звание — главный ранг, поэтому проверяем и профиль.
 */
class BridgeRankTest extends TestCase
{
    use RefreshDatabase;

    private function rank(string $label = 'Bridge Master', int $order = 1): BridgeRank
    {
        return BridgeRank::create([
            'key' => 'rank_' . $order,
            'label' => $label,
            'color' => '#fbbf24',
            'sort_order' => $order,
            'is_active' => true,
        ]);
    }

    private function service(): BridgeService
    {
        return app(BridgeService::class);
    }

    public function test_tester_assigns_rank(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);
        $rank = $this->rank();

        Sanctum::actingAs($tester);

        $this->postJson("/api/bridge-review/players/{$player->id}/rank", [
            'rank_id' => $rank->id,
        ])
            ->assertOk()
            ->assertJsonPath('rank.label', 'Bridge Master');

        $this->assertDatabaseHas('users', [
            'id' => $player->id,
            'bridge_rank_id' => $rank->id,
            'bridge_rank_by' => $tester->id,
        ]);
    }

    public function test_regular_player_cannot_assign_rank(): void
    {
        $player = User::factory()->create();
        $rank = $this->rank();

        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->postJson("/api/bridge-review/players/{$player->id}/rank", [
            'rank_id' => $rank->id,
        ])->assertForbidden();

        $this->assertNull($player->fresh()->bridge_rank_id);
    }

    public function test_rank_can_be_removed(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);

        $this->service()->assignRank($player, $tester, $this->rank()->id);

        Sanctum::actingAs($tester);

        $this->postJson("/api/bridge-review/players/{$player->id}/rank", [
            'rank_id' => null,
        ])->assertOk()->assertJsonPath('rank', null);

        $this->assertNull($player->fresh()->bridge_rank_id);
    }

    public function test_unknown_rank_is_rejected(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);

        Sanctum::actingAs($tester);

        $this->postJson("/api/bridge-review/players/{$player->id}/rank", [
            'rank_id' => 999,
        ])->assertStatus(422)->assertJsonValidationErrors(['rank_id']);
    }

    public function test_profile_returns_bridge_rank(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);

        $this->service()->assignRank($player, $tester, $this->rank('Bridge Профи')->id);

        Sanctum::actingAs($player);

        $this->getJson("/api/players/{$player->id}")
            ->assertOk()
            ->assertJsonPath('bridge_rank.label', 'Bridge Профи')
            ->assertJsonPath('bridge_rank.color', '#fbbf24');
    }

    public function test_profile_returns_mode(): void
    {
        $player = User::factory()->create(['profile_mode' => 'bridge']);

        Sanctum::actingAs($player);

        $this->getJson("/api/players/{$player->id}")
            ->assertOk()
            ->assertJsonPath('profile_mode', 'bridge');
    }

    public function test_profile_mode_is_pvp_by_default(): void
    {
        $player = User::factory()->create();

        Sanctum::actingAs($player);

        $this->getJson("/api/players/{$player->id}")
            ->assertOk()
            ->assertJsonPath('profile_mode', 'pvp');
    }

    public function test_player_can_switch_profile_mode(): void
    {
        $player = User::factory()->create();

        Sanctum::actingAs($player);

        $this->putJson('/api/players/me/profile', ['profile_mode' => 'bridge'])
            ->assertOk();

        $this->assertSame('bridge', $player->fresh()->profile_mode);
    }

    public function test_unknown_profile_mode_is_rejected(): void
    {
        $player = User::factory()->create();

        Sanctum::actingAs($player);

        $this->putJson('/api/players/me/profile', ['profile_mode' => 'bedwars'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['profile_mode']);
    }

    public function test_tester_sees_bridgers_with_ranks(): void
    {
        $player = User::factory()->create();
        $tester = User::factory()->create(['role' => 'bridge_tester']);

        $technique = BridgeTechnique::create([
            'key' => 'telly', 'label' => 'Telly', 'sort_order' => 1, 'is_active' => true,
        ]);

        $submission = $this->service()->declare($player, $technique->id, 'https://youtu.be/abc');

        $this->service()->confirm($submission, $tester, [
            'stability' => 50, 'speed' => 70, 'difficulty' => 70, 'score' => 8,
        ]);

        Sanctum::actingAs($tester);

        $this->getJson('/api/bridge-review/players')
            ->assertOk()
            ->assertJsonPath('data.0.user.id', $player->id)
            ->assertJsonPath('data.0.techniques_count', 1)
            ->assertJsonPath('data.0.aspects_total', 190);
    }

    public function test_ranks_catalog_is_available_to_tester(): void
    {
        $tester = User::factory()->create(['role' => 'bridge_tester']);

        $this->rank('Bridge Новичок', 1);
        $this->rank('Bridge Master', 2);

        Sanctum::actingAs($tester);

        $this->getJson('/api/bridge-review/ranks')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
