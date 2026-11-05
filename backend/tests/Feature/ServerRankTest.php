<?php

namespace Tests\Feature;

use App\Domains\Minecraft\Models\MinecraftGrant;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerRankTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function linked(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['minecraft_username' => 'Steve_1']);
    }

    public function test_admin_grants_temporary_rank_through_luckperms_queue(): void
    {
        $player = $this->linked();

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$player->id}/ranks", ['rank' => 'mvp_plus', 'days' => 30])
            ->assertOk();

        $grant = MinecraftGrant::first();
        $this->assertSame('Steve_1', $grant->minecraft_username);
        $this->assertSame(['lp user Steve_1 parent addtemp mvp_plus 30d replace'], $grant->commands);
        $this->assertDatabaseHas('server_rank_grants', ['user_id' => $player->id, 'rank' => 'mvp_plus', 'source' => 'admin']);
    }

    public function test_permanent_rank_and_revoke(): void
    {
        $player = $this->linked();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson("/api/admin/users/{$player->id}/ranks", ['rank' => 'apex'])->assertOk();
        $this->assertSame(['lp user Steve_1 parent add apex'], MinecraftGrant::first()->commands);

        $this->actingAs($admin)->deleteJson("/api/admin/users/{$player->id}/ranks/apex")->assertOk();
        $this->assertSame(['lp user Steve_1 parent remove apex'], MinecraftGrant::orderByDesc('id')->first()->commands);
        $this->assertDatabaseMissing('server_rank_grants', ['user_id' => $player->id, 'rank' => 'apex']);
    }

    public function test_moderator_cannot_grant_ranks(): void
    {
        $player = $this->linked();
        $mod = User::factory()->create(['role' => 'moderator']);

        $this->actingAs($mod)->postJson("/api/admin/users/{$player->id}/ranks", ['rank' => 'vip'])->assertForbidden();
    }

    public function test_unknown_rank_is_rejected(): void
    {
        $player = $this->linked();

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$player->id}/ranks", ['rank' => 'god'])
            ->assertUnprocessable();
    }

    public function test_tester_role_gives_and_takes_tester_rank(): void
    {
        $player = $this->linked();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson("/api/admin/users/{$player->id}/role", ['role' => 'tester'])->assertOk();
        $this->assertDatabaseHas('server_rank_grants', ['user_id' => $player->id, 'rank' => 'tester', 'source' => 'role']);
        $this->assertSame(['lp user Steve_1 parent add tester'], MinecraftGrant::first()->commands);

        $this->actingAs($admin)->postJson("/api/admin/users/{$player->id}/role", ['role' => 'user'])->assertOk();
        $this->assertDatabaseMissing('server_rank_grants', ['user_id' => $player->id, 'rank' => 'tester']);
        $this->assertSame(['lp user Steve_1 parent remove tester'], MinecraftGrant::orderByDesc('id')->first()->commands);
    }

    public function test_role_change_keeps_manually_granted_ranks(): void
    {
        $player = $this->linked();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson("/api/admin/users/{$player->id}/ranks", ['rank' => 'tester', 'days' => 10])->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/users/{$player->id}/role", ['role' => 'media'])->assertOk();

        $this->assertDatabaseHas('server_rank_grants', ['user_id' => $player->id, 'rank' => 'tester', 'source' => 'admin']);
        $this->assertDatabaseHas('server_rank_grants', ['user_id' => $player->id, 'rank' => 'media', 'source' => 'role']);
    }

    public function test_rank_without_nick_waits_and_is_delivered_on_link(): void
    {
        $player = User::factory()->create(['minecraft_username' => null]);

        $this->actingAs($this->admin())->postJson("/api/admin/users/{$player->id}/ranks", ['rank' => 'mvp'])->assertOk();
        $this->assertSame(0, MinecraftGrant::count());

        $this->actingAs($player)->postJson('/api/players/me/minecraft/link', ['nickname' => 'Alex_77'])->assertSuccessful();

        $grant = MinecraftGrant::first();
        $this->assertNotNull($grant);
        $this->assertSame(['lp user Alex_77 parent add mvp'], $grant->commands);
    }

    public function test_setup_commands_cover_every_group(): void
    {
        $commands = $this->actingAs($this->admin())->getJson('/api/admin/server-ranks/setup')->assertOk()->json('commands');

        foreach (['mvp', 'mvp_plus', 'apex', 'tester'] as $group) {
            $this->assertContains("lp creategroup {$group}", $commands);
        }
    }
}
