<?php

namespace Tests\Feature;

use App\Models\ClanApplication;
use App\Models\ClanEvent;
use App\Models\ClanForumTopic;
use App\Models\ClanMember;
use App\Models\ClanResource;
use App\Models\ClanWar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ClanFixtures;
use Tests\TestCase;

/**
 * Api\MyClanController (дашборд «мой клан») и Api\ClanRoleController
 * (смена ролей, кик, передача лидерства).
 */
class ClanRoleTest extends TestCase
{
    use ClanFixtures;
    use RefreshDatabase;

    // --------------------------------------------------------------- dashboard

    public function test_my_clan_dashboard_for_leader(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $officer = $this->user();
        $this->member($clan, $officer, 'officer');
        $member = $this->user();
        $this->member($clan, $member);

        ClanApplication::create([
            'clan_id' => $clan->id,
            'user_id' => $this->user()->id,
            'status' => 'pending',
        ]);
        ClanApplication::create([
            'clan_id' => $clan->id,
            'user_id' => $this->user()->id,
            'status' => 'declined',
        ]);

        ClanForumTopic::create([
            'clan_id' => $clan->id,
            'author_id' => $leader->id,
            'title' => 'Тема',
            'body' => 'Тело',
        ]);

        ClanEvent::create([
            'clan_id' => $clan->id,
            'author_id' => $leader->id,
            'type' => 'event',
            'title' => 'Ивент',
        ]);

        ClanResource::create([
            'clan_id' => $clan->id,
            'author_id' => $leader->id,
            'title' => 'Файл',
            'file_path' => 'clans/x/file.zip',
            'file_name' => 'file.zip',
            'file_size' => 10,
            'mime_type' => 'application/zip',
            'category' => 'other',
        ]);

        $response = $this->actingAs($leader)->getJson('/api/my-clan')->assertOk();

        $response->assertJsonPath('clan.id', $clan->id)
            ->assertJsonPath('clan.members_count', 3)
            ->assertJsonPath('my_role', 'leader')
            ->assertJsonPath('my_permissions.news', true)
            ->assertJsonPath('my_permissions.forum', true)
            ->assertJsonPath('my_permissions.applications', true)
            ->assertJsonPath('my_permissions.wars', true)
            ->assertJsonPath('my_permissions.resources', true)
            ->assertJsonPath('my_permissions.roles', true)
            ->assertJsonPath('my_permissions.kick', true)
            ->assertJsonPath('my_permissions.edit_clan', true)
            ->assertJsonPath('stats.members', 3)
            ->assertJsonPath('stats.applications', 1)
            ->assertJsonPath('stats.wars_active', 0)
            ->assertJsonPath('stats.forum_topics', 1)
            ->assertJsonPath('stats.resources', 1);
    }

    public function test_my_clan_dashboard_for_officer_and_member_permissions(): void
    {
        $clan = $this->clan($this->user());

        $officer = $this->user();
        $this->member($clan, $officer, 'officer');

        $this->actingAs($officer)->getJson('/api/my-clan')
            ->assertOk()
            ->assertJsonPath('my_role', 'officer')
            ->assertJsonPath('my_permissions.news', true)
            ->assertJsonPath('my_permissions.forum', true)
            ->assertJsonPath('my_permissions.applications', true)
            ->assertJsonPath('my_permissions.wars', true)
            ->assertJsonPath('my_permissions.resources', true)
            ->assertJsonPath('my_permissions.roles', false)
            ->assertJsonPath('my_permissions.kick', false)
            ->assertJsonPath('my_permissions.edit_clan', false);

        $member = $this->user();
        $this->member($clan, $member);

        $this->actingAs($member)->getJson('/api/my-clan')
            ->assertOk()
            ->assertJsonPath('my_role', 'member')
            ->assertJsonPath('my_permissions.news', false)
            ->assertJsonPath('my_permissions.forum', false)
            ->assertJsonPath('my_permissions.roles', false);
    }

