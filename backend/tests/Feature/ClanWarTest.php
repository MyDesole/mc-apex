<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\ClanWar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ClanFixtures;
use Tests\TestCase;

/**
 * ClanWarController: вызов на войну, принятие/отклонение,
 * отчёт о результате, участие бойцов и просмотр войны.
 */
class ClanWarTest extends TestCase
{
    use ClanFixtures;
    use RefreshDatabase;

    private function war(Clan $challenger, Clan $opponent, User $author, string $status = 'pending', array $attributes = []): ClanWar
    {
        return ClanWar::create(array_merge([
            'challenger_clan_id' => $challenger->id,
            'opponent_clan_id' => $opponent->id,
            'created_by' => $author->id,
            'status' => $status,
        ], $attributes));
    }

    // ------------------------------------------------------------------ store

    public function test_leader_can_challenge_another_clan(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);

        $theirLeader = $this->user();
        $theirClan = $this->clan($theirLeader);

        $response = $this->actingAs($myLeader)->postJson("/api/clans/{$theirClan->id}/wars", [
            'scheduled_at' => now()->addDay()->toDateTimeString(),
            'notes' => 'Бой 5х5',
        ])->assertCreated();

        $response->assertJsonPath('war.status', 'pending')
            ->assertJsonPath('war.challenger_clan_id', $myClan->id)
            ->assertJsonPath('war.opponent_clan_id', $theirClan->id)
            ->assertJsonPath('war.created_by', $myLeader->id)
            ->assertJsonPath('war.notes', 'Бой 5х5');

        // challenger/opponent подгружены
        $this->assertSame($myClan->name, $response->json('war.challenger.name'));
        $this->assertSame($theirClan->name, $response->json('war.opponent.name'));

