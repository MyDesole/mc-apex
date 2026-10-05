<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\ClanApplication;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Clan\Services\ClanService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\ClanFixtures;
use Tests\TestCase;

/**
 * Игрок не может состоять в двух кланах.
 */
class ClanSingleMembershipTest extends TestCase
{
    use ClanFixtures;
    use RefreshDatabase;

    private function service(): ClanService
    {
        return app(ClanService::class);
    }

    private function openClan(User $leader): object
    {
        return $this->clan($leader, ['is_open' => true, 'max_members' => 30]);
    }

    public function test_accepting_in_one_clan_declines_other_pending_applications(): void
    {
        $leaderA = $this->user();
        $leaderB = $this->user();
        $clanA = $this->openClan($leaderA);
        $clanB = $this->openClan($leaderB);

        $applicant = $this->user();

        $appA = $this->service()->apply($clanA, $applicant, null);
        $appB = $this->service()->apply($clanB, $applicant, null);

        Sanctum::actingAs($leaderA);

        $this->postJson("/api/clans/{$clanA->id}/applications/{$appA->id}/accept")
            ->assertOk();

        $this->assertSame('accepted', $appA->fresh()->status);
        $this->assertSame('declined', $appB->fresh()->status);
        $this->assertSame(1, ClanMember::where('user_id', $applicant->id)->count());
    }

    public function test_second_clan_cannot_accept_already_member(): void
    {
        $leaderA = $this->user();
        $leaderB = $this->user();
        $clanA = $this->openClan($leaderA);
        $clanB = $this->openClan($leaderB);

        $applicant = $this->user();

        $appA = $this->service()->apply($clanA, $applicant, null);
        $appB = $this->service()->apply($clanB, $applicant, null);

        Sanctum::actingAs($leaderA);

        $this->postJson("/api/clans/{$clanA->id}/applications/{$appA->id}/accept")
            ->assertOk();

        Sanctum::actingAs($leaderB);

        $this->postJson("/api/clans/{$clanB->id}/applications/{$appB->id}/accept")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Игрок уже состоит в другом клане. Заявка отклонена.');

        $this->assertSame(1, ClanMember::where('user_id', $applicant->id)->count());
        $this->assertSame('declined', $appB->fresh()->status);
    }

    public function test_applicant_cannot_apply_while_already_in_clan(): void
    {
        $leader = $this->user();
        $clan = $this->openClan($leader);
        $member = $this->user();

        $this->member($clan, $member);

        Sanctum::actingAs($member);

        $this->postJson("/api/clans/{$clan->id}/apply", ['message' => null])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Вы уже в клане.');
    }
}
