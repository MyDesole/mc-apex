<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Tournaments\Models\Tournament;
use App\Domains\Tournaments\Models\TournamentParticipant;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Клановые турниры: в заявке обязан быть клан.
 *
 * До исправления игрок без клана мог зарегистрироваться в клановом турнире,
 * создавая участника с user_id = null и clan_id = null, причём повторно —
 * NULL не конфликтует в уникальном индексе, поэтому появлялись фантомные
 * слоты в сетке.
 */
class ClanTournamentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function tournament(array $attributes = []): Tournament
    {
        return Tournament::create(array_merge([
            'name' => 'Клановый турнир',
            'slug' => 'clan-tournament-' . uniqid(),
            'type' => 'clan',
            'format' => 'single_elim',
            'status' => 'registration',
            'max_participants' => 8,
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
        ], $attributes));
    }

    private function clanMember(): array
    {
        $leader = User::factory()->create();

        $clan = Clan::create([
            'name' => 'Клан для турнира',
            'tag' => 'TRN',
            'leader_id' => $leader->id,
        ]);

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $leader->id,
            'role' => 'leader',
        ]);

        return [$leader, $clan];
    }

    public function test_player_without_clan_cannot_register_for_clan_tournament(): void
    {
        $tournament = $this->tournament();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/tournaments/{$tournament->id}/register")
            ->assertStatus(422);

        $this->assertSame(0, TournamentParticipant::where('tournament_id', $tournament->id)->count());
    }

    public function test_clan_member_registers_with_his_clan(): void
    {
        $tournament = $this->tournament();
        [$leader, $clan] = $this->clanMember();

        $this->actingAs($leader)
            ->postJson("/api/tournaments/{$tournament->id}/register")
            ->assertCreated();

        $participant = TournamentParticipant::where('tournament_id', $tournament->id)->firstOrFail();

        $this->assertSame($clan->id, $participant->clan_id);
        $this->assertNull($participant->user_id);
    }

    public function test_same_clan_cannot_register_twice(): void
    {
        $tournament = $this->tournament();
        [$leader, $clan] = $this->clanMember();

        $member = User::factory()->create();

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $this->actingAs($leader)
            ->postJson("/api/tournaments/{$tournament->id}/register")
            ->assertCreated();

        // Второй участник того же клана не должен создавать новый слот
        $this->actingAs($member)
            ->postJson("/api/tournaments/{$tournament->id}/register")
            ->assertStatus(422);

        $this->assertSame(1, TournamentParticipant::where('tournament_id', $tournament->id)->count());
    }
}
