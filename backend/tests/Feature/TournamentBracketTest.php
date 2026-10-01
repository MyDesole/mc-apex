<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentBracketTest extends TestCase
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
            'min_tier' => 'E',
            'max_tier' => 'S',
            'created_by' => $this->admin()->id,
        ], $attributes));
    }

    private function approved(Tournament $tournament, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->create();

            TournamentParticipant::create([
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'status' => 'approved',
                'seed' => $i + 1,
            ]);
        }
    }

    public function test_generate_bracket_creates_matches(): void
    {
        $tournament = $this->tournament();
        $this->approved($tournament, 4);

        $response = $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertOk();

        // 4 участника → 3 матча (2 в первом раунде + финал)
        $this->assertCount(3, $response->json('matches'));
        $this->assertSame(3, TournamentMatch::where('tournament_id', $tournament->id)->count());

        // Раунды: 2 матча в первом, 1 во втором
        $this->assertSame(2, TournamentMatch::where('tournament_id', $tournament->id)->where('round', 1)->count());
        $this->assertSame(1, TournamentMatch::where('tournament_id', $tournament->id)->where('round', 2)->count());

        // Первый раунд заполнен участниками и готов к игре
        $first = TournamentMatch::where('tournament_id', $tournament->id)->where('round', 1)->get();

        foreach ($first as $match) {
            $this->assertNotNull($match->participant1_id);
            $this->assertNotNull($match->participant2_id);
            $this->assertSame('ready', $match->status);
            $this->assertNotNull($match->next_match_id, 'Матч должен вести в следующий раунд');
        }
    }

    public function test_generate_requires_two_approved_participants(): void
    {
        $tournament = $this->tournament();

        // Одобренных нет — понятная ошибка, а не 500
        $response = $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertStatus(422);

        $this->assertStringContainsString('2', $response->json('message'));

        // Один одобренный — тоже мало
        $this->approved($tournament, 1);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertStatus(422);
    }

    public function test_pending_participants_are_not_used_for_bracket(): void
    {
        $tournament = $this->tournament();

        // Двое зарегистрировались, но не одобрены
        for ($i = 0; $i < 2; $i++) {
            TournamentParticipant::create([
                'tournament_id' => $tournament->id,
                'user_id' => User::factory()->create()->id,
                'status' => 'pending',
                'seed' => $i + 1,
            ]);
        }

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertStatus(422);

        $this->assertSame(0, TournamentMatch::where('tournament_id', $tournament->id)->count());
    }

    public function test_bracket_pads_to_power_of_two(): void
    {
        $tournament = $this->tournament();
        $this->approved($tournament, 3);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertOk();

        // 3 участника → сетка на 4: 2 матча первого раунда + финал
        $this->assertSame(3, TournamentMatch::where('tournament_id', $tournament->id)->count());

        // Один матч первого раунда без пары — в статусе pending
        $this->assertSame(
            1,
            TournamentMatch::where('tournament_id', $tournament->id)->where('round', 1)->where('status', 'pending')->count()
        );
    }

    public function test_regeneration_replaces_old_matches(): void
    {
        $tournament = $this->tournament();
        $this->approved($tournament, 4);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertOk();

        $firstIds = TournamentMatch::where('tournament_id', $tournament->id)->pluck('id');

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertOk();

        // Старые матчи удалены, счётчик не удвоился
        $this->assertSame(3, TournamentMatch::where('tournament_id', $tournament->id)->count());
        $this->assertEmpty(
            array_intersect($firstIds->all(), TournamentMatch::where('tournament_id', $tournament->id)->pluck('id')->all())
        );
    }

    public function test_non_single_elim_format_is_rejected(): void
    {
        $tournament = $this->tournament(['format' => 'round_robin']);
        $this->approved($tournament, 4);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertStatus(422);
    }

    public function test_regular_user_cannot_generate_bracket(): void
    {
        $tournament = $this->tournament();
        $this->approved($tournament, 4);

        $this->actingAs(User::factory()->create())
            ->postJson("/api/admin/tournaments/{$tournament->id}/bracket/generate")
            ->assertForbidden();
    }
}
