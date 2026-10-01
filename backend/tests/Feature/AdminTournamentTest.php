<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTournamentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function tournament(array $attributes = []): Tournament
    {
        return Tournament::create(array_merge([
            'name' => 'Тестовый турнир',
            'slug' => 'test-tournament-' . uniqid(),
            'type' => 'solo',
            'format' => 'single_elim',
            'status' => 'registration',
            'max_participants' => 8,
            'created_by' => User::factory()->create()->id,
        ], $attributes));
    }

    private function participant(Tournament $tournament, array $attributes = []): TournamentParticipant
    {
        return TournamentParticipant::create(array_merge([
            'tournament_id' => $tournament->id,
            'user_id' => User::factory()->create()->id,
            'status' => 'pending',
        ], $attributes));
    }

    private function approvedParticipants(Tournament $tournament, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->participant($tournament, ['status' => 'approved', 'seed' => $i + 1]);
        }
    }

    /* ------------------------------- index ------------------------------ */

    public function test_index_paginates_and_filters_by_status(): void
    {
        $this->tournament(['status' => 'registration']);
        $draft = $this->tournament(['status' => 'draft']);

        $all = $this->actingAs($this->admin())->getJson('/api/admin/tournaments')->assertOk();

        $this->assertSame(20, $all->json('per_page'));
        $this->assertCount(2, $all->json('data'));
        $this->assertArrayHasKey('creator', $all->json('data.0'));

        $filtered = $this->actingAs($this->admin())
            ->getJson('/api/admin/tournaments?status=draft')
            ->assertOk();

        $this->assertSame([$draft->id], collect($filtered->json('data'))->pluck('id')->all());
    }

    /* ------------------------------- store ------------------------------ */

    public function test_store_creates_tournament_with_defaults(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/tournaments', [
                'name' => 'Зимний кубок',
                'description' => 'Описание турнира',
                'type' => 'solo',
                'format' => 'single_elim',
                'prize_pool' => 15000.5,
                'prize_currency' => 'RUB',
                'min_tier' => 'C',
                'max_tier' => 'S',
                'max_participants' => 32,
            ])
            ->assertCreated();

        $this->assertSame('Зимний кубок', $response->json('tournament.name'));
        $this->assertSame('registration', $response->json('tournament.status'));
        $this->assertSame($admin->id, $response->json('tournament.created_by'));
        $this->assertStringStartsWith('zimnii-kubok-', $response->json('tournament.slug'));
        $this->assertSame('15000.50', (string) $response->json('tournament.prize_pool'));

        $this->assertDatabaseHas('tournaments', [
            'id' => $response->json('tournament.id'),
            'created_by' => $admin->id,
            'status' => 'registration',
        ]);
    }

    public function test_store_validation_errors(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/admin/tournaments', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type', 'format', 'max_participants']);

        $this->actingAs($admin)
            ->postJson('/api/admin/tournaments', [
                'name' => 'X',
                'type' => 'squad',
                'format' => 'swiss',
                'max_participants' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'format', 'max_participants']);

        $this->actingAs($admin)
            ->postJson('/api/admin/tournaments', [
                'name' => 'X',
                'type' => 'solo',
                'format' => 'single_elim',
                'max_participants' => 129,
                'min_tier' => 'Z',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_participants', 'min_tier']);
    }

    public function test_store_rejects_registration_end_before_start(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/tournaments', [
                'name' => 'Кривые даты',
                'type' => 'solo',
                'format' => 'single_elim',
                'max_participants' => 8,
                'registration_starts_at' => now()->addDays(5)->toDateTimeString(),
                'registration_ends_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('registration_ends_at');
    }

    public function test_store_uploads_banner(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())
            ->post('/api/admin/tournaments', [
                'name' => 'С баннером',
                'type' => 'clan',
                'format' => 'double_elim',
                'max_participants' => 16,
                'banner' => UploadedFile::fake()->image('banner.png', 800, 300),
            ])
            ->assertCreated();

        $banner = $response->json('tournament.banner');

        $this->assertNotNull($banner);
        Storage::disk('public')->assertExists($banner);
    }

    /* ------------------------------ update ------------------------------ */

    public function test_update_works_via_put_and_post(): void
    {
        $tournament = $this->tournament();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson("/api/admin/tournaments/{$tournament->id}", ['name' => 'Новое имя'])
            ->assertOk();

        $this->assertSame('Новое имя', $tournament->fresh()->name);

        // Роут объявлен как match(['put','post'])
        $this->actingAs($admin)
            ->postJson("/api/admin/tournaments/{$tournament->id}", ['status' => 'ongoing'])
            ->assertOk();

        $this->assertSame('ongoing', $tournament->fresh()->status);
    }

    public function test_update_validates_status_and_format(): void
    {
        $tournament = $this->tournament();

        $this->actingAs($this->admin())
            ->putJson("/api/admin/tournaments/{$tournament->id}", ['status' => 'finished'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->actingAs($this->admin())
            ->putJson("/api/admin/tournaments/{$tournament->id}", ['format' => 'swiss'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('format');

        $this->assertSame('registration', $tournament->fresh()->status);
    }

    public function test_update_keeps_slug_unchanged(): void
    {
        $tournament = $this->tournament();
        $slug = $tournament->slug;

        $this->actingAs($this->admin())
            ->putJson("/api/admin/tournaments/{$tournament->id}", ['name' => 'Другое имя'])
            ->assertOk();

        $this->assertSame($slug, $tournament->fresh()->slug);
    }

    /* ------------------------------ destroy ----------------------------- */

    public function test_destroy_removes_tournament_and_matches(): void
    {
        $tournament = $this->tournament();
        $this->approvedParticipants($tournament, 4);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertOk();

        $this->assertSame(3, TournamentMatch::where('tournament_id', $tournament->id)->count());

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/tournaments/{$tournament->id}")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('tournaments', ['id' => $tournament->id]);
        $this->assertSame(0, TournamentMatch::where('tournament_id', $tournament->id)->count());
    }

    /* --------------------------- Участники ------------------------------ */

    public function test_participants_ordered_by_seed_with_relations(): void
    {
        $tournament = $this->tournament();
        $third = $this->participant($tournament, ['seed' => 3]);
        $first = $this->participant($tournament, ['seed' => 1]);
        $noSeed = $this->participant($tournament, ['seed' => null]);

        $response = $this->actingAs($this->admin())
            ->getJson("/api/admin/tournaments/{$tournament->id}/participants")
            ->assertOk();

        $seeds = collect($response->json('participants'))->pluck('seed')->all();

        // null-сид уезжает в начало (SQLite сортирует NULL первым)
        $this->assertSame([null, 1, 3], $seeds);
        $this->assertArrayHasKey('user', $response->json('participants.2'));
        $this->assertSame($first->user_id, $response->json('participants.1.user_id'));
    }

    public function test_approve_and_reject_participant(): void
    {
        $tournament = $this->tournament();
        $participant = $this->participant($tournament);

        $response = $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/participants/{$participant->id}/approve")
            ->assertOk();

        $this->assertSame('approved', $response->json('participant.status'));

        $response = $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/participants/{$participant->id}/reject")
            ->assertOk();

        $this->assertSame('rejected', $response->json('participant.status'));
        $this->assertSame('rejected', $participant->fresh()->status);
    }

    public function test_participant_from_another_tournament_returns_404(): void
    {
        $tournament = $this->tournament();
        $other = $this->tournament();
        $foreign = $this->participant($other);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/participants/{$foreign->id}/approve")
            ->assertNotFound();

        $this->assertSame('pending', $foreign->fresh()->status);
    }

    public function test_set_seeds_updates_slots(): void
    {
        $tournament = $this->tournament();
        $a = $this->participant($tournament);
        $b = $this->participant($tournament);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/seeds", [
                'seeds' => [
                    ['id' => $a->id, 'seed' => 2],
                    ['id' => $b->id, 'seed' => 1],
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(2, $a->fresh()->seed);
        $this->assertSame(1, $b->fresh()->seed);
    }

    public function test_set_seeds_validation_and_foreign_participant_is_ignored(): void
    {
        $tournament = $this->tournament();
        $mine = $this->participant($tournament);
        $foreign = $this->participant($this->tournament());

        // Пустой массив не проходит правило required
        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/seeds", ['seeds' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('seeds');

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/seeds", [
                'seeds' => [['id' => $mine->id, 'seed' => 0]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('seeds.0.seed');

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/seeds", [
                'seeds' => [['id' => 999999, 'seed' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('seeds.0.id');

        // Участник чужого турнира проходит exists, но молча не обновляется
        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/seeds", [
                'seeds' => [['id' => $foreign->id, 'seed' => 5]],
            ])
            ->assertOk();

        $this->assertNull($foreign->fresh()->seed);
    }

    /* ------------------------------ Сетка ------------------------------- */

    public function test_matches_endpoint_returns_bracket(): void
    {
        $tournament = $this->tournament();
        $this->approvedParticipants($tournament, 4);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertOk();

        $response = $this->actingAs($this->admin())
            ->getJson("/api/admin/tournaments/{$tournament->id}/matches")
            ->assertOk();

        $this->assertCount(3, $response->json('matches'));
        $this->assertArrayHasKey('participant1', $response->json('matches.0'));
        $this->assertArrayHasKey('winner', $response->json('matches.0'));
    }

    public function test_bracket_needs_at_least_two_approved_participants(): void
    {
        $tournament = $this->tournament();

        $this->participant($tournament, ['status' => 'pending']);
        $this->participant($tournament, ['status' => 'rejected']);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertStatus(422);

        $this->assertSame(0, TournamentMatch::where('tournament_id', $tournament->id)->count());
    }

    public function test_bracket_only_supported_for_single_elim(): void
    {
        foreach (['double_elim', 'round_robin'] as $format) {
            $tournament = $this->tournament(['format' => $format]);
            $this->approvedParticipants($tournament, 4);

            $response = $this->actingAs($this->admin())
                ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
                ->assertStatus(422);

            $this->assertStringContainsString('single_elim', $response->json('message'));
            $this->assertSame(0, TournamentMatch::where('tournament_id', $tournament->id)->count());
        }
    }

    public function test_regenerating_bracket_replaces_old_matches(): void
    {
        $tournament = $this->tournament();
        $this->approvedParticipants($tournament, 4);

        $first = $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertOk();

        $firstIds = collect($first->json('matches'))->pluck('id')->all();

        $second = $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertOk();

        $secondIds = collect($second->json('matches'))->pluck('id')->all();

        $this->assertCount(3, $secondIds);
        $this->assertEmpty(array_intersect($firstIds, $secondIds));
        $this->assertSame(3, TournamentMatch::where('tournament_id', $tournament->id)->count());
    }

    /* --------------------------- Матчи ---------------------------------- */

    public function test_update_match_with_winner_completes_it_and_advances(): void
    {
        $tournament = $this->tournament();
        $this->approvedParticipants($tournament, 4);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertOk();

        $round1 = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('round', 1)
            ->orderBy('position')
            ->get();

        $match = $round1->first();
        $winner = $match->participant1_id;

        $response = $this->actingAs($this->admin())
            ->putJson("/api/admin/tournaments/{$tournament->id}/matches/{$match->id}", [
                'score1' => 3,
                'score2' => 1,
                'winner_id' => $winner,
            ])
            ->assertOk();

        $this->assertSame('completed', $response->json('match.status'));
        $this->assertSame(3, $response->json('match.score1'));
        $this->assertNotNull($response->json('match.completed_at'));

        // Победитель уехал в следующий матч (position 0 → слот p1)
        $next = TournamentMatch::find($match->fresh()->next_match_id);
        $this->assertSame($winner, $next->participant1_id);
        $this->assertSame('pending', $next->status);

        // Второй матч первого раунда заполняет слот p2 и делает матч готовым
        $second = $round1->last();
        $secondWinner = $second->participant2_id;

        $this->actingAs($this->admin())
            ->putJson("/api/admin/tournaments/{$tournament->id}/matches/{$second->id}", [
                'winner_id' => $secondWinner,
            ])
            ->assertOk();

        $next->refresh();
        $this->assertSame($secondWinner, $next->participant2_id);
        $this->assertSame('ready', $next->status);
    }

    public function test_update_match_validation_and_cross_tournament_404(): void
    {
        $tournament = $this->tournament();
        $this->approvedParticipants($tournament, 2);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertOk();

        $match = TournamentMatch::where('tournament_id', $tournament->id)->firstOrFail();

        $this->actingAs($this->admin())
            ->putJson("/api/admin/tournaments/{$tournament->id}/matches/{$match->id}", [
                'score1' => -1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('score1');

        $this->actingAs($this->admin())
            ->putJson("/api/admin/tournaments/{$tournament->id}/matches/{$match->id}", [
                'status' => 'unknown',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->actingAs($this->admin())
            ->putJson("/api/admin/tournaments/{$tournament->id}/matches/{$match->id}", [
                'winner_id' => 999999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('winner_id');

        // Матч другого турнира — 404
        $other = $this->tournament();
        $this->approvedParticipants($other, 2);
        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$other->id}/bracket/generate")
            ->assertOk();
        $foreignMatch = TournamentMatch::where('tournament_id', $other->id)->firstOrFail();

        $this->actingAs($this->admin())
            ->putJson("/api/admin/tournaments/{$tournament->id}/matches/{$foreignMatch->id}", [
                'score1' => 1,
            ])
            ->assertNotFound();
    }

    public function test_final_winner_is_marked_completed_without_next_match(): void
    {
        $tournament = $this->tournament(['min_tier' => 'E', 'max_tier' => 'S']);
        $this->approvedParticipants($tournament, 2);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertOk();

        $final = TournamentMatch::where('tournament_id', $tournament->id)->firstOrFail();
        $this->assertNull($final->next_match_id);

        $this->actingAs($this->admin())
            ->putJson("/api/admin/tournaments/{$tournament->id}/matches/{$final->id}", [
                'winner_id' => $final->participant1_id,
            ])
            ->assertOk();

        $final->refresh();
        $this->assertSame('completed', $final->status);
        $this->assertNull($final->next_match_id);
    }

    /**
     * Исправлено: правило winner_id проверяло только существование записи,
     * поэтому победителем можно было назначить участника чужого турнира.
     */
    public function test_update_match_rejects_winner_from_another_tournament(): void
    {
        $tournament = $this->tournament();
        $this->approvedParticipants($tournament, 4);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertOk();

        $other = $this->tournament();
        $foreign = $this->participant($other, ['status' => 'approved']);

        $match = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('round', 1)
            ->orderBy('position')
            ->firstOrFail();

        $this->actingAs($this->admin())
            ->putJson("/api/admin/tournaments/{$tournament->id}/matches/{$match->id}", [
                'winner_id' => $foreign->id,
            ])
            ->assertStatus(422);

        $this->assertNull($match->fresh()->winner_id);
    }

    public function test_update_match_can_schedule_without_winner(): void
    {
        $tournament = $this->tournament();
        $this->approvedParticipants($tournament, 2);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertOk();

        $match = TournamentMatch::where('tournament_id', $tournament->id)->firstOrFail();
        $when = now()->addDay()->startOfSecond();

        $response = $this->actingAs($this->admin())
            ->putJson("/api/admin/tournaments/{$tournament->id}/matches/{$match->id}", [
                'status' => 'live',
                'scheduled_at' => $when->toDateTimeString(),
            ])
            ->assertOk();

        $this->assertSame('live', $response->json('match.status'));
        $this->assertNotNull($response->json('match.scheduled_at'));
        $this->assertNull($response->json('match.winner_id'));
    }
}
