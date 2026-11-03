<?php

namespace Tests\Feature;

use App\Domains\Minecraft\Models\MinecraftGrant;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MinecraftIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private array $server = ['X-Minecraft-Server-Key' => 'test-key'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.minecraft.server_key' => 'test-key']);
        Cache::flush();
    }

    private function duel(string $key, string $winner, string $loser, string $kit = 'nodebuff'): array
    {
        return ['match_key' => $key, 'kit' => $kit, 'winner' => $winner, 'loser' => $loser, 'duration_seconds' => 90, 'ranked' => true];
    }

    public function test_duels_require_server_key(): void
    {
        $this->postJson('/api/minecraft/duels', $this->duel('a', 'Alice', 'Bob'))->assertForbidden();
    }

    public function test_duel_is_recorded_once_and_shows_in_history_and_stats(): void
    {
        $a = User::factory()->create(['minecraft_username' => 'Alice']);
        $b = User::factory()->create(['minecraft_username' => 'Bob']);

        $this->postJson('/api/minecraft/duels', $this->duel('m1', 'alice', 'Bob'), $this->server)->assertOk();
        $this->postJson('/api/minecraft/duels', $this->duel('m1', 'alice', 'Bob'), $this->server)->assertOk();
        $this->postJson('/api/minecraft/duels', $this->duel('m2', 'Bob', 'Alice', 'sumo'), $this->server)->assertOk();
        $this->postJson('/api/minecraft/duels', $this->duel('m3', 'Alice', 'Bob'), $this->server)->assertOk();

        $res = $this->getJson("/api/players/{$a->id}/duels")->assertOk();

        $this->assertSame(3, $res->json('meta.total'));
        $this->assertSame(2, $res->json('stats.total.wins'));
        $this->assertSame(1, $res->json('stats.total.losses'));
        $this->assertSame(1, $res->json('stats.total.streak'));
        $this->assertSame('nodebuff', $res->json('stats.kits.0.kit'));
        $this->assertSame('Bob', $res->json('duels.0.opponent'));

        $this->getJson("/api/players/{$b->id}/duels?kit=sumo")->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('duels.0.won', true);
    }

    public function test_unlinked_player_names_are_still_stored(): void
    {
        $this->postJson('/api/minecraft/duels', $this->duel('x', 'Ghost', 'Nobody'), $this->server)->assertOk();
        $this->assertDatabaseHas('minecraft_duels', ['match_key' => 'x', 'winner_user_id' => null]);
    }

    public function test_lobby_perk_purchase_queues_grant_and_server_acknowledges(): void
    {
        $user = User::factory()->create(['apex_coins' => 5000, 'minecraft_username' => 'Alice']);
        $item = ShopItem::where('slug', 'lobby-fly')->firstOrFail();

        $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $this->assertSame(3500, (int) $user->fresh()->apex_coins);

        $grants = $this->getJson('/api/minecraft/grants', $this->server)->assertOk();
        $this->assertSame('Alice', $grants->json('grants.0.player'));
        $this->assertSame('lp user Alice permission set apex.lobby.fly true', $grants->json('grants.0.commands.0'));

        $id = $grants->json('grants.0.id');
        $this->postJson("/api/minecraft/grants/{$id}/ack", ['ok' => true], $this->server)->assertOk()
            ->assertJsonPath('status', 'done');
        $this->getJson('/api/minecraft/grants', $this->server)->assertJsonCount(0, 'grants');
    }

    public function test_lobby_perk_requires_linked_nickname_and_does_not_charge(): void
    {
        $user = User::factory()->create(['apex_coins' => 5000, 'minecraft_username' => null]);
        $item = ShopItem::where('slug', 'lobby-fly')->firstOrFail();

        $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertStatus(422);

        $this->assertSame(5000, (int) $user->fresh()->apex_coins);
        $this->assertSame(0, MinecraftGrant::count());
    }

    public function test_failed_grant_is_retried_then_marked_failed(): void
    {
        $user = User::factory()->create(['apex_coins' => 5000, 'minecraft_username' => 'Alice']);
        $item = ShopItem::where('slug', 'lobby-prefix-star')->firstOrFail();
        $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();
        $id = MinecraftGrant::firstOrFail()->id;

        for ($i = 0; $i < 5; $i++) {
            $this->postJson("/api/minecraft/grants/{$id}/ack", ['ok' => false, 'error' => 'offline'], $this->server)->assertOk();
        }

        $this->assertSame('failed', MinecraftGrant::find($id)->status);
    }

    public function test_presence_marks_player_online(): void
    {
        $user = User::factory()->create(['minecraft_username' => 'Alice']);

        $this->postJson('/api/minecraft/presence', ['players' => ['alice', 'Steve']], $this->server)->assertOk();

        $this->getJson("/api/players/{$user->id}/duels")->assertJsonPath('player.online', true);
        $this->getJson('/api/minecraft/online')->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('players.0.id', $user->id);
    }

    public function test_clan_board_responds(): void
    {
        $this->getJson('/api/minecraft/clan-duels')->assertOk()->assertJsonStructure(['clans']);
    }
}
