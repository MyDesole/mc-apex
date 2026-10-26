<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Clan\Models\ClanWar;
use App\Domains\Clan\Models\ClanWarParticipant;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Вызов на КВ из «Моего клана» и призыв участников руководством.
 */
class ClanWarCallTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Clan, 1: User} */
    private function clan(string $tag): array
    {
        $leader = User::factory()->create();
        $clan = Clan::create(['name' => "Клан {$tag}", 'tag' => $tag, 'leader_id' => $leader->id]);
        ClanMember::create(['clan_id' => $clan->id, 'user_id' => $leader->id, 'role' => 'leader']);

        return [$clan, $leader];
    }

    private function member(Clan $clan, string $role = 'member'): User
    {
        $user = User::factory()->create();
        ClanMember::create(['clan_id' => $clan->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function warNotes(User $user, string $type)
    {
        return $user->fresh()->notifications->filter(fn ($n) => ($n->data['type'] ?? null) === $type);
    }

    public function test_challenge_notifies_opponent_leader(): void
    {
        [, $leader] = $this->clan('AAA');
        [$enemy, $enemyLeader] = $this->clan('BBB');

        $this->actingAs($leader)
            ->postJson("/api/clans/{$enemy->id}/wars", ['notes' => 'BO3'])
            ->assertCreated();

        $note = $this->warNotes($enemyLeader, 'clan_war_challenge')->first();
        $this->assertNotNull($note);
        $this->assertSame('BO3', $note->data['note']);
    }

    public function test_officer_calls_selected_members_except_joined_and_strangers(): void
    {
        [$clan] = $this->clan('AAA');
        [$enemy] = $this->clan('BBB');
        $officer = $this->member($clan, 'officer');
        $called = $this->member($clan);
        $joined = $this->member($clan);
        $stranger = $this->member($enemy);

        $war = ClanWar::create([
            'challenger_clan_id' => $clan->id, 'opponent_clan_id' => $enemy->id,
            'created_by' => $officer->id, 'status' => 'accepted',
        ]);
        ClanWarParticipant::create(['clan_war_id' => $war->id, 'user_id' => $joined->id, 'clan_id' => $clan->id]);

        $this->actingAs($officer)
            ->postJson("/api/wars/{$war->id}/call", [
                'user_ids' => [$called->id, $joined->id, $stranger->id],
                'message' => 'Сбор в 20:00',
            ])
            ->assertOk()
            ->assertJsonPath('sent', 1);

        $note = $this->warNotes($called, 'clan_war_call')->first();
        $this->assertNotNull($note);
        $this->assertSame($war->id, $note->data['war_id']);
        $this->assertSame('Сбор в 20:00', $note->data['note']);
        $this->assertCount(0, $this->warNotes($joined, 'clan_war_call'));
        $this->assertCount(0, $this->warNotes($stranger, 'clan_war_call'));
    }

    public function test_creator_joins_own_war_before_it_is_accepted(): void
    {
        [$clan, $leader] = $this->clan('AAA');
        [$enemy] = $this->clan('BBB');

        $warId = $this->actingAs($leader)
            ->postJson("/api/clans/{$enemy->id}/wars")
            ->assertCreated()
            ->json('war.id');

        $this->actingAs($leader)->postJson("/api/wars/{$warId}/join")->assertOk();

        $this->assertDatabaseHas('clan_war_participants', [
            'clan_war_id' => $warId, 'user_id' => $leader->id, 'clan_id' => $clan->id,
        ]);

        // Состав виден на странице клана, иначе кнопка «Покинуть» не появится
        $wars = $this->actingAs($leader)->getJson("/api/clans/{$clan->id}")->assertOk()->json('outgoing_wars');
        $this->assertSame($leader->id, collect($wars)->firstWhere('id', $warId)['participants'][0]['user_id'] ?? null);
    }

    public function test_regular_member_cannot_call(): void
    {
        [$clan] = $this->clan('AAA');
        [$enemy] = $this->clan('BBB');
        $member = $this->member($clan);
        $other = $this->member($clan);

        $war = ClanWar::create([
            'challenger_clan_id' => $clan->id, 'opponent_clan_id' => $enemy->id,
            'created_by' => $member->id, 'status' => 'pending',
        ]);

        $this->actingAs($member)
            ->postJson("/api/wars/{$war->id}/call", ['user_ids' => [$other->id]])
            ->assertForbidden();
    }
}
