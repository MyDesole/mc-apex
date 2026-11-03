<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\ClanWar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ClanFixtures;
use Tests\TestCase;

/**
 * Клановые войны с игрового сервера: лидер видит принятые войны,
 * итог матча закрывает ClanWar.
 */
class MinecraftClanWarServerTest extends TestCase
{
    use ClanFixtures;
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

    /** @return array{0: \App\Domains\Clan\Models\ClanWar, 1: \App\Domains\Users\Models\User, 2: \App\Domains\Users\Models\User} */
    private function acceptedWar(): array
    {
        $a = $this->user(['minecraft_username' => 'LeaderA']);
        $b = $this->user(['minecraft_username' => 'LeaderB']);
        $clanA = $this->clan($a);
        $clanB = $this->clan($b);

        $war = ClanWar::create([
            'challenger_clan_id' => $clanA->id,
            'opponent_clan_id' => $clanB->id,
            'created_by' => $a->id,
            'status' => 'accepted',
        ]);

        return [$war, $a, $b];
    }

    public function test_leader_sees_accepted_wars_of_his_clan(): void
    {
        [$war] = $this->acceptedWar();

        $this->server()->getJson('/api/minecraft/clan-wars?leader=LeaderB')
            ->assertOk()
            ->assertJsonPath('wars.0.id', $war->id)
            ->assertJsonPath('wars.0.my_side', 'opponent')
            ->assertJsonPath('wars.0.challenger.leader', 'LeaderA');
    }

    public function test_plain_member_sees_nothing(): void
    {
        [$war, $a] = $this->acceptedWar();
        $member = $this->user(['minecraft_username' => 'Rank']);
        $this->member($war->challenger, $member);

        $this->server()->getJson('/api/minecraft/clan-wars?leader=Rank')
            ->assertOk()
            ->assertJsonCount(0, 'wars');
    }

    public function test_result_completes_war_and_counts_wins(): void
    {
        [$war] = $this->acceptedWar();

        $this->server()->postJson("/api/minecraft/clan-wars/{$war->id}/result", [
            'challenger_score' => 1,
            'opponent_score' => 3,
            'mode' => 'duels',
            'games' => [['name' => 'Boxing', 'winner' => 'opponent'], ['name' => 'Classic', 'winner' => 'challenger']],
        ])->assertOk()->assertJsonPath('war.status', 'completed');

        $war->refresh();
        $this->assertSame($war->opponent_clan_id, $war->winner_clan_id);
        $this->assertStringContainsString('1 : 3', $war->notes);
        $this->assertSame(1, $war->opponent->fresh()->wins);

        $this->server()->postJson("/api/minecraft/clan-wars/{$war->id}/result", [
            'challenger_score' => 1,
            'opponent_score' => 3,
        ])->assertStatus(422);
    }
}
