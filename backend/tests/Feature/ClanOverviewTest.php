<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanCheckin;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Clan\Services\ClanOverviewService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Вкладка «Обзор» в «Моём клане»: отметки, уровень, объявление, сводка.
 */
class ClanOverviewTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Clan, 1: User} */
    private function clan(string $tag = 'AAA'): array
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

    public function test_member_checks_in_once_per_day(): void
    {
        [$clan] = $this->clan();
        $member = $this->member($clan);

        $this->actingAs($member)->postJson('/api/my-clan/checkin')
            ->assertOk()
            ->assertJsonPath('streak', 1)
            ->assertJsonPath('level.xp', \App\Domains\Clan\Support\ClanLevel::XP_PER_CHECKIN);

        $this->actingAs($member)->postJson('/api/my-clan/checkin')->assertStatus(422);

        $this->assertSame(1, ClanCheckin::where('user_id', $member->id)->count());
    }

    public function test_streak_counts_consecutive_days(): void
    {
        [$clan] = $this->clan();
        $member = $this->member($clan);

        foreach ([3, 2, 1] as $daysAgo) {
            ClanCheckin::create(['clan_id' => $clan->id, 'user_id' => $member->id, 'day' => now()->subDays($daysAgo)->toDateString()]);
        }
        // Разрыв: позапрошлая неделя не продлевает серию
        ClanCheckin::create(['clan_id' => $clan->id, 'user_id' => $member->id, 'day' => now()->subDays(10)->toDateString()]);

        // Сегодня ещё не отмечался — серия из трёх дней не сгорела
        $this->actingAs($member)->getJson('/api/my-clan/overview')
            ->assertOk()
            ->assertJsonPath('checkin.done', false)
            ->assertJsonPath('checkin.streak', 3);

        $this->actingAs($member)->postJson('/api/my-clan/checkin')->assertJsonPath('streak', 4);
    }

    public function test_level_grows_from_checkins_and_wins(): void
    {
        [$clan] = $this->clan();
        $clan->update(['wins' => 2]);

        $level = app(ClanOverviewService::class)->level($clan->fresh());

        // 2 победы × 100 = 200 опыта — ровно второй уровень
        $this->assertSame(200, $level['xp']);
        $this->assertSame(2, $level['level']);
        $this->assertSame(600, $level['next']);
        $this->assertSame(2, $clan->fresh()->level);
        $this->assertSame(2, $level['bonuses']['slots']);
    }

    public function test_level_adds_member_slots(): void
    {
        [$clan] = $this->clan();
        $clan->forceFill(['max_members' => 1, 'level' => 2])->save();

        $applicant = User::factory()->create();
        $application = \App\Domains\Clan\Models\ClanApplication::create([
            'clan_id' => $clan->id, 'user_id' => $applicant->id, 'status' => 'pending',
        ]);

        // База — 1 место, и оно занято лидером; второй уровень даёт ещё 2
        app(\App\Domains\Clan\Services\ClanService::class)->acceptApplication($clan->fresh(), $application);

        $this->assertDatabaseHas('clan_members', ['clan_id' => $clan->id, 'user_id' => $applicant->id]);
    }

    public function test_checkin_pays_treasury_only_for_settled_members(): void
    {
        [$clan] = $this->clan();
        $clan->forceFill(['level' => 3, 'xp' => 600])->save();
        $veteran = $this->member($clan);
        ClanMember::where('user_id', $veteran->id)->update(['joined_at' => now()->subDays(3)]);
        $newbie = $this->member($clan);
        ClanMember::where('user_id', $newbie->id)->update(['joined_at' => now()]);

        $this->actingAs($veteran)->postJson('/api/my-clan/checkin')->assertJsonPath('coins', 3);
        $this->actingAs($newbie)->postJson('/api/my-clan/checkin')->assertJsonPath('coins', 0);

        $this->assertSame(3, (int) $clan->fresh()->treasury);
    }

    public function test_treasury_purchase_gets_level_discount(): void
    {
        $this->assertSame(100, \App\Domains\Clan\Support\ClanLevel::discounted(100, 2));
        $this->assertSame(95, \App\Domains\Clan\Support\ClanLevel::discounted(100, 3));
        $this->assertSame(90, \App\Domains\Clan\Support\ClanLevel::discounted(100, 6));
        $this->assertSame(80, \App\Domains\Clan\Support\ClanLevel::discounted(100, 12));
    }

    public function test_officer_writes_motd_and_member_cannot(): void
    {
        [$clan] = $this->clan();
        $officer = $this->member($clan, 'officer');
        $member = $this->member($clan);

        $this->actingAs($member)->putJson('/api/my-clan/motd', ['text' => 'Привет'])->assertForbidden();

        $this->actingAs($officer)->putJson('/api/my-clan/motd', ['text' => 'Сбор в субботу в 18:00'])
            ->assertOk()
            ->assertJsonPath('motd.text', 'Сбор в субботу в 18:00')
            ->assertJsonPath('motd.author.id', $officer->id);

        $this->actingAs($member)->getJson('/api/my-clan/overview')
            ->assertJsonPath('motd.text', 'Сбор в субботу в 18:00')
            ->assertJsonPath('can_edit_motd', false);

        // Объявление видят только свои: в публичной карточке клана его нет
        $this->getJson("/api/clans/{$clan->id}")->assertJsonMissingPath('clan.motd');
    }

    public function test_overview_lists_feed_and_today_checkins(): void
    {
        [$clan, $leader] = $this->clan();
        $member = $this->member($clan);

        $this->actingAs($member)->postJson('/api/my-clan/checkin')->assertOk();

        $response = $this->actingAs($leader)->getJson('/api/my-clan/overview')->assertOk();

        $response->assertJsonPath('checkin.today_count', 1)
            ->assertJsonPath('checkin.today.0.id', $member->id)
            ->assertJsonPath('goals.items.0.value', 1);

        $this->assertNotEmpty($response->json('feed'));
        $this->assertCount(2, $response->json('top_members'));
    }

    public function test_pulse_reports_pending_checkin(): void
    {
        [$clan, $leader] = $this->clan();

        $this->actingAs($leader)->getJson('/api/my-clan/pulse')
            ->assertOk()
            ->assertJsonPath('clan.tag', 'AAA')
            ->assertJsonPath('checked_in', false);

        $this->actingAs($leader)->postJson('/api/my-clan/checkin');

        $this->actingAs($leader)->getJson('/api/my-clan/pulse')->assertJsonPath('checked_in', true);
    }

    public function test_outsider_has_no_access(): void
    {
        $this->clan();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->getJson('/api/my-clan/overview')->assertForbidden();
        $this->actingAs($stranger)->postJson('/api/my-clan/checkin')->assertForbidden();
    }
}
