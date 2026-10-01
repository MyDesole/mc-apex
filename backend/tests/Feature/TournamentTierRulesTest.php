<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Тир-правила турниров и целостность сетки.
 *
 * До рефакторинга: тир S+ обходил ограничения (array_search возвращал false),
 * победителем матча можно было указать участника другого турнира,
 * а /api/top показывал забаненные кланы.
 */
class TournamentTierRulesTest extends TestCase
{
    use RefreshDatabase;

    private function tournament(array $attributes = []): Tournament
    {
        return Tournament::create(array_merge([
            'name' => 'Турнир',
            'slug' => 'tournament-' . uniqid(),
            'type' => 'solo',
            'format' => 'single_elim',
            'status' => 'registration',
            'max_participants' => 8,
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
        ], $attributes));
    }

    /* ------------------------- Тир-ограничения ------------------------- */

    public function test_s_plus_within_max_tier_is_accepted(): void
    {
        $tournament = $this->tournament(['max_tier' => 'A']);
        $player = User::factory()->create(['tier' => 'S+']);

        // S+ выше A — при ограничении «A или ниже» должен быть отказ
        $this->actingAs($player)
            ->postJson("/api/tournaments/{$tournament->id}/register")
            ->assertStatus(422);
    }

    public function test_s_plus_within_min_tier_is_accepted(): void
    {
        $tournament = $this->tournament(['min_tier' => 'C']);
        $player = User::factory()->create(['tier' => 'S+']);

        // S+ выше C — ограничение «C или выше» выполнено
        $this->actingAs($player)
            ->postJson("/api/tournaments/{$tournament->id}/register")
            ->assertCreated();
    }

    public function test_s_plus_only_events_accept_s_plus(): void
    {
        $tournament = $this->tournament(['min_tier' => 'S', 'max_tier' => 'S']);
        $player = User::factory()->create(['tier' => 'S+']);

        // Ограничение ровно S: S+ не подходит
        $this->actingAs($player)
            ->postJson("/api/tournaments/{$tournament->id}/register")
            ->assertStatus(422);

        $sPlayer = User::factory()->create(['tier' => 'S']);

        $this->actingAs($sPlayer)
            ->postJson("/api/tournaments/{$tournament->id}/register")
            ->assertCreated();
    }

    /* ------------------------- Победитель матча ------------------------- */

    public function test_winner_from_another_tournament_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $tournament = $this->tournament();
        $other = $this->tournament(['name' => 'Другой', 'slug' => 'other-' . uniqid()]);

        $match = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 1,
            'position' => 1,
            'status' => 'ready',
        ]);

        $foreign = TournamentParticipant::create([
            'tournament_id' => $other->id,
            'user_id' => User::factory()->create()->id,
            'status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->putJson("/api/admin/tournaments/{$tournament->id}/matches/{$match->id}", [
                'winner_id' => $foreign->id,
            ])
            ->assertStatus(422);

        $this->assertNull($match->fresh()->winner_id);
    }

    /* ------------------------- Топ кланов ------------------------- */

    public function test_banned_clans_are_hidden_from_public_top(): void
    {
        $leader = User::factory()->create();

        Clan::create([
            'name' => 'Забаненный',
            'tag' => 'BAN',
            'leader_id' => $leader->id,
            'power' => 1000,
            'is_banned' => true,
            'ban_reason' => 'нарушения',
        ]);

        Clan::create([
            'name' => 'Нормальный',
            'tag' => 'OK',
            'leader_id' => $leader->id,
            'power' => 10,
        ]);

        $ids = collect($this->getJson('/api/top')->assertOk()->json('clans'))
            ->pluck('name')
            ->all();

        $this->assertContains('Нормальный', $ids);
        $this->assertNotContains('Забаненный', $ids);
    }
}