    /**
     * Исправлено: без группировки условий AND/OR давал
     * «challenger = X OR (opponent = X AND status IN ...)», и активными
     * считались все войны, где клан был инициатором, в любом статусе.
     */
    public function test_wars_active_counter_counts_only_active_wars(): void
    {
        $leader = $this->user();
        $myClan = $this->clan($leader);

        $otherLeader = $this->user();
        $otherClan = $this->clan($otherLeader);

        // Завершённая война, где мой клан — инициатор: ошибочно считается активной
        ClanWar::create([
            'challenger_clan_id' => $myClan->id,
            'opponent_clan_id' => $otherClan->id,
            'created_by' => $leader->id,
            'status' => 'completed',
        ]);

        // Отклонённая война, где мой клан — инициатор: тоже считается
        ClanWar::create([
            'challenger_clan_id' => $myClan->id,
            'opponent_clan_id' => $otherClan->id,
            'created_by' => $leader->id,
            'status' => 'declined',
        ]);

        // Война, где мой клан — оппонент и статус не активный: не считается
        ClanWar::create([
            'challenger_clan_id' => $otherClan->id,
            'opponent_clan_id' => $myClan->id,
            'created_by' => $otherLeader->id,
            'status' => 'declined',
        ]);

        // Единственная реально активная война
        ClanWar::create([
            'challenger_clan_id' => $otherClan->id,
            'opponent_clan_id' => $myClan->id,
            'created_by' => $otherLeader->id,
            'status' => 'pending',
        ]);

        $this->actingAs($leader)->getJson('/api/my-clan')
            ->assertOk()
            ->assertJsonPath('stats.wars_active', 1);
    }

    public function test_my_clan_dashboard_is_closed_for_guests_and_users_without_clan(): void
    {
        $this->getJson('/api/my-clan')->assertUnauthorized();

        $this->actingAs($this->user())->getJson('/api/my-clan')
            ->assertForbidden()
            ->assertJsonPath('message', 'Вы не в клане.');
    }

    // ------------------------------------------------------------ roles update

    public function test_leader_can_promote_member_to_officer(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $member = $this->user();
        $this->member($clan, $member);

        $response = $this->actingAs($leader)
            ->postJson("/api/my-clan/roles/{$member->id}", [
                'role' => 'officer',
                'permissions' => ['news', 'wars'],
                'title' => 'Глава войн',
            ])
            ->assertOk();

        $response->assertJsonPath('member.role', 'officer')
            ->assertJsonPath('member.title', 'Глава войн')
            ->assertJsonPath('member.permissions', ['news', 'wars'])
            ->assertJsonPath('member.user.username', $member->username);

        $this->assertDatabaseHas('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $member->id,
            'role' => 'officer',
            'title' => 'Глава войн',
            'promoted_by' => $leader->id,
        ]);

