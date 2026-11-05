<?php

namespace Tests\Feature;

use App\Domains\Bridge\Models\BridgeRank;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Публичный API клиентского мода APEX. */
class ModApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_finds_by_minecraft_nick_and_site_username_without_private_fields(): void
    {
        $user = User::factory()->create(['username' => 'SiteName', 'minecraft_username' => 'McName', 'tier' => 'A', 'tier_score' => 75]);

        foreach (['mcname', 'SITENAME'] as $query) {
            $res = $this->getJson('/api/mod/player/' . $query)->assertOk();
            $res->assertJsonPath('player.tier', 'A')
                ->assertJsonPath('player.tier_color', '#f97316')
                ->assertJsonPath('player.badge.type', 'tier')
                ->assertJsonPath('player.profile_url', rtrim(config('app.frontend_url'), '/') . '/players/' . $user->id);
            $this->assertStringNotContainsString($user->email, $res->getContent());
        }
    }

    public function test_bridge_rank_replaces_tier_with_short_label(): void
    {
        $rank = BridgeRank::create(['key' => 'pro', 'label' => 'BRIDGE ПРОФИ', 'color' => '#22c55e', 'sort_order' => 1, 'is_active' => true]);
        User::factory()->create([
            'minecraft_username' => 'Bridger', 'tier' => 'C', 'tier_score' => 45,
            'bridge_rank_id' => $rank->id, 'profile_mode' => 'bridge',
        ]);

        $this->getJson('/api/mod/player/Bridger')->assertOk()
            ->assertJsonPath('player.badge.type', 'bridge')
            ->assertJsonPath('player.badge.short', 'B.P')
            ->assertJsonPath('player.badge.color', '#22c55e');
    }

    public function test_unknown_and_banned_players_are_404(): void
    {
        User::factory()->create(['minecraft_username' => 'Banned', 'is_banned' => true]);

        $this->getJson('/api/mod/player/Nobody')->assertNotFound();
        $this->getJson('/api/mod/player/Banned')->assertNotFound();
    }

    public function test_batch_returns_only_known_minecraft_nicks(): void
    {
        User::factory()->create(['minecraft_username' => 'Alpha', 'tier' => 'S', 'tier_score' => 90]);
        User::factory()->create(['username' => 'OnlySite', 'minecraft_username' => null]);

        $res = $this->postJson('/api/mod/tiers', ['nicks' => ['alpha', 'OnlySite', 'ghost']])->assertOk();

        $this->assertCount(1, $res->json('players'));
        $this->assertSame('Alpha', $res->json('players.0.nick'));
        $this->assertSame('#facc15', $res->json('players.0.badge.color'));
    }

    public function test_batch_validates_size(): void
    {
        $this->postJson('/api/mod/tiers', ['nicks' => []])->assertStatus(422);
        $this->postJson('/api/mod/tiers', ['nicks' => array_map(fn ($i) => "n$i", range(1, 61))])->assertStatus(422);
    }
}
