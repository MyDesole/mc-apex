<?php

namespace Tests\Feature;

use App\Domains\Tournaments\Models\Tournament;
use App\Domains\Tournaments\Models\TournamentMatch;
use App\Domains\Tournaments\Models\TournamentWin;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Турнир целиком, как его проводит админ: создание, заявки, одобрение,
 * сетка (в том числе с «пустыми» слотами), результаты матчей, чемпион,
 * статусы и очки рейтинга.
 */
class TournamentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function createTournament(array $data = []): Tournament
    {
        $id = $this->actingAs($this->admin)->postJson('/api/admin/tournaments', array_merge([
            'name' => 'Кубок APEX',
            'type' => 'solo',
            'format' => 'single_elim',
            'max_participants' => 8,
            'win_points' => 60,
        ], $data))->assertCreated()->json('tournament.id');

        return Tournament::findOrFail($id);
    }

    /** @return array<int, User> */
    private function registerAndApprove(Tournament $t, int $count): array
    {
        $players = User::factory()->count($count)->create(['tier' => 'C', 'tier_score' => 45]);

        foreach ($players as $player) {
            $this->actingAs($player)->postJson("/api/tournaments/{$t->id}/register")->assertCreated();
        }

        foreach ($t->participants as $p) {
            $this->actingAs($this->admin)->postJson("/api/admin/tournaments/{$t->id}/participants/{$p->id}/approve")->assertOk();
        }

        return $players->all();
    }

    /** Сыграть все готовые матчи: побеждает первый участник. */
    private function playReadyMatches(Tournament $t): int
    {
        $played = 0;

        foreach (TournamentMatch::where('tournament_id', $t->id)->where('status', 'ready')->orderBy('round')->get() as $match) {
            $this->actingAs($this->admin)
                ->putJson("/api/admin/tournaments/{$t->id}/matches/{$match->id}", [
                    'score1' => 2, 'score2' => 0, 'winner_id' => $match->participant1_id,
                ])->assertOk();
            $played++;
        }

        return $played;
    }

    public function test_admin_removes_approved_participant_and_unplayed_bracket_resets(): void
    {
        $t = $this->createTournament();
        $this->registerAndApprove($t, 4);
        $this->actingAs($this->admin)->postJson("/api/admin/tournaments/{$t->id}/bracket/generate")->assertOk();

        $participant = $t->participants()->first();

        $this->actingAs($this->admin)
            ->deleteJson("/api/admin/tournaments/{$t->id}/participants/{$participant->id}")
            ->assertOk()
            ->assertJsonPath('bracket_reset', true);

        $this->assertDatabaseMissing('tournament_participants', ['id' => $participant->id]);
        $this->assertSame(0, TournamentMatch::where('tournament_id', $t->id)->count());
        $this->assertSame('registration', $t->fresh()->status);

        // Сетку можно построить заново из оставшихся
        $this->actingAs($this->admin)->postJson("/api/admin/tournaments/{$t->id}/bracket/generate")->assertOk();
    }

    public function test_participant_cannot_be_removed_after_matches_are_played(): void
    {
        $t = $this->createTournament();
        $this->registerAndApprove($t, 4);
        $this->actingAs($this->admin)->postJson("/api/admin/tournaments/{$t->id}/bracket/generate")->assertOk();
        $this->playReadyMatches($t);

        $participant = $t->participants()->first();

        $this->actingAs($this->admin)
            ->deleteJson("/api/admin/tournaments/{$t->id}/participants/{$participant->id}")
            ->assertUnprocessable();
    }

    public function test_full_tournament_with_byes_reaches_a_champion(): void
    {
        $t = $this->createTournament();
        $this->registerAndApprove($t, 5);

        $this->actingAs($this->admin)->postJson("/api/admin/tournaments/{$t->id}/bracket/generate")->assertOk();

        // Сетка на 8: у троих «пропуск» — они уже во втором раунде, пустых матчей нет
        $t->refresh();
        $this->assertSame('ongoing', $t->status);
        $this->assertSame(7, TournamentMatch::where('tournament_id', $t->id)->count());
        $this->assertSame(0, TournamentMatch::where('tournament_id', $t->id)->where('round', 1)
            ->whereNull('participant1_id')->whereNull('participant2_id')->count());

        // Регистрация после старта сетки закрыта
        $late = User::factory()->create(['tier' => 'C', 'tier_score' => 45]);
        $this->actingAs($late)->postJson("/api/tournaments/{$t->id}/register")->assertUnprocessable();

        // Играем раунд за раундом, пока не останется готовых матчей
        $guard = 0;
        while ($this->playReadyMatches($t) > 0 && $guard++ < 5) {
        }

        $final = TournamentMatch::where('tournament_id', $t->id)->whereNull('next_match_id')->firstOrFail();
        $this->assertNotNull($final->winner_id, 'Финал должен быть сыгран');

        $t->refresh();
        $this->assertSame('completed', $t->status);

        $champion = $final->winner->user;
        $this->assertTrue(TournamentWin::where('user_id', $champion->id)->where('tournament_id', $t->id)->exists());
        $this->assertGreaterThanOrEqual(60, $champion->fresh()->rating_points);

        // Публичная страница отдаёт сетку и чемпиона
        $this->getJson("/api/tournaments/{$t->id}")
            ->assertOk()
            ->assertJsonPath('tournament.status', 'completed')
            ->assertJsonPath('tournament.champion.id', $champion->id);
    }

    public function test_cannot_change_result_after_next_match_is_played(): void
    {
        $t = $this->createTournament();
        $this->registerAndApprove($t, 4);
        $this->actingAs($this->admin)->postJson("/api/admin/tournaments/{$t->id}/bracket/generate")->assertOk();

        $this->playReadyMatches($t); // полуфиналы
        $this->playReadyMatches($t); // финал

        $semi = TournamentMatch::where('tournament_id', $t->id)->where('round', 1)->orderBy('position')->first();

        $this->actingAs($this->admin)
            ->putJson("/api/admin/tournaments/{$t->id}/matches/{$semi->id}", ['winner_id' => $semi->participant2_id])
            ->assertUnprocessable();
    }

    public function test_banner_update_stores_file_and_unsupported_format_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->postJson('/api/admin/tournaments', [
            'name' => 'Круговой', 'type' => 'solo', 'format' => 'round_robin', 'max_participants' => 8,
        ])->assertUnprocessable();

        $t = $this->createTournament();

        $this->actingAs($this->admin)->post("/api/admin/tournaments/{$t->id}", [
            'banner' => UploadedFile::fake()->image('banner.png', 1200, 400),
        ], ['Accept' => 'application/json'])->assertOk();

        $banner = $t->fresh()->banner;
        $this->assertStringStartsWith('tournaments/', $banner);
        Storage::disk('public')->assertExists($banner);
    }

    public function test_participant_gets_notified_about_approval_and_win(): void
    {
        $t = $this->createTournament();
        [$first] = $this->registerAndApprove($t, 2);

        $this->assertSame('tournament_approved', $first->notifications()->first()?->data['type']);

        $this->actingAs($this->admin)->postJson("/api/admin/tournaments/{$t->id}/bracket/generate")->assertOk();
        $this->playReadyMatches($t);

        $champion = TournamentMatch::where('tournament_id', $t->id)->whereNull('next_match_id')->first()->winner->user;
        $this->assertTrue($champion->notifications()->get()->contains(fn ($n) => $n->data['type'] === 'tournament_won'));
    }
}
