<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanEvent;
use App\Domains\Clan\Models\ClanEventComment;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Роспуск клана и выход лидера.
 *
 * Лидер не мог выйти из клана: сервер отвечал «передайте лидерство»,
 * а если он единственный участник — передать было некому, и клан
 * становился ловушкой. Теперь:
 *   - лидер с участниками получает понятный отказ и передаёт лидерство;
 *   - лидер-одиночка распускает клан этим же действием.
 */
class ClanDissolveTest extends TestCase
{
    use RefreshDatabase;

    private function clan(User $leader, array $attributes = []): Clan
    {
        return Clan::create(array_merge([
            'name' => 'Клан для роспуска',
            'tag' => 'DIS',
            'leader_id' => $leader->id,
        ], $attributes));
    }

    private function member(Clan $clan, User $user, string $role = 'member'): ClanMember
    {
        return ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    /* ------------------------- Лидер-одиночка ------------------------- */

    public function test_solo_leader_leaving_dissolves_clan(): void
    {
        $leader = User::factory()->create();

        $clan = $this->clan($leader);
        $this->member($clan, $leader, 'leader');

        $leader->update(['clan_joined_at' => now()]);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/leave")
            ->assertOk();

        $this->assertDatabaseMissing('clans', ['id' => $clan->id]);
        $this->assertDatabaseMissing('clan_members', ['clan_id' => $clan->id]);
        $this->assertNull($leader->fresh()->clan_joined_at);
    }

    public function test_dissolve_removes_clan_content(): void
    {
        $leader = User::factory()->create();

        $clan = $this->clan($leader);
        $this->member($clan, $leader, 'leader');

        $event = ClanEvent::create([
            'clan_id' => $clan->id,
            'author_id' => $leader->id,
            'type' => 'event',
            'title' => 'Событие',
            'body' => 'Текст',
            'starts_at' => now()->addDay(),
        ]);

        ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $leader->id,
            'body' => 'Комментарий',
        ]);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/leave")
            ->assertOk();

        $this->assertDatabaseMissing('clans', ['id' => $clan->id]);
        $this->assertDatabaseMissing('clan_events', ['id' => $event->id]);
        $this->assertSame(0, ClanEventComment::where('clan_event_id', $event->id)->count());
    }

    public function test_dissolve_clears_favorite_clan_on_profiles(): void
    {
        $leader = User::factory()->create();
        $fan = User::factory()->create();

        $clan = $this->clan($leader);
        $this->member($clan, $leader, 'leader');

        $fan->update(['favorite_clan_id' => $clan->id]);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/leave")
            ->assertOk();

        $this->assertNull($fan->fresh()->favorite_clan_id);
    }

    /* ------------------------- Лидер с участниками ------------------------- */

    public function test_leader_with_members_cannot_leave(): void
    {
        $leader = User::factory()->create();

        $clan = $this->clan($leader);
        $this->member($clan, $leader, 'leader');
        $this->member($clan, User::factory()->create(), 'officer');

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/leave")
            ->assertStatus(422);

        // Клан и состав на месте
        $this->assertDatabaseHas('clans', ['id' => $clan->id]);
        $this->assertSame(2, ClanMember::where('clan_id', $clan->id)->count());
    }

    public function test_leader_can_leave_after_transferring_leadership(): void
    {
        $leader = User::factory()->create();
        $officer = User::factory()->create();

        $clan = $this->clan($leader);
        $this->member($clan, $leader, 'leader');
        $this->member($clan, $officer, 'officer');

        // Передаём лидерство
        $this->actingAs($leader)
            ->postJson("/api/my-clan/transfer/{$officer->id}")
            ->assertOk();

        $this->assertSame($officer->id, $clan->fresh()->leader_id);

        // Теперь бывший лидер выходит как обычный участник
        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/leave")
            ->assertOk();

        $this->assertDatabaseHas('clans', ['id' => $clan->id]);
        $this->assertDatabaseMissing('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $leader->id,
        ]);

        // Новый лидер остался, клан не распущен
        $this->assertDatabaseHas('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $officer->id,
            'role' => 'leader',
        ]);
    }

    public function test_new_leader_can_then_dissolve_alone(): void
    {
        $leader = User::factory()->create();
        $officer = User::factory()->create();

        $clan = $this->clan($leader);
        $this->member($clan, $leader, 'leader');
        $this->member($clan, $officer, 'officer');

        $this->actingAs($leader)->postJson("/api/my-clan/transfer/{$officer->id}")->assertOk();
        $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/leave")->assertOk();

        // Остался один лидер — теперь он может распустить клан
        $this->actingAs($officer)
            ->postJson("/api/clans/{$clan->id}/leave")
            ->assertOk();

        $this->assertDatabaseMissing('clans', ['id' => $clan->id]);
    }

    /* ------------------------- Обычный участник ------------------------- */

    public function test_plain_member_leaving_does_not_dissolve_clan(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $clan = $this->clan($leader);
        $this->member($clan, $leader, 'leader');
        $this->member($clan, $member, 'member');

        $this->actingAs($member)
            ->postJson("/api/clans/{$clan->id}/leave")
            ->assertOk();

        $this->assertDatabaseHas('clans', ['id' => $clan->id]);
        $this->assertDatabaseMissing('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_second_member_leaving_dissolves_clan_for_last_leader(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $clan = $this->clan($leader);
        $this->member($clan, $leader, 'leader');
        $this->member($clan, $member, 'member');

        // Участник уходит — лидер остаётся в клане один
        $this->actingAs($member)->postJson("/api/clans/{$clan->id}/leave")->assertOk();

        $this->assertDatabaseHas('clans', ['id' => $clan->id]);

        // Теперь лидер может распустить клан
        $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/leave")->assertOk();

        $this->assertDatabaseMissing('clans', ['id' => $clan->id]);
    }

    public function test_stranger_cannot_leave(): void
    {
        $leader = User::factory()->create();
        $clan = $this->clan($leader);
        $this->member($clan, $leader, 'leader');

        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->postJson("/api/clans/{$clan->id}/leave")
            ->assertNotFound();

        $this->assertDatabaseHas('clans', ['id' => $clan->id]);
    }
}