        $this->assertDatabaseHas('clan_wars', [
            'challenger_clan_id' => $myClan->id,
            'opponent_clan_id' => $theirClan->id,
            'created_by' => $myLeader->id,
            'status' => 'pending',
        ]);
    }

    public function test_officer_can_challenge_another_clan(): void
    {
        $myClan = $this->clan($this->user());
        $officer = $this->user();
        $this->member($myClan, $officer, 'officer');

        $theirClan = $this->clan($this->user());

        $this->actingAs($officer)->postJson("/api/clans/{$theirClan->id}/wars")
            ->assertCreated()
            ->assertJsonPath('war.challenger_clan_id', $myClan->id);

        $this->assertDatabaseCount('clan_wars', 1);
    }

    public function test_plain_member_cannot_challenge_another_clan(): void
    {
        $myClan = $this->clan($this->user());
        $member = $this->user();
        $this->member($myClan, $member);

        $theirClan = $this->clan($this->user());

        $this->actingAs($member)->postJson("/api/clans/{$theirClan->id}/wars")
            ->assertForbidden()
            ->assertJsonPath('message', 'Нет прав.');

        $this->assertDatabaseCount('clan_wars', 0);
    }

    public function test_user_without_clan_cannot_challenge(): void
    {
        $loner = $this->user();
        $theirClan = $this->clan($this->user());

        $this->actingAs($loner)->postJson("/api/clans/{$theirClan->id}/wars")
            ->assertForbidden()
            ->assertJsonPath('message', 'Вы не в клане.');

        $this->assertDatabaseCount('clan_wars', 0);
    }

    public function test_clan_cannot_challenge_itself(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/wars")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Нельзя вызвать свой клан.');

        $this->assertDatabaseCount('clan_wars', 0);
    }

    public function test_challenge_validates_scheduled_at_and_notes(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());

        $this->actingAs($myLeader)->postJson("/api/clans/{$theirClan->id}/wars", [
            'scheduled_at' => now()->subDay()->toDateTimeString(),
        ])->assertStatus(422)->assertJsonValidationErrors('scheduled_at');

        $this->actingAs($myLeader)->postJson("/api/clans/{$theirClan->id}/wars", [
            'notes' => str_repeat('n', 1001),
        ])->assertStatus(422)->assertJsonValidationErrors('notes');

        $this->assertDatabaseCount('clan_wars', 0);
        $this->assertSame($myClan->id, $myClan->fresh()->id);
    }

    public function test_challenge_unknown_clan_returns_404(): void
    {
        $leader = $this->user();
        $this->clan($leader);

        $this->actingAs($leader)->postJson('/api/clans/999999/wars')->assertNotFound();
    }

    public function test_my_clan_alias_route_also_creates_challenge(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());

        $this->actingAs($myLeader)->postJson("/api/my-clan/clans/{$theirClan->id}/wars")
            ->assertCreated()
            ->assertJsonPath('war.challenger_clan_id', $myClan->id);
    }

    public function test_guest_cannot_challenge(): void
    {
        $theirClan = $this->clan($this->user());

        $this->postJson("/api/clans/{$theirClan->id}/wars")->assertUnauthorized();
        $this->postJson("/api/my-clan/clans/{$theirClan->id}/wars")->assertUnauthorized();
    }

    // ----------------------------------------------------------------- accept

    public function test_opponent_leader_can_accept_war(): void
    {
        $myClan = $this->clan($this->user());
        $myLeader = User::find($myClan->leader_id);

        $theirLeader = $this->user();
        $theirClan = $this->clan($theirLeader);

        $war = $this->war($myClan, $theirClan, $myLeader);

        $this->actingAs($theirLeader)->postJson("/api/wars/{$war->id}/accept")
            ->assertOk()
            ->assertJsonPath('war.status', 'accepted');

        $this->assertSame('accepted', $war->fresh()->status);
    }

    public function test_opponent_officer_can_accept_war(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);

        $theirClan = $this->clan($this->user());
        $officer = $this->user();
        $this->member($theirClan, $officer, 'officer');

        $war = $this->war($myClan, $theirClan, $myLeader);

        $this->actingAs($officer)->postJson("/api/wars/{$war->id}/accept")->assertOk();
    }

    public function test_challenger_cannot_accept_own_challenge(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());

        $war = $this->war($myClan, $theirClan, $myLeader);

        $this->actingAs($myLeader)->postJson("/api/wars/{$war->id}/accept")->assertForbidden();

        $this->assertSame('pending', $war->fresh()->status);
    }

    public function test_plain_opponent_member_cannot_accept_war(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());

        $member = $this->user();
        $this->member($theirClan, $member);

        $war = $this->war($myClan, $theirClan, $myLeader);

        $this->actingAs($member)->postJson("/api/wars/{$war->id}/accept")->assertForbidden();

        $this->assertSame('pending', $war->fresh()->status);
    }

    public function test_cannot_accept_war_twice(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);

        $theirLeader = $this->user();
        $theirClan = $this->clan($theirLeader);

        $war = $this->war($myClan, $theirClan, $myLeader);

        $this->actingAs($theirLeader)->postJson("/api/wars/{$war->id}/accept")->assertOk();
        $this->actingAs($theirLeader)->postJson("/api/wars/{$war->id}/accept")->assertStatus(422);
    }

    public function test_user_without_clan_cannot_accept_war(): void
    {
        $myClan = $this->clan($this->user());
        $theirClan = $this->clan($this->user());
        $war = $this->war($myClan, $theirClan, User::find($myClan->leader_id));

        $this->actingAs($this->user())->postJson("/api/wars/{$war->id}/accept")->assertForbidden();

        $this->assertSame('pending', $war->fresh()->status);
    }

    public function test_accept_unknown_war_returns_404(): void
    {
        $leader = $this->user();

        $this->actingAs($leader)->postJson('/api/wars/999999/accept')->assertNotFound();
    }

    // ---------------------------------------------------------------- decline

    public function test_opponent_leader_can_decline_war(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);

        $theirLeader = $this->user();
        $theirClan = $this->clan($theirLeader);

        $war = $this->war($myClan, $theirClan, $myLeader);

        $this->actingAs($theirLeader)->postJson("/api/wars/{$war->id}/decline")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('declined', $war->fresh()->status);
    }

    /**
     * Текущее поведение: в decline() нет проверки прав canManage(),
     * поэтому войну может отклонить любой участник клана-оппонента.
     */
    /**
     * Исправлено: decline() не проверял права, и любой участник
     * клана-оппонента мог отклонить войну за руководство.
     */
    public function test_plain_opponent_member_cannot_decline_war(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());

        $member = $this->user();
        $this->member($theirClan, $member);

        $war = $this->war($myClan, $theirClan, $myLeader);

        $this->actingAs($member)->postJson("/api/wars/{$war->id}/decline")->assertForbidden();

        $this->assertSame('pending', $war->fresh()->status);
    }

    public function test_challenger_cannot_decline_own_challenge(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());

        $war = $this->war($myClan, $theirClan, $myLeader);

        $this->actingAs($myLeader)->postJson("/api/wars/{$war->id}/decline")->assertForbidden();

        $this->assertSame('pending', $war->fresh()->status);
    }

    public function test_cannot_decline_finished_war(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);

        $theirLeader = $this->user();
        $theirClan = $this->clan($theirLeader);

        $war = $this->war($myClan, $theirClan, $myLeader, 'completed');

        $this->actingAs($theirLeader)->postJson("/api/wars/{$war->id}/decline")->assertStatus(422);

        $this->assertSame('completed', $war->fresh()->status);
    }

    // --------------------------------------------------------------- complete

    public function test_challenger_reports_result_and_winner_is_counted(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);

        $theirLeader = $this->user();
        $theirClan = $this->clan($theirLeader);

        $war = $this->war($myClan, $theirClan, $myLeader);

        $this->actingAs($myLeader)->postJson("/api/wars/{$war->id}/complete", [
            'challenger_score' => 5,
            'opponent_score' => 3,
            'notes' => 'Чистая победа',
        ])->assertOk()->assertJsonPath('war.status', 'completed');

        $fresh = $war->fresh();

        $this->assertSame('completed', $fresh->status);
        $this->assertSame(5, $fresh->challenger_score);
        $this->assertSame(3, $fresh->opponent_score);
        $this->assertSame($myClan->id, $fresh->winner_clan_id);

        $this->assertSame(1, $myClan->fresh()->wins);
        $this->assertSame(0, $myClan->fresh()->losses);
        $this->assertSame(0, $theirClan->fresh()->wins);
        $this->assertSame(1, $theirClan->fresh()->losses);

        // power = contribution + wins*100 - losses*50
        $this->assertSame(100, $myClan->fresh()->power);
        $this->assertSame(-50, $theirClan->fresh()->power);
    }

    public function test_opponent_reports_result_and_opponent_wins(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);

        $theirLeader = $this->user();
        $theirClan = $this->clan($theirLeader);

        $war = $this->war($myClan, $theirClan, $myLeader);

        $this->actingAs($theirLeader)->postJson("/api/wars/{$war->id}/complete", [
            'challenger_score' => 1,
            'opponent_score' => 7,
        ])->assertOk();

        $this->assertSame($theirClan->id, $war->fresh()->winner_clan_id);
        $this->assertSame(1, $theirClan->fresh()->wins);
        $this->assertSame(1, $myClan->fresh()->losses);
        $this->assertSame(-50, $myClan->fresh()->power);
        $this->assertSame(100, $theirClan->fresh()->power);
    }

    /**
     * Текущее поведение: при равном счёте победителем объявляется клан-оппонент
     * (условие строго «>», ничьей в правилах нет).
     */
    /**
     * Исправлено: сравнение было строго «>», поэтому при равном счёте
     * победа уходила оппоненту. Теперь ничья помечается явно.
     */
    public function test_draw_records_draw_without_awarding_win(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());
        $war = $this->war($myClan, $theirClan, $myLeader);

        $this->actingAs($myLeader)->postJson("/api/wars/{$war->id}/complete", [
            'challenger_score' => 4,
            'opponent_score' => 4,
        ])->assertOk();

        $fresh = $war->fresh();

        $this->assertSame('draw', $fresh->outcome);
        $this->assertNull($fresh->winner_clan_id);
        $this->assertSame(0, $theirClan->fresh()->wins);
        $this->assertSame(0, $myClan->fresh()->wins);
        $this->assertSame(0, $myClan->fresh()->losses);
    }

    /**
     * Исправлено: complete() не проверял статус, поэтому повторный отчёт
     * удваивал победы и поражения.
     */
    public function test_completed_war_cannot_be_reported_again(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());
        $war = $this->war($myClan, $theirClan, $myLeader);

        $payload = ['challenger_score' => 5, 'opponent_score' => 3];

        $this->actingAs($myLeader)->postJson("/api/wars/{$war->id}/complete", $payload)->assertOk();

        $this->actingAs($myLeader)
            ->postJson("/api/wars/{$war->id}/complete", $payload)
            ->assertStatus(422);

        $this->assertSame(1, $myClan->fresh()->wins);
        $this->assertSame(1, $theirClan->fresh()->losses);
    }

    public function test_complete_validates_scores(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());
        $war = $this->war($myClan, $theirClan, $myLeader);

        $this->actingAs($myLeader)->postJson("/api/wars/{$war->id}/complete", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['challenger_score', 'opponent_score']);

        $this->actingAs($myLeader)->postJson("/api/wars/{$war->id}/complete", [
            'challenger_score' => 101,
            'opponent_score' => -1,
        ])->assertStatus(422)->assertJsonValidationErrors(['challenger_score', 'opponent_score']);

        $this->assertSame('pending', $war->fresh()->status);
    }

    public function test_outsider_clan_cannot_report_result(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());
        $war = $this->war($myClan, $theirClan, $myLeader);

        $outsiderClan = $this->clan($this->user());
        $outsider = User::find($outsiderClan->leader_id);

        $this->actingAs($outsider)->postJson("/api/wars/{$war->id}/complete", [
            'challenger_score' => 5,
            'opponent_score' => 0,
        ])->assertForbidden();

        $this->assertSame('pending', $war->fresh()->status);
    }

    /**
     * Текущее поведение: отчёт о результате доступен любому участнику клана,
     * без проверки роли leader/officer.
     */
    /**
     * Исправлено: complete() не проверял права — любой участник клана
     * мог зафиксировать результат войны.
     */
    public function test_plain_member_cannot_report_result(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());
        $war = $this->war($myClan, $theirClan, $myLeader);

        $member = $this->user();
        $this->member($myClan, $member);

        $this->actingAs($member)->postJson("/api/wars/{$war->id}/complete", [
            'challenger_score' => 2,
            'opponent_score' => 1,
        ])->assertForbidden();

        $this->assertSame('pending', $war->fresh()->status);
    }

    // ------------------------------------------------------------------- join

    public function test_member_of_participating_clan_can_join_war(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());
        $war = $this->war($myClan, $theirClan, $myLeader);

        $member = $this->user();
        $this->member($myClan, $member);

        $this->actingAs($member)->postJson("/api/wars/{$war->id}/join")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('clan_war_participants', [
            'clan_war_id' => $war->id,
            'user_id' => $member->id,
            'clan_id' => $myClan->id,
        ]);
    }

    public function test_cannot_join_war_twice(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());
        $war = $this->war($myClan, $theirClan, $myLeader);

        $member = $this->user();
        $this->member($myClan, $member);

        $this->actingAs($member)->postJson("/api/wars/{$war->id}/join")->assertOk();
        $this->actingAs($member)->postJson("/api/wars/{$war->id}/join")->assertStatus(422);

        $this->assertDatabaseCount('clan_war_participants', 1);
    }

    public function test_outsider_cannot_join_war(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());
        $war = $this->war($myClan, $theirClan, $myLeader);

        $outsiderClan = $this->clan($this->user());
        $outsider = User::find($outsiderClan->leader_id);

        $this->actingAs($outsider)->postJson("/api/wars/{$war->id}/join")
            ->assertForbidden()
            ->assertJsonPath('message', 'Вы не участвуете в этой войне.');

        $this->assertDatabaseCount('clan_war_participants', 0);
    }

    public function test_user_without_clan_cannot_join_war(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());
        $war = $this->war($myClan, $theirClan, $myLeader);

        $this->actingAs($this->user())->postJson("/api/wars/{$war->id}/join")
            ->assertForbidden()
            ->assertJsonPath('message', 'Вы не в клане.');
    }

    public function test_cannot_join_finished_war(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());
        $war = $this->war($myClan, $theirClan, $myLeader, 'completed');

        $member = $this->user();
        $this->member($myClan, $member);

        $this->actingAs($member)->postJson("/api/wars/{$war->id}/join")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Война уже завершена.');
    }

    // ------------------------------------------------------------------ leave

    public function test_participant_can_leave_war(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());
        $war = $this->war($myClan, $theirClan, $myLeader);

        $member = $this->user();
        $this->member($myClan, $member);

        $this->actingAs($member)->postJson("/api/wars/{$war->id}/join")->assertOk();
        $this->assertDatabaseCount('clan_war_participants', 1);

        $this->actingAs($member)->postJson("/api/wars/{$war->id}/leave")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseCount('clan_war_participants', 0);
    }

    /**
     * Текущее поведение: leave() не проверяет ни клан, ни участие,
     * поэтому посторонний получает 200.
     */
    /**
     * Исправлено: leave() не проверял права и отвечал «ок» вообще всем.
     */
    public function test_stranger_cannot_leave_war(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());
        $war = $this->war($myClan, $theirClan, $myLeader);

        // Посторонний без клана
        $this->actingAs($this->user())
            ->postJson("/api/wars/{$war->id}/leave")
            ->assertForbidden();
    }

    // ------------------------------------------------------------------- show

    public function test_participant_clan_can_view_war(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());
        $war = $this->war($myClan, $theirClan, $myLeader);

        $member = $this->user();
        $this->member($myClan, $member);

        $response = $this->actingAs($member)->getJson("/api/wars/{$war->id}")->assertOk();

        $response->assertJsonPath('war.id', $war->id)
            ->assertJsonPath('my_clan_id', $myClan->id)
            ->assertJsonPath('is_participant', false);

        $this->assertArrayHasKey('participants', $response->json('war'));
        $this->assertSame($myClan->name, $response->json('war.challenger.name'));
        $this->assertSame($theirClan->name, $response->json('war.opponent.name'));

        $this->actingAs($member)->postJson("/api/wars/{$war->id}/join")->assertOk();

        $this->actingAs($member)->getJson("/api/wars/{$war->id}")
            ->assertOk()
            ->assertJsonPath('is_participant', true);
    }

    public function test_outsider_cannot_view_war(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());
        $war = $this->war($myClan, $theirClan, $myLeader);

        $outsiderClan = $this->clan($this->user());
        $outsider = User::find($outsiderClan->leader_id);

        $this->actingAs($outsider)->getJson("/api/wars/{$war->id}")->assertForbidden();
    }

    public function test_user_without_clan_cannot_view_war(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());
        $war = $this->war($myClan, $theirClan, $myLeader);

        $this->actingAs($this->user())->getJson("/api/wars/{$war->id}")->assertForbidden();
    }

    public function test_show_unknown_war_returns_404(): void
    {
        $leader = $this->user();
        $this->clan($leader);

        $this->actingAs($leader)->getJson('/api/wars/999999')->assertNotFound();
    }

    public function test_guest_cannot_access_war_routes(): void
    {
        $myLeader = $this->user();
        $myClan = $this->clan($myLeader);
        $theirClan = $this->clan($this->user());
        $war = $this->war($myClan, $theirClan, $myLeader);

        $this->getJson("/api/wars/{$war->id}")->assertUnauthorized();
        $this->postJson("/api/wars/{$war->id}/join")->assertUnauthorized();
        $this->postJson("/api/wars/{$war->id}/leave")->assertUnauthorized();
        $this->postJson("/api/wars/{$war->id}/accept")->assertUnauthorized();
        $this->postJson("/api/wars/{$war->id}/decline")->assertUnauthorized();
        $this->postJson("/api/wars/{$war->id}/complete")->assertUnauthorized();
    }
}
