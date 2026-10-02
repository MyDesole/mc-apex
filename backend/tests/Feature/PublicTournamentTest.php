<?php

namespace Tests\Feature;

use App\Domains\Tournaments\Models\Tournament;
use App\Domains\Tournaments\Models\TournamentParticipant;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Публичный просмотр и регистрация: /api/tournaments
 */
class PublicTournamentTest extends TestCase
{
    use RefreshDatabase;

    private function tournament(array $attributes = []): Tournament
    {
        static $i = 0;
        $i++;

        return Tournament::create(array_merge([
            'name' => "Турнир {$i}",
            'slug' => "tournament-{$i}",
            'type' => 'solo',
            'format' => 'single_elim',
            'status' => 'registration',
            'max_participants' => 8,
            'created_by' => User::factory()->create()->id,
        ], $attributes));
    }

    private function approved(Tournament $tournament, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            TournamentParticipant::create([
                'tournament_id' => $tournament->id,
                'user_id' => User::factory()->create()->id,
                'status' => 'approved',
            ]);
        }
    }

    /* ------------------------------- index ------------------------------ */

    public function test_index_is_public_and_paginates(): void
    {
        $this->tournament();
        $this->tournament();

        $response = $this->getJson('/api/tournaments')->assertOk();

        $this->assertSame(12, $response->json('per_page'));
        $this->assertCount(2, $response->json('data'));
        $this->assertArrayHasKey('creator', $response->json('data.0'));
        $this->assertArrayHasKey('participants_count', $response->json('data.0'));
    }

    public function test_index_filters_by_search_type_and_status(): void
    {
        $solo = $this->tournament(['name' => 'Летний кубок', 'type' => 'solo', 'status' => 'registration']);
        $clan = $this->tournament(['name' => 'Клановый кубок', 'type' => 'clan', 'status' => 'ongoing']);

        $bySearch = $this->getJson('/api/tournaments?search=Летний')->assertOk();
        $this->assertSame([$solo->id], collect($bySearch->json('data'))->pluck('id')->all());

        $byType = $this->getJson('/api/tournaments?type=clan')->assertOk();
        $this->assertSame([$clan->id], collect($byType->json('data'))->pluck('id')->all());

        $byStatus = $this->getJson('/api/tournaments?status=ongoing')->assertOk();
        $this->assertSame([$clan->id], collect($byStatus->json('data'))->pluck('id')->all());
    }

    public function test_index_counts_only_all_participants(): void
    {
        $tournament = $this->tournament();
        $this->approved($tournament, 2);
        TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'user_id' => User::factory()->create()->id,
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/tournaments')->assertOk();

        $this->assertSame(3, $response->json('data.0.participants_count'));
    }

    /* -------------------------------- show ------------------------------ */

    public function test_show_payload_shape(): void
    {
        $tournament = $this->tournament();
        $this->approved($tournament, 1);

        $response = $this->getJson("/api/tournaments/{$tournament->id}")->assertOk();

        $this->assertSame($tournament->id, $response->json('tournament.id'));
        $this->assertNull($response->json('my_participation'));
        $this->assertTrue($response->json('registration_open'));
        $this->assertCount(1, $response->json('tournament.participants'));
        $this->assertArrayHasKey('matches', $response->json('tournament'));
    }

    public function test_show_returns_my_participation_for_logged_in_user(): void
    {
        $tournament = $this->tournament();
        $user = User::factory()->create();

        TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/tournaments/{$tournament->id}")
            ->assertOk();

        $this->assertSame('pending', $response->json('my_participation.status'));

        $other = User::factory()->create();
        $this->assertNull(
            $this->actingAs($other)->getJson("/api/tournaments/{$tournament->id}")->json('my_participation')
        );
    }

    public function test_show_unknown_tournament_returns_404(): void
    {
        $this->getJson('/api/tournaments/999999')->assertNotFound();
    }

    public function test_registration_open_flag_reflects_status_and_dates(): void
    {
        $ongoing = $this->tournament(['status' => 'ongoing']);
        $notStarted = $this->tournament(['registration_starts_at' => now()->addDay()]);
        $ended = $this->tournament(['registration_ends_at' => now()->subDay()]);

        $this->assertFalse($this->getJson("/api/tournaments/{$ongoing->id}")->json('registration_open'));
        $this->assertFalse($this->getJson("/api/tournaments/{$notStarted->id}")->json('registration_open'));
        $this->assertFalse($this->getJson("/api/tournaments/{$ended->id}")->json('registration_open'));
    }

    /* ------------------------------ register ---------------------------- */

    public function test_guest_cannot_register_or_withdraw(): void
    {
        $tournament = $this->tournament();

        $this->postJson("/api/tournaments/{$tournament->id}/register")->assertUnauthorized();
        $this->postJson("/api/tournaments/{$tournament->id}/withdraw")->assertUnauthorized();
    }

    public function test_user_can_register_and_withdraw(): void
    {
        $tournament = $this->tournament();
        $user = User::factory()->create(['tier' => 'E']);

        $response = $this->actingAs($user)
            ->postJson("/api/tournaments/{$tournament->id}/register")
            ->assertCreated();

        $this->assertSame('pending', $response->json('participant.status'));
        $this->assertSame($user->id, $response->json('participant.user_id'));
        $this->assertNull($response->json('participant.clan_id'));

        $this->assertDatabaseHas('tournament_participants', [
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->postJson("/api/tournaments/{$tournament->id}/withdraw")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('tournament_participants', [
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_register_rejects_closed_registration(): void
    {
        $tournament = $this->tournament(['status' => 'ongoing']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/api/tournaments/{$tournament->id}/register")
            ->assertStatus(422);

        $this->assertSame('Регистрация закрыта.', $response->json('message'));
    }

    public function test_register_rejects_duplicate(): void
    {
        $tournament = $this->tournament();
        $user = User::factory()->create(['tier' => 'E']);

        $this->actingAs($user)
            ->postJson("/api/tournaments/{$tournament->id}/register")
            ->assertCreated();

        $response = $this->actingAs($user)
            ->postJson("/api/tournaments/{$tournament->id}/register")
            ->assertStatus(422);

        $this->assertSame('Вы уже зарегистрированы.', $response->json('message'));
        $this->assertSame(
            1,
            TournamentParticipant::where('tournament_id', $tournament->id)->where('user_id', $user->id)->count()
        );
    }

    public function test_register_rejects_when_filled(): void
    {
        $tournament = $this->tournament(['max_participants' => 2]);
        $this->approved($tournament, 2);

        $response = $this->actingAs(User::factory()->create(['tier' => 'E']))
            ->postJson("/api/tournaments/{$tournament->id}/register")
            ->assertStatus(422);

        $this->assertSame('Турнир заполнен.', $response->json('message'));
    }

    public function test_pending_participants_do_not_fill_the_tournament(): void
    {
        $tournament = $this->tournament(['max_participants' => 2]);

        TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'user_id' => User::factory()->create()->id,
            'status' => 'pending',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'E']))
            ->postJson("/api/tournaments/{$tournament->id}/register")
            ->assertCreated();
    }

    public function test_register_enforces_tier_bounds(): void
    {
        $tooLow = $this->tournament(['min_tier' => 'C']);
        $tooHigh = $this->tournament(['max_tier' => 'B']);

        $lowUser = User::factory()->create(['tier' => 'E']);
        $response = $this->actingAs($lowUser)
            ->postJson("/api/tournaments/{$tooLow->id}/register")
            ->assertStatus(422);
        $this->assertSame('Нужен тир C или выше.', $response->json('message'));

        $highUser = User::factory()->create(['tier' => 'S']);
        $response = $this->actingAs($highUser)
            ->postJson("/api/tournaments/{$tooHigh->id}/register")
            ->assertStatus(422);
        $this->assertSame('Нужен тир B или ниже.', $response->json('message'));

        // Подходящий тир проходит
        $okUser = User::factory()->create(['tier' => 'B']);
        $this->actingAs($okUser)
            ->postJson("/api/tournaments/{$tooLow->id}/register")
            ->assertCreated();
    }

    /**
     * Исправлено: тира S+ не было в списке для array_search, поэтому он
     * обходил ограничения. Теперь S+ сравнивается корректно: он выше S.
     */
    public function test_s_plus_tier_respects_tier_limits(): void
    {
        $withMaxB = $this->tournament(['max_tier' => 'B']);
        $withMinE = $this->tournament(['min_tier' => 'E']);
        $withMinC = $this->tournament(['min_tier' => 'C']);

        // Ограничение «B или ниже»: S+ выше — отказ
        $this->actingAs(User::factory()->create(['tier' => 'S+']))
            ->postJson("/api/tournaments/{$withMaxB->id}/register")
            ->assertStatus(422);

        // Ограничение «E или выше»: S+ подходит
        $this->actingAs(User::factory()->create(['tier' => 'S+']))
            ->postJson("/api/tournaments/{$withMinE->id}/register")
            ->assertCreated();

        // Ограничение «C или выше»: S+ тоже подходит
        $this->actingAs(User::factory()->create(['tier' => 'S+']))
            ->postJson("/api/tournaments/{$withMinC->id}/register")
            ->assertCreated();
    }

    /**
     * Исправлено: игрок без клана создавал «пустого» участника
     * (user_id = null и clan_id = null), причём повторно — NULL не
     * конфликтует в уникальном индексе, и в сетке появлялись фантомы.
     */
    public function test_clan_tournament_registration_without_clan_is_rejected(): void
    {
        $tournament = $this->tournament(['type' => 'clan']);
        $user = User::factory()->create(['tier' => 'E']);

        $this->actingAs($user)
            ->postJson("/api/tournaments/{$tournament->id}/register")
            ->assertStatus(422);

        $this->assertSame(0, TournamentParticipant::where('tournament_id', $tournament->id)->count());
    }

    public function test_clan_tournament_registration_uses_member_clan(): void
    {
        $tournament = $this->tournament(['type' => 'clan']);

        $leader = User::factory()->create(['tier' => 'E']);
        $clan = \App\Domains\Clan\Models\Clan::create([
            'name' => 'Клан для турнира',
            'tag' => 'KTT',
            'leader_id' => $leader->id,
        ]);
        \App\Domains\Clan\Models\ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $leader->id,
            'role' => 'leader',
        ]);

        $response = $this->actingAs($leader)
            ->postJson("/api/tournaments/{$tournament->id}/register")
            ->assertCreated();

        $this->assertNull($response->json('participant.user_id'));
        $this->assertSame($clan->id, $response->json('participant.clan_id'));

        // Второй участник того же клана получает отказ по дубликату
        $member = User::factory()->create(['tier' => 'E']);
        \App\Domains\Clan\Models\ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $this->actingAs($member)
            ->postJson("/api/tournaments/{$tournament->id}/register")
            ->assertStatus(422);
    }

    /* ------------------------------ withdraw ---------------------------- */

    public function test_withdraw_rejected_after_approval(): void
    {
        $tournament = $this->tournament();
        $user = User::factory()->create(['tier' => 'E']);

        TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/tournaments/{$tournament->id}/withdraw")
            ->assertStatus(422);

        $this->assertSame('Нельзя снять заявку после одобрения.', $response->json('message'));
        $this->assertDatabaseHas('tournament_participants', [
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_withdraw_without_participation_returns_404(): void
    {
        $tournament = $this->tournament();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/tournaments/{$tournament->id}/withdraw")
            ->assertNotFound();
    }
}
