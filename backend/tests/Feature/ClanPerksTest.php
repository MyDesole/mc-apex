<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanCheckin;
use App\Domains\Clan\Models\ClanInvitation;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Clan\Notifications\ClanInvitationNotification;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Кланы: приглашения, оформление, набор, бусты за казну и награды за цели недели.
 */
class ClanPerksTest extends TestCase
{
    use RefreshDatabase;

    /** level, xp и treasury нельзя задать массово — выставляем их отдельно. */
    private function clan(array $attrs = []): array
    {
        $leader = User::factory()->create();
        $level = $attrs['level'] ?? 1;
        $treasury = $attrs['treasury'] ?? 0;
        unset($attrs['level'], $attrs['treasury']);

        $clan = Clan::create(array_merge(['name' => 'Волки', 'tag' => 'WLF', 'leader_id' => $leader->id], $attrs));
        $clan->forceFill(['level' => $level, 'bonus_xp' => \App\Domains\Clan\Support\ClanLevel::xpFor($level), 'xp' => \App\Domains\Clan\Support\ClanLevel::xpFor($level), 'treasury' => $treasury])->save();
        ClanMember::create(['clan_id' => $clan->id, 'user_id' => $leader->id, 'role' => 'leader', 'joined_at' => now()]);

        return [$clan, $leader];
    }

    /* ----------------------------- Приглашения ----------------------------- */

