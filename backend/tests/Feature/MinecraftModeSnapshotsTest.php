<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MinecraftModeSnapshotsTest extends TestCase
{
    use RefreshDatabase;

    private const ALICE = '00000000-0000-4000-8000-000000000001';

    private const BOB = '00000000-0000-4000-8000-000000000002';

    private array $headers = ['X-Minecraft-Server-Key' => 'mode-test'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.minecraft.server_key' => 'mode-test']);
        Cache::flush();
    }

    private function snapshot(): array
    {
        $bw = fn ($uuid, $name, $wins) => ['uuid' => $uuid, 'name' => $name, 'wins' => $wins, 'losses' => 2, 'games' => $wins + 2, 'kills' => 5, 'final_kills' => 3, 'deaths' => 2, 'final_deaths' => 1, 'beds' => 1];
        $fb = fn ($uuid, $name, $map, $best) => ['uuid' => $uuid, 'name' => $name, 'map' => $map, 'best' => $best, 'attempts24' => 3, 'finishes24' => 2];
        $history = fn ($uuid, $outcome) => ['uuid' => $uuid, 'map' => '32', 'public' => true, 'at' => now()->getTimestampMs(), 'outcome' => $outcome, 'elapsed' => 2000, 'value' => 2000];

        return ['captured_at' => now()->toIso8601String(), 'bedwars' => ['configured' => true, 'online' => 0, 'players' => [$bw(self::ALICE, 'Alice', 8), $bw(self::BOB, 'Bob', 2)], 'arenas' => [['id' => 'amazon', 'name' => 'Amazon', 'teams' => 8, 'team_size' => 2, 'players' => 0, 'max_players' => 16, 'state' => 'waiting']]], 'fastbridge' => ['configured' => true, 'online' => 0, 'tables' => ['current' => [$fb(self::ALICE, 'Alice', '32', 2000), $fb(self::BOB, 'Bob', '32', 1500), $fb(self::ALICE, 'Alice', 'inf', 100), $fb(self::BOB, 'Bob', 'inf', 200)], 'islands' => [$fb(self::ALICE, 'Alice', '32', 1000)], 'classic' => []], 'history' => [$history(self::ALICE, 'AliceFinish'), $history(self::BOB, 'BobFinish')]]];
    }

    public function test_only_server_can_write_and_empty_native_tables_are_valid(): void
    {
        $body = $this->snapshot();
        $this->postJson('/api/minecraft/modes/snapshot', $body)->assertForbidden();
        $body['bedwars']['players'] = [];
        $body['bedwars']['arenas'] = [];
        $body['fastbridge']['tables'] = [];
        $body['fastbridge']['history'] = [];
        $this->postJson('/api/minecraft/modes/snapshot', $body, $this->headers)->assertOk();
        $this->getJson('/api/minecraft/modes/bedwars')->assertOk()->assertJsonPath('configured', true)->assertJsonPath('summary.players', 0);
    }

    public function test_public_native_rankings_sort_correctly_and_archives_stay_separate(): void
    {
        $this->postJson('/api/minecraft/modes/snapshot', $this->snapshot(), $this->headers)->assertOk();
        $this->getJson('/api/minecraft/modes/bedwars')->assertOk()->assertJsonPath('players.0.name', 'Alice')->assertJsonPath('summary.beds', 2)->assertJsonPath('arenas.0.name', 'Amazon')->assertJsonMissingPath('players.0.uuid');
        $this->getJson('/api/minecraft/modes/fastbridge?distance=32')->assertOk()->assertJsonPath('players.0.name', 'Bob')->assertJsonPath('summary.attempts24', 6)->assertJsonCount(0, 'history');
        $this->getJson('/api/minecraft/modes/fastbridge?distance=inf')->assertOk()->assertJsonPath('players.0.best', 200);
        $this->getJson('/api/minecraft/modes/fastbridge?distance=32&table=islands')->assertOk()->assertJsonPath('players.0.best', 1000)->assertJsonPath('meta.total', 1);
    }

    public function test_detailed_history_is_only_returned_to_its_owner(): void
    {
        $alice = User::factory()->create(['minecraft_username' => 'Alice', 'minecraft_uuid' => self::ALICE]);
        $bob = User::factory()->create(['minecraft_username' => 'Bob', 'minecraft_uuid' => self::BOB]);
        $this->postJson('/api/minecraft/modes/snapshot', $this->snapshot(), $this->headers)->assertOk();
        $url = '/api/minecraft/modes/fastbridge?user='.$alice->id;
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'history');
        $this->actingAs($bob)->getJson($url)->assertOk()->assertJsonCount(0, 'history');
        $this->actingAs($alice)->getJson($url)->assertOk()->assertJsonCount(1, 'history')->assertJsonPath('history.0.outcome', 'AliceFinish')->assertJsonMissingPath('history.0.uuid');
    }

    public function test_private_payload_is_rejected_and_old_capture_cannot_replace_new(): void
    {
        $body = $this->snapshot();
        $this->postJson('/api/minecraft/modes/snapshot', $body, $this->headers)->assertOk();
        $old = $body;
        $old['captured_at'] = now()->subDay()->toIso8601String();
        $old['bedwars']['players'][0]['wins'] = 999;
        $this->postJson('/api/minecraft/modes/snapshot', $old, $this->headers)->assertOk();
        $this->getJson('/api/minecraft/modes/bedwars')->assertJsonPath('players.0.wins', 8);
        $body['fastbridge']['history'][0]['public'] = false;
        $this->postJson('/api/minecraft/modes/snapshot', $body, $this->headers)->assertUnprocessable();
        $this->getJson('/api/minecraft/modes/fastbridge?distance=bad')->assertUnprocessable();
        $this->getJson('/api/minecraft/modes/duels')->assertNotFound();
    }

    public function test_real_native_fixture_matches_endpoint_contract(): void
    {
        $body = json_decode(file_get_contents(base_path('tests/Fixtures/native_modes_snapshot.json')), true, flags: JSON_THROW_ON_ERROR);
        $body['captured_at'] = now()->toIso8601String();
        $this->postJson('/api/minecraft/modes/snapshot', $body, $this->headers)->assertOk();
        $this->getJson('/api/minecraft/modes/fastbridge')->assertOk()->assertJsonPath('players.0.best', 2345);
    }
}
