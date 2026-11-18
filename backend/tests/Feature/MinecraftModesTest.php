<?php

namespace Tests\Feature;

use App\Domains\Minecraft\Models\MinecraftDuel;
use App\Domains\Minecraft\Models\MinecraftGrant;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MinecraftModesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_page_and_filter_recent_matches(): void
    {
        $owner = User::factory()->create();
        for ($i = 0; $i < 21; $i++) {
            MinecraftDuel::create(['match_key' => 'recent-'.$i, 'kit' => 'sumo', 'winner_name' => 'Winner', 'loser_name' => 'Loser', 'winner_user_id' => $owner->id, 'duration_seconds' => 12, 'ranked' => false, 'played_at' => now()->subMinutes(21 - $i)]);
        }
        MinecraftDuel::create(['match_key' => 'other-kit', 'kit' => 'boxing', 'winner_name' => 'Boxer', 'loser_name' => 'Other', 'duration_seconds' => 8, 'ranked' => false, 'played_at' => now()]);
        $this->getJson('/api/minecraft/duels/recent?kit=SUMO')->assertOk()->assertJsonCount(20, 'duels')->assertJsonPath('meta.total', 21)->assertJsonPath('duels.0.id', MinecraftDuel::where('match_key', 'recent-20')->value('id'))->assertJsonMissing(['email' => $owner->email]);
        $this->getJson('/api/minecraft/duels/recent?kit=sumo&page=2')->assertOk()->assertJsonCount(1, 'duels')->assertJsonPath('meta.current_page', 2);
        $this->getJson('/api/minecraft/duels/recent?kit=absent')->assertOk()->assertJsonCount(0, 'duels');
        $this->getJson('/api/minecraft/duels/recent?page=0')->assertUnprocessable();
    }

    public function test_delayed_delivery_keeps_time_and_accepts_small_clock_skew(): void
    {
        config(['services.minecraft.server_key' => 'test-key']);
        $headers = ['X-Minecraft-Server-Key' => 'test-key'];
        $body = ['match_key' => 'delayed-match', 'kit' => 'sumo', 'winner' => 'Alice', 'loser' => 'Bob', 'duration_seconds' => 12, 'ranked' => false, 'played_at' => now()->subDay()->toIso8601String()];
        $this->postJson('/api/minecraft/duels', $body, $headers)->assertOk();
        $this->assertSame($body['played_at'], MinecraftDuel::first()->played_at->toIso8601String());
        $this->postJson('/api/minecraft/duels', $body, $headers)->assertOk();
        $this->assertDatabaseCount('minecraft_duels', 1);
        $body['match_key'] = 'clock-skew';
        $body['played_at'] = now()->addSeconds(30)->toIso8601String();
        $this->postJson('/api/minecraft/duels', $body, $headers)->assertOk();
        $body['played_at'] = now()->addDay()->toIso8601String();
        $this->postJson('/api/minecraft/duels', $body, $headers)->assertUnprocessable()->assertJsonValidationErrors('played_at');
    }

    public function test_lavka_showcase_requires_completed_unexpired_delivery(): void
    {
        $user = User::factory()->create();
        $grant = MinecraftGrant::create(['user_id' => $user->id, 'minecraft_username' => 'Alice', 'commands' => [], 'status' => MinecraftGrant::PENDING]);
        DB::table('privilege_orders')->insert(['user_id' => $user->id, 'item_key' => 'vip_7', 'price' => 100, 'grant_id' => $grant->id, 'created_at' => now(), 'updated_at' => now()]);
        $url = '/api/players/'.$user->id.'/showcase';
        $this->getJson($url)->assertOk()->assertJsonPath('eligible', false);
        $grant->update(['status' => MinecraftGrant::DONE, 'delivered_at' => now()]);
        $this->getJson($url)->assertOk()->assertJsonPath('eligible', true)->assertJsonPath('rank', 'VIP');
        $grant->update(['delivered_at' => now()->subDays(8)]);
        $this->getJson($url)->assertOk()->assertJsonPath('eligible', false);
    }
}
