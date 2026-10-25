<?php

namespace Tests\Feature;

use App\Domains\Bridge\Models\BridgeTechnique;
use App\Domains\Bridge\Models\UserBridgeTechnique;
use App\Domains\Clan\Models\ClanWar;
use App\Domains\Clan\Models\ClanWarParticipant;
use App\Domains\Players\Models\PlayerAspectBedwars;
use App\Domains\Players\Models\PlayerAspectPvp;
use App\Domains\Players\Services\RatingService;
use App\Domains\Tournaments\Models\Tournament;
use App\Domains\Tournaments\Models\TournamentMatch;
use App\Domains\Tournaments\Models\TournamentParticipant;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ClanFixtures;
use Tests\TestCase;

/**
 * Очки общего рейтинга и сила клана.
 */
class RatingPointsTest extends TestCase
{
    use ClanFixtures, RefreshDatabase;

    private function pvp(User $user, int $each): void
    {
        PlayerAspectPvp::updateOrCreate(['user_id' => $user->id], [
            'block_placing' => $each, 'rotka' => $each, 'movement' => $each, 'aim' => $each, 'game_sense' => $each,
        ]);
    }

    private function tournament(array $attributes = []): Tournament
    {
        return Tournament::create(array_merge([
            'name' => 'Турнир',
            'slug' => 'cup-'.uniqid(),
            'type' => 'solo',
            'format' => 'single_elim',
            'status' => 'ongoing',
            'max_participants' => 8,
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
            'win_points' => 40,
        ], $attributes));
    }

    private function participant(Tournament $t, User $user): TournamentParticipant
    {
        return TournamentParticipant::create(['tournament_id' => $t->id, 'user_id' => $user->id, 'status' => 'approved']);
    }

    public function test_tiers_average_is_always_divided_by_three(): void
    {
        $player = User::factory()->create();
        $this->pvp($player, 18);  // 90%

        $this->assertSame(30, $player->fresh()->rating_points);

        PlayerAspectBedwars::create([
            'user_id' => $player->id,
            'pvp' => 12, 'game_sense' => 12, 'bed_play' => 12, 'teamplay' => 12, 'building' => 12, // 60%
        ]);

        $this->assertSame(50, $player->fresh()->rating_points);
    }

    public function test_bridge_percent_is_average_of_confirmed_technique_aspects(): void
    {
        $player = User::factory()->create();
        $technique = BridgeTechnique::create(['key' => 'ninja', 'label' => 'Ниндзя', 'is_active' => true]);

        UserBridgeTechnique::create([
            'user_id' => $player->id, 'technique_id' => $technique->id, 'status' => 'confirmed',
            'stability' => 90, 'speed' => 60, 'difficulty' => 90, 'score' => 5,  // 80%
        ]);

        $breakdown = app(RatingService::class)->breakdown($player);
        $this->assertSame(80, $breakdown['bridge']);
        $this->assertSame(27, $player->fresh()->rating_points); // ceil(80 / 3)
    }

    public function test_tournament_match_wins_and_tournament_win_points(): void
    {
        $winner = User::factory()->create();
        $loser = User::factory()->create();
        $t = $this->tournament(['win_points' => 40]);
        $pw = $this->participant($t, $winner);
        $pl = $this->participant($t, $loser);

        // Полуфинал → финал: два выигранных матча и победа в турнире
        $final = TournamentMatch::create(['tournament_id' => $t->id, 'round' => 2, 'position' => 1, 'participant1_id' => $pw->id, 'participant2_id' => $pl->id]);
        TournamentMatch::create(['tournament_id' => $t->id, 'round' => 1, 'position' => 1, 'participant1_id' => $pw->id, 'winner_id' => $pw->id, 'status' => 'completed', 'next_match_id' => $final->id]);
        $final->update(['winner_id' => $pw->id, 'status' => 'completed']);

        // 2 матча × 5 + 40 за турнир
        $this->assertSame(50, $winner->fresh()->rating_points);
        $this->assertSame(0, $loser->fresh()->rating_points);

        // Админ поменял награду турнира — очки пересчитались
        $t->update(['win_points' => 100]);
        $this->assertSame(110, $winner->fresh()->rating_points);
    }

    public function test_clan_war_participation_win_and_clan_power(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $staff = User::factory()->create(['role' => 'moderator']);
        $clanA = $this->clan($a);
        $this->member($clanA, $staff);
        $clanB = $this->clan($b);

        $war = ClanWar::create([
            'challenger_clan_id' => $clanA->id, 'opponent_clan_id' => $clanB->id,
            'created_by' => $a->id, 'status' => 'accepted',
        ]);
        ClanWarParticipant::create(['clan_war_id' => $war->id, 'user_id' => $a->id, 'clan_id' => $clanA->id]);
        ClanWarParticipant::create(['clan_war_id' => $war->id, 'user_id' => $b->id, 'clan_id' => $clanB->id]);

        // Пока война не завершена — очков нет
        $this->assertSame(0, $a->fresh()->rating_points);

        $war->update(['status' => 'completed', 'winner_clan_id' => $clanA->id]);

        $this->assertSame(10, $a->fresh()->rating_points); // 3 за участие + 7 за победу
        $this->assertSame(3, $b->fresh()->rating_points);

        // Сила клана — половина очков игроков из рейтинга (персонал не в счёт)
        $this->pvp($staff, 20);
        $this->assertSame(5, $clanA->fresh()->power);
        $this->assertSame(1, $clanB->fresh()->power);
    }

    public function test_overall_ranking_and_profile_use_rating_points(): void
    {
        $first = User::factory()->create(['username' => 'First']);
        $second = User::factory()->create(['username' => 'Second']);
        $this->pvp($first, 20);
        $this->pvp($second, 10);

        $this->getJson('/api/ranking')
            ->assertOk()
            ->assertJsonPath('data.0.username', 'First')
            ->assertJsonPath('data.0.rating_score', 34); // ceil(100 / 3)

        $this->actingAs($second)->getJson("/api/players/{$second->id}")
            ->assertOk()
            ->assertJsonPath('rank.position', 2)
            ->assertJsonPath('rating.total', 17)
            ->assertJsonPath('rating.pvp', 50);
    }

    public function test_admin_changes_weights_and_everyone_is_recalculated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create();
        $clan = $this->clan($player);
        $other = $this->clan();
        $war = ClanWar::create([
            'challenger_clan_id' => $clan->id, 'opponent_clan_id' => $other->id,
            'created_by' => $player->id, 'status' => 'completed', 'winner_clan_id' => $other->id,
        ]);
        ClanWarParticipant::create(['clan_war_id' => $war->id, 'user_id' => $player->id, 'clan_id' => $clan->id]);
        $this->assertSame(3, $player->fresh()->rating_points);

        $this->actingAs($admin)
            ->putJson('/api/admin/site-settings', ['rating' => ['rating_war_participation' => 20]])
            ->assertOk();

        $this->assertSame(20, $player->fresh()->rating_points);
        $this->assertSame(10, $clan->fresh()->power);

        $this->actingAs($admin)
            ->putJson('/api/admin/site-settings', ['rating' => ['unknown_key' => 5]])
            ->assertUnprocessable();
    }
}
