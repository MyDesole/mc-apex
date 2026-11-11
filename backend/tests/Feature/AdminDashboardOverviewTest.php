<?php

namespace Tests\Feature;

use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Дашборд админки: лента событий, динамика и состояние сервера.
 */
class AdminDashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_gets_activity_growth_totals_and_system(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create(['role' => 'user']);
        TierTest::create(['user_id' => $player->id, 'mode' => 'pvp', 'status' => 'pending']);

        $response = $this->actingAs($admin)->getJson('/api/admin/dashboard')->assertOk();

        $response->assertJsonStructure([
            'activity' => [['type', 'title', 'text', 'at']],
            'growth' => [['date', 'signups', 'tests', 'topics']],
            'totals' => ['clans', 'forum_topics', 'tests_total', 'tickets_open'],
            'system' => ['app' => ['php', 'laravel'], 'host', 'database', 'queue', 'alerts'],
        ]);

        $types = collect($response->json('activity'))->pluck('type');
        $this->assertTrue($types->contains('signup'));
        $this->assertTrue($types->contains('test_new'));
        $this->assertCount(14, $response->json('growth'));
    }

    public function test_moderator_does_not_get_system_details(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator']);

        $this->actingAs($moderator)->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('system', null)
            ->assertJsonStructure(['activity', 'growth', 'totals']);
    }

    public function test_live_endpoint_returns_fresh_feed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->getJson('/api/admin/dashboard/live')
            ->assertOk()
            ->assertJsonStructure(['activity', 'online', 'attention', 'system']);
    }

    public function test_regular_user_cannot_open_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->getJson('/api/admin/dashboard/live')->assertForbidden();
    }
}
