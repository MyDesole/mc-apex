<?php

namespace Tests\Feature;

use App\Domains\Minecraft\Models\MinecraftDuel;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DonorShowcaseTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $changes = []): array
    {
        return array_replace(['enabled' => true, 'mode' => 'fastbridge', 'theme' => 'aurora', 'title' => 'Мой мост', 'duel_id' => null, 'achievement_id' => null], $changes);
    }

    private function grant(User $user, string $rank = 'vip', mixed $expires = null): void
    {
        DB::table('server_rank_grants')->insert(['user_id' => $user->id, 'rank' => $rank, 'source' => 'admin', 'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_guests_cannot_change_showcase(): void
    {
        $this->putJson('/api/players/me/showcase', $this->payload())->assertUnauthorized();
    }

    public function test_regular_player_cannot_claim_paid_showcase(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->putJson('/api/players/me/showcase', $this->payload())->assertForbidden();
        $this->assertNull($user->fresh()->donor_showcase);
    }

    public function test_donor_can_publish_and_only_change_own_showcase(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->grant($user);
        $this->actingAs($user)->putJson('/api/players/me/showcase', $this->payload(['user_id' => $other->id, 'title' => '<b>Мой мост</b>']))
            ->assertOk()->assertJsonPath('visible', true)->assertJsonPath('rank', 'VIP')->assertJsonPath('settings.title', 'Мой мост');
        $this->assertSame('fastbridge', $user->fresh()->donor_showcase['mode']);
        $this->assertNull($other->fresh()->donor_showcase);
    }

    public function test_expired_rank_hides_showcase_but_preserves_settings(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create();
        $this->grant($user, 'apex', now()->addMinute());
        $this->actingAs($user)->putJson('/api/players/me/showcase', $this->payload())->assertOk();
        $this->travel(2)->minutes();
        $guest = User::factory()->create();
        $this->actingAs($guest)->getJson('/api/players/'.$user->id.'/showcase')->assertOk()->assertJsonPath('visible', false)->assertJsonPath('settings', null)->assertJsonPath('options', null);
        $this->assertSame('aurora', $user->fresh()->donor_showcase['theme']);
    }

    public function test_cannot_pin_another_players_match(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->grant($user);
        $duel = MinecraftDuel::create(['match_key' => 'someone-else', 'kit' => 'sumo', 'winner_name' => 'Other', 'loser_name' => 'Guest', 'winner_user_id' => $other->id, 'duration_seconds' => 10, 'ranked' => false, 'played_at' => now()]);
        $this->actingAs($user)->putJson('/api/players/me/showcase', $this->payload(['duel_id' => $duel->id]))->assertUnprocessable()->assertJsonValidationErrors('duel_id');
    }

    public function test_own_match_is_available_publicly_without_edit_options(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'legend');
        $duel = MinecraftDuel::create(['match_key' => 'own-match', 'kit' => 'sumo', 'winner_name' => 'Owner', 'loser_name' => 'Guest', 'winner_user_id' => $user->id, 'duration_seconds' => 12, 'ranked' => false, 'played_at' => now()]);
        $this->actingAs($user)->putJson('/api/players/me/showcase', $this->payload(['duel_id' => $duel->id]))->assertOk()->assertJsonPath('duel.opponent', 'Guest');
        $visitor = User::factory()->create();
        $this->actingAs($visitor)->getJson('/api/players/'.$user->id.'/showcase')->assertOk()->assertJsonPath('duel.won', true)->assertJsonPath('options', null);
    }

    public function test_invalid_mode_and_unearned_achievement_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->grant($user);
        $this->actingAs($user)->putJson('/api/players/me/showcase', $this->payload(['mode' => 'admin']))->assertUnprocessable()->assertJsonValidationErrors('mode');
        $this->actingAs($user)->putJson('/api/players/me/showcase', $this->payload(['achievement_id' => 9999]))->assertUnprocessable()->assertJsonValidationErrors('achievement_id');
    }

    public function test_owner_can_reset_saved_showcase_after_rank_expiry(): void
    {
        $user = User::factory()->create(['donor_showcase' => $this->payload()]);
        $this->actingAs($user)->deleteJson('/api/players/me/showcase')->assertOk()->assertJsonPath('settings', null);
        $this->assertNull($user->fresh()->donor_showcase);
    }
}
