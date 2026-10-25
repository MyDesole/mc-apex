<?php

namespace Tests\Feature;

use App\Domains\Bridge\Models\BridgeTechnique;
use App\Domains\Bridge\Models\UserBridgeTechnique;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridgeAdminTest extends TestCase
{
    use RefreshDatabase;

    private function submission(User $player): UserBridgeTechnique
    {
        $technique = BridgeTechnique::create(['key' => 'godbridge', 'label' => 'Godbridge', 'sort_order' => 1]);

        return UserBridgeTechnique::create([
            'user_id' => $player->id, 'technique_id' => $technique->id, 'status' => 'confirmed',
            'stability' => 100, 'speed' => 100, 'difficulty' => 100, 'score' => 10,
        ]);
    }

    public function test_admin_lowers_inflated_scores_and_rating_is_recalculated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create(['tier_score' => 0]);
        $submission = $this->submission($player);

        $this->actingAs($admin)->getJson("/api/admin/users/{$player->id}/bridge")
            ->assertOk()
            ->assertJsonPath('submissions.0.total', 300);

        $this->actingAs($admin)->putJson("/api/admin/bridge/submissions/{$submission->id}", [
            'status' => 'confirmed', 'stability' => 30, 'speed' => 30, 'difficulty' => 30, 'score' => 3,
        ])->assertOk()->assertJsonPath('submission.total', 90);

        $this->assertSame(30, $submission->fresh()->stability);
        $this->assertDatabaseHas('moderation_logs', ['action' => 'bridge.submission_updated', 'target_user_id' => $player->id]);
        // бридж 30% / 3 режима → 10 очков
        $this->assertSame(10, (int) $player->fresh()->rating_points);
    }

    public function test_confirmed_requires_all_scores_and_admin_can_delete(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create();
        $submission = $this->submission($player);

        $this->actingAs($admin)->putJson("/api/admin/bridge/submissions/{$submission->id}", ['status' => 'confirmed'])
            ->assertStatus(422);

        $this->actingAs($admin)->deleteJson("/api/admin/bridge/submissions/{$submission->id}")->assertOk();
        $this->assertDatabaseMissing('user_bridge_techniques', ['id' => $submission->id]);
    }

    public function test_non_admin_cannot_edit(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator']);
        $submission = $this->submission(User::factory()->create());

        $this->actingAs($moderator)->putJson("/api/admin/bridge/submissions/{$submission->id}", ['status' => 'rejected'])
            ->assertForbidden();
    }
}
