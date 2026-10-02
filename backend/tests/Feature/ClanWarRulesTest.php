<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Clan\Models\ClanWar;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Права и правила клановых войн.
 *
 * До рефакторинга: decline и leave не проверяли права вообще, complete
 * можно было вызвать повторно, а ничья отдавала победу оппоненту.
 */
class ClanWarRulesTest extends TestCase
{
    use RefreshDatabase;

    private function clan(User $leader, string $tag): Clan
    {
        $clan = Clan::create([
            'name' => "Clan {$tag}",
            'tag' => $tag,
            'leader_id' => $leader->id,
        ]);

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $leader->id,
            'role' => 'leader',
        ]);

        return $clan;
    }

    private function member(Clan $clan, string $role = 'member'): User
    {
        $user = User::factory()->create();

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $user;
    }

    /**
     * @return array{0: Clan, 1: Clan, 2: ClanWar, 3: User, 4: User}
     */
    private function war(string $status = 'pending'): array
    {
        $challengerLeader = User::factory()->create();
        $opponentLeader = User::factory()->create();

        $challenger = $this->clan($challengerLeader, 'CH1');
        $opponent = $this->clan($opponentLeader, 'OP1');

        $war = ClanWar::create([
            'challenger_clan_id' => $challenger->id,
            'opponent_clan_id' => $opponent->id,
            'created_by' => $challengerLeader->id,
            'status' => $status,
        ]);

        return [$challenger, $opponent, $war, $challengerLeader, $opponentLeader];
    }

    /* --------------------------- Отклонение --------------------------- */

    public function test_plain_member_cannot_decline_war(): void
    {
        [, $opponent, $war] = $this->war();

        $plain = $this->member($opponent);

        $this->actingAs($plain)
            ->postJson("/api/wars/{$war->id}/decline")
            ->assertForbidden();

        $this->assertSame('pending', $war->fresh()->status);
    }

    public function test_opponent_leader_can_decline_war(): void
    {
        [, , $war, , $opponentLeader] = $this->war();

        $this->actingAs($opponentLeader)
            ->postJson("/api/wars/{$war->id}/decline")
            ->assertOk();

        $this->assertSame('declined', $war->fresh()->status);
    }

    /* --------------------------- Завершение --------------------------- */

    public function test_plain_member_cannot_report_war_result(): void
    {
        [$challenger, , $war] = $this->war('accepted');

        $plain = $this->member($challenger);

        $this->actingAs($plain)
            ->postJson("/api/wars/{$war->id}/complete", [
                'challenger_score' => 5,
                'opponent_score' => 1,
            ])
            ->assertForbidden();

        $this->assertSame('accepted', $war->fresh()->status);
    }

    public function test_completed_war_cannot_be_reported_again(): void
    {
        [$challenger, $opponent, $war, $challengerLeader] = $this->war('accepted');

        $this->actingAs($challengerLeader)
            ->postJson("/api/wars/{$war->id}/complete", [
                'challenger_score' => 5,
                'opponent_score' => 1,
            ])
            ->assertOk();

        $this->assertSame(1, $challenger->fresh()->wins);

        // Повторный отчёт не должен начислять победу ещё раз
        $this->actingAs($challengerLeader)
            ->postJson("/api/wars/{$war->id}/complete", [
                'challenger_score' => 5,
                'opponent_score' => 1,
            ])
            ->assertStatus(422);

        $this->assertSame(1, $challenger->fresh()->wins);
        $this->assertSame(1, $opponent->fresh()->losses);
    }

    public function test_draw_does_not_award_a_win(): void
    {
        [$challenger, $opponent, $war, $challengerLeader] = $this->war('accepted');

        $this->actingAs($challengerLeader)
            ->postJson("/api/wars/{$war->id}/complete", [
                'challenger_score' => 3,
                'opponent_score' => 3,
            ])
            ->assertOk();

        $fresh = $war->fresh();

        $this->assertSame('draw', $fresh->outcome);
        $this->assertNull($fresh->winner_clan_id);

        // Ничья не даёт побед никому
        $this->assertSame(0, $challenger->fresh()->wins);
        $this->assertSame(0, $opponent->fresh()->wins);
    }

    public function test_winning_clan_gets_win_and_loser_gets_loss(): void
    {
        [$challenger, $opponent, $war, $challengerLeader] = $this->war('accepted');

        $this->actingAs($challengerLeader)
            ->postJson("/api/wars/{$war->id}/complete", [
                'challenger_score' => 7,
                'opponent_score' => 2,
            ])
            ->assertOk();

        $this->assertSame($challenger->id, $war->fresh()->winner_clan_id);
        $this->assertSame(1, $challenger->fresh()->wins);
        $this->assertSame(1, $opponent->fresh()->losses);
    }

    /* --------------------------- Выход из войны --------------------------- */

    public function test_stranger_cannot_leave_war(): void
    {
        [, , $war] = $this->war('accepted');

        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->postJson("/api/wars/{$war->id}/leave")
            ->assertForbidden();
    }
}