        $fresh = ClanMember::where('clan_id', $clan->id)->where('user_id', $member->id)->first();
        $this->assertNotNull($fresh->promoted_at);
        $this->assertTrue($fresh->can('news'));
    }

    public function test_leader_can_demote_officer_and_clear_metadata(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $officer = $this->user();
        $this->member($clan, $officer, 'officer', ['permissions' => ['news'], 'title' => 'Старый титул']);

        $this->actingAs($leader)
            ->postJson("/api/my-clan/roles/{$officer->id}", ['role' => 'member'])
            ->assertOk()
            ->assertJsonPath('member.role', 'member')
            ->assertJsonPath('member.permissions', null)
            ->assertJsonPath('member.title', null);

        $this->assertDatabaseHas('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $officer->id,
            'role' => 'member',
        ]);
    }

    public function test_custom_permissions_are_visible_in_my_clan_dashboard(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $member = $this->user();
        $this->member($clan, $member);

        $this->actingAs($leader)
            ->postJson("/api/my-clan/roles/{$member->id}", [
                'role' => 'member',
                'permissions' => ['news'],
            ])
            ->assertOk();

        $this->actingAs($member)->getJson('/api/my-clan')
            ->assertOk()
            ->assertJsonPath('my_role', 'member')
            ->assertJsonPath('my_permissions.news', true)
            ->assertJsonPath('my_permissions.forum', false);
    }

    public function test_only_leader_can_change_roles(): void
    {
        $clan = $this->clan($this->user());

        $officer = $this->user();
        $this->member($clan, $officer, 'officer');
        $member = $this->user();
        $this->member($clan, $member);
        $target = $this->user();
        $this->member($clan, $target);

        $this->actingAs($officer)->postJson("/api/my-clan/roles/{$target->id}", ['role' => 'officer'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Только лидер может менять роли.');

        $this->actingAs($member)->postJson("/api/my-clan/roles/{$target->id}", ['role' => 'officer'])
            ->assertForbidden();

        $this->assertSame('member', ClanMember::where('user_id', $target->id)->first()->role);
    }

    public function test_leader_cannot_change_own_role(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $this->actingAs($leader)
            ->postJson("/api/my-clan/roles/{$leader->id}", ['role' => 'member'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Нельзя менять свою роль.');

        $this->assertSame('leader', ClanMember::where('user_id', $leader->id)->first()->role);
        $this->assertSame($leader->id, $clan->fresh()->leader_id);
    }

    public function test_role_change_returns_404_for_user_outside_clan(): void
    {
        $leader = $this->user();
        $this->clan($leader);

        $stranger = $this->user();

        $this->actingAs($leader)->postJson("/api/my-clan/roles/{$stranger->id}", ['role' => 'officer'])
            ->assertNotFound();
    }

    public function test_role_change_validates_payload(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $member = $this->user();
        $this->member($clan, $member);

        $this->actingAs($leader)->postJson("/api/my-clan/roles/{$member->id}", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $this->actingAs($leader)->postJson("/api/my-clan/roles/{$member->id}", ['role' => 'leader'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $this->actingAs($leader)->postJson("/api/my-clan/roles/{$member->id}", [
            'role' => 'officer',
            'permissions' => ['unknown'],
        ])->assertStatus(422)->assertJsonValidationErrors('permissions.0');

        $this->actingAs($leader)->postJson("/api/my-clan/roles/{$member->id}", [
            'role' => 'officer',
            'title' => str_repeat('t', 33),
        ])->assertStatus(422)->assertJsonValidationErrors('title');

        $this->assertSame('member', ClanMember::where('user_id', $member->id)->first()->role);
    }

    // -------------------------------------------------------------- roles kick

    public function test_leader_can_kick_member_via_my_clan(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $member = $this->user();
        $this->member($clan, $member);
        $member->update(['clan_joined_at' => now()]);

        $this->actingAs($leader)->deleteJson("/api/my-clan/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('clan_members', ['clan_id' => $clan->id, 'user_id' => $member->id]);
        $this->assertNull($member->fresh()->clan_joined_at);
    }

    public function test_leader_cannot_be_kicked_via_my_clan(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $this->actingAs($leader)->deleteJson("/api/my-clan/members/{$leader->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Нельзя кикнуть лидера.');

        $this->assertDatabaseHas('clan_members', ['clan_id' => $clan->id, 'user_id' => $leader->id]);
    }

    public function test_officer_cannot_kick_via_my_clan(): void
    {
        $clan = $this->clan($this->user());
        $officer = $this->user();
        $this->member($clan, $officer, 'officer');
        $member = $this->user();
        $this->member($clan, $member);

        $this->actingAs($officer)->deleteJson("/api/my-clan/members/{$member->id}")->assertForbidden();

        $this->assertDatabaseHas('clan_members', ['clan_id' => $clan->id, 'user_id' => $member->id]);
    }

    /**
     * Текущее поведение: кик не проверяет, что цель действительно в клане.
     */
    public function test_kick_of_user_outside_clan_returns_ok(): void
    {
        $leader = $this->user();
        $this->clan($leader);
        $stranger = $this->user();

        $this->actingAs($leader)->deleteJson("/api/my-clan/members/{$stranger->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    // ------------------------------------------------------- transfer leadership

    public function test_leader_can_transfer_leadership(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $member = $this->user();
        $this->member($clan, $member);

        $this->actingAs($leader)->postJson("/api/my-clan/transfer/{$member->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame($member->id, $clan->fresh()->leader_id);
        $this->assertSame('leader', ClanMember::where('user_id', $member->id)->first()->role);
        $this->assertSame('officer', ClanMember::where('user_id', $leader->id)->first()->role);
    }

    public function test_cannot_transfer_leadership_to_self(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $this->actingAs($leader)->postJson("/api/my-clan/transfer/{$leader->id}")->assertStatus(422);

        $this->assertSame($leader->id, $clan->fresh()->leader_id);
        $this->assertSame('leader', ClanMember::where('user_id', $leader->id)->first()->role);
    }

    public function test_only_leader_can_transfer_leadership(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $officer = $this->user();
        $this->member($clan, $officer, 'officer');
        $target = $this->user();
        $this->member($clan, $target);

        $this->actingAs($officer)->postJson("/api/my-clan/transfer/{$target->id}")->assertForbidden();

        $this->assertSame($leader->id, $clan->fresh()->leader_id);
        $this->assertSame('member', ClanMember::where('user_id', $target->id)->first()->role);
    }

    public function test_transfer_returns_404_for_user_outside_clan(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $stranger = $this->user();

        $this->actingAs($leader)->postJson("/api/my-clan/transfer/{$stranger->id}")->assertNotFound();

        $this->assertSame($leader->id, $clan->fresh()->leader_id);
    }

    // ------------------------------------------------------------ guest access

    public function test_guest_cannot_access_my_clan_routes(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $member = $this->user();
        $this->member($clan, $member);

        $this->getJson('/api/my-clan')->assertUnauthorized();
        $this->postJson("/api/my-clan/roles/{$member->id}", ['role' => 'officer'])->assertUnauthorized();
        $this->deleteJson("/api/my-clan/members/{$member->id}")->assertUnauthorized();
        $this->postJson("/api/my-clan/transfer/{$member->id}")->assertUnauthorized();
    }
}
