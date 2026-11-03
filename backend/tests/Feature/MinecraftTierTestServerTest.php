<?php

namespace Tests\Feature;

use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Тир-тест с игрового сервера: приглашение закрепляет заявку за тестером,
 * итог дуэлей пишется в заметки заявки.
 */
class MinecraftTierTestServerTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-server-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.minecraft.server_key' => self::KEY]);
    }

    private function server()
    {
        return $this->withHeaders(['X-Minecraft-Server-Key' => self::KEY]);
    }

    private function test(User $player, array $extra = []): TierTest
    {
        return TierTest::create(array_merge([
            'user_id' => $player->id,
            'mode' => 'pvp',
            'contact_type' => 'discord',
            'contact_value' => 'apex#1',
            'preferred_time' => 'вечером',
            'status' => 'pending',
        ], $extra));
    }

    public function test_invite_claims_pending_test_for_tester(): void
    {
        $player = User::factory()->create(['minecraft_username' => 'Player1']);
        $tester = User::factory()->create(['minecraft_username' => 'Tester1']);
        $test = $this->test($player);

        $this->server()->postJson("/api/minecraft/tier-tests/{$test->id}/invite", [
            'tester' => 'Tester1',
            'kits' => ['Boxing'],
            'rounds' => 3,
        ])->assertOk()->assertJsonPath('test.status', 'in_progress');

        $this->assertSame($tester->id, $test->fresh()->tester_id);
    }

    public function test_invite_rejects_foreign_test_and_unknown_tester(): void
    {
        $player = User::factory()->create(['minecraft_username' => 'Player1']);
        $owner = User::factory()->create(['minecraft_username' => 'Owner']);
        User::factory()->create(['minecraft_username' => 'Other']);
        $test = $this->test($player, ['status' => 'in_progress', 'tester_id' => $owner->id, 'claimed_by' => $owner->id]);

        $this->server()->postJson("/api/minecraft/tier-tests/{$test->id}/invite", ['tester' => 'Other'])
            ->assertStatus(422);
        $this->server()->postJson("/api/minecraft/tier-tests/{$test->id}/invite", ['tester' => 'Nobody'])
            ->assertStatus(422);
    }

    public function test_finish_appends_duel_summary_to_notes(): void
    {
        $player = User::factory()->create(['minecraft_username' => 'Player1']);
        $tester = User::factory()->create(['minecraft_username' => 'Tester1']);
        $test = $this->test($player, ['status' => 'in_progress', 'tester_id' => $tester->id, 'claimed_by' => $tester->id]);

        $this->server()->postJson("/api/minecraft/tier-tests/{$test->id}/finish", [
            'subject_wins' => 2,
            'tester_wins' => 1,
            'draws' => 0,
            'completed' => true,
            'games' => [
                ['kit' => 'Boxing', 'winner' => 'subject'],
                ['kit' => 'Bridge', 'winner' => 'tester'],
                ['kit' => 'Bridge', 'winner' => 'subject'],
            ],
        ])->assertOk();

        $notes = $test->fresh()->notes;
        $this->assertStringContainsString('игрок 2 : 1 тестер', $notes);
        $this->assertStringContainsString('2. Bridge - тестер', $notes);
        $this->assertSame('in_progress', $test->fresh()->status);
    }

    public function test_server_key_is_required(): void
    {
        $this->postJson('/api/minecraft/tier-tests/1/finish', [])->assertStatus(403);
    }
}