    public function test_leader_invites_player_and_player_accepts_for_free(): void
    {
        Notification::fake();
        [$clan, $leader] = $this->clan(['entry_fee' => 500, 'is_open' => false]);
        $player = User::factory()->create(['apex_coins' => 0]);

        $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/invitations", [
            'username' => $player->username,
            'message' => 'Заходи к нам',
        ])->assertCreated();

        Notification::assertSentTo($player, ClanInvitationNotification::class);

        $invitation = ClanInvitation::first();

        $this->actingAs($player)->getJson('/api/clan-invitations')
            ->assertOk()
            ->assertJsonPath('invitations.0.clan.tag', 'WLF');

        // Клан закрыт и берёт плату, но по приглашению игрок вступает бесплатно
        $this->actingAs($player)->postJson("/api/clan-invitations/{$invitation->id}/accept")->assertOk();

        $this->assertTrue(ClanMember::where('clan_id', $clan->id)->where('user_id', $player->id)->exists());
        $this->assertSame(0, (int) $player->fresh()->apex_coins);
        $this->assertSame('accepted', $invitation->fresh()->status);
    }

    public function test_regular_member_cannot_invite_and_outsider_cannot_answer(): void
    {
        [$clan] = $this->clan();
        $member = User::factory()->create();
        ClanMember::create(['clan_id' => $clan->id, 'user_id' => $member->id, 'role' => 'member', 'joined_at' => now()]);
        $target = User::factory()->create();

        $this->actingAs($member)->postJson("/api/clans/{$clan->id}/invitations", ['username' => $target->username])
            ->assertForbidden();
    }

    public function test_cannot_invite_player_already_in_clan_or_twice(): void
    {
        Notification::fake();
        [$clan, $leader] = $this->clan();
        [, $otherLeader] = $this->clan(['name' => 'Лисы', 'tag' => 'FOX']);
        $free = User::factory()->create();

        $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/invitations", ['username' => $otherLeader->username])
            ->assertStatus(422);

        $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/invitations", ['username' => $free->username])->assertCreated();
        $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/invitations", ['username' => $free->username])->assertStatus(422);
    }

    public function test_declined_or_foreign_invitation_cannot_be_accepted(): void
    {
        Notification::fake();
        [$clan, $leader] = $this->clan();
        $player = User::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/invitations", ['username' => $player->username]);
        $invitation = ClanInvitation::first();

        $this->actingAs($stranger)->postJson("/api/clan-invitations/{$invitation->id}/accept")->assertForbidden();

        $this->actingAs($player)->postJson("/api/clan-invitations/{$invitation->id}/decline")->assertOk();
        $this->actingAs($player)->postJson("/api/clan-invitations/{$invitation->id}/accept")->assertStatus(422);
    }

    public function test_leader_can_cancel_invitation(): void
    {
        Notification::fake();
        [$clan, $leader] = $this->clan();
        $player = User::factory()->create();

        $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/invitations", ['username' => $player->username]);
        $invitation = ClanInvitation::first();

        $this->actingAs($leader)->deleteJson("/api/clans/{$clan->id}/invitations/{$invitation->id}")->assertOk();
        $this->assertSame('cancelled', $invitation->fresh()->status);
    }

    /* ----------------------------- Оформление и набор ----------------------------- */

    public function test_style_unlocks_with_clan_level(): void
    {
        [$clan, $leader] = $this->clan(['level' => 1]);

        // Базовые варианты открыты сразу
        $this->actingAs($leader)->putJson('/api/my-clan/style', [
            'motto' => 'Охота начинается',
            'emblem' => 'wolf',
            'theme' => 'ocean',
        ])->assertStatus(422)->assertJsonValidationErrors(['emblem']);

        $this->actingAs($leader)->putJson('/api/my-clan/style', [
            'motto' => 'Охота начинается',
            'emblem' => 'sword',
            'theme' => 'ocean',
        ])->assertOk()->assertJsonPath('current.emblem.key', 'sword');

        // Свой цвет тега — со 2 уровня
        $this->actingAs($leader)->putJson('/api/my-clan/style', ['tag_color' => '#ff8800'])->assertStatus(422);

        $clan->forceFill(['level' => 4, 'bonus_xp' => \App\Domains\Clan\Support\ClanLevel::xpFor(4), 'xp' => \App\Domains\Clan\Support\ClanLevel::xpFor(4)])->save();

        $leader = $leader->fresh();
        $this->actingAs($leader)->putJson('/api/my-clan/style', ['emblem' => 'crown', 'tag_color' => '#ff8800'])->assertOk();
        $this->assertSame('#ff8800', $clan->fresh()->tag_color);
    }

    public function test_only_leader_changes_style_and_catalog_marks_locked_items(): void
    {
        [$clan, $leader] = $this->clan(['level' => 3]);
        $officer = User::factory()->create();
        ClanMember::create(['clan_id' => $clan->id, 'user_id' => $officer->id, 'role' => 'officer', 'joined_at' => now()]);

        $this->actingAs($officer)->putJson('/api/my-clan/style', ['motto' => 'x'])->assertForbidden();

        $catalog = $this->actingAs($leader)->getJson('/api/my-clan/style')->assertOk()->json('catalog');
        $emblems = collect($catalog['emblems'])->keyBy('key');
        $this->assertTrue($emblems['wolf']['unlocked']);
        $this->assertFalse($emblems['dragon']['unlocked']);
    }

    public function test_min_rating_blocks_applications_below_threshold(): void
    {
        [$clan] = $this->clan(['min_rating' => 500, 'is_open' => true]);
        $weak = User::factory()->create(['rating_points' => 100]);
        $strong = User::factory()->create(['rating_points' => 900]);

        $this->actingAs($weak)->postJson("/api/clans/{$clan->id}/apply", [])->assertStatus(422);
        $this->actingAs($strong)->postJson("/api/clans/{$clan->id}/apply", [])->assertCreated();
    }

    /* ----------------------------- Бусты и цели ----------------------------- */

    public function test_leader_buys_boost_from_treasury_and_it_doubles_checkin_xp(): void
    {
        [$clan, $leader] = $this->clan(['treasury' => 1000]);

        $this->actingAs($leader)->postJson('/api/my-clan/boosts/xp')->assertOk();

        $clan->refresh();
        $this->assertSame(700, (int) $clan->treasury);
        $this->assertTrue($clan->xp_boost_until->isFuture());

        $leader = $leader->fresh();
        $this->actingAs($leader)->postJson('/api/my-clan/checkin')->assertOk()->assertJsonPath('xp', 20);
        $this->assertSame(20, (int) $clan->fresh()->xp);
    }

    public function test_boost_needs_enough_treasury_and_leader(): void
    {
        [$clan, $leader] = $this->clan(['treasury' => 100]);
        $officer = User::factory()->create();
        ClanMember::create(['clan_id' => $clan->id, 'user_id' => $officer->id, 'role' => 'officer', 'joined_at' => now()]);

        $this->actingAs($leader)->postJson('/api/my-clan/boosts/xp')->assertStatus(422);
        $this->actingAs($officer)->postJson('/api/my-clan/boosts/xp')->assertForbidden();
    }

    public function test_weekly_goal_reward_is_paid_once(): void
    {
        [$clan, $leader] = $this->clan(['treasury' => 0]);

        // Цель «отметки»: минимум 10 за неделю
        foreach (range(1, 10) as $i) {
            $user = User::factory()->create();
            ClanCheckin::create(['clan_id' => $clan->id, 'user_id' => $user->id, 'day' => now()->toDateString()]);
        }

        $this->actingAs($leader)->postJson('/api/my-clan/goals/wins/claim')->assertStatus(422);

        $this->actingAs($leader)->postJson('/api/my-clan/goals/checkins/claim')->assertOk()->assertJsonPath('coins', 150);
        $this->assertSame(150, (int) $clan->fresh()->treasury);

        $this->actingAs($leader)->postJson('/api/my-clan/goals/checkins/claim')->assertStatus(422);
        $this->assertSame(150, (int) $clan->fresh()->treasury);
    }

    public function test_overview_exposes_heroes_and_boosts(): void
    {
        [, $leader] = $this->clan();

        $this->actingAs($leader)->getJson('/api/my-clan/overview')
            ->assertOk()
            ->assertJsonStructure(['hero' => ['activity', 'donor'], 'boosts' => [['key', 'price', 'active']], 'goals' => ['items' => [['id', 'reward', 'done', 'claimed']]]]);
    }
}
