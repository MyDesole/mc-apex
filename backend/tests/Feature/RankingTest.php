<?php

namespace Tests\Feature;

use App\Domains\Players\Models\PlayerAspectBedwars;
use App\Domains\Players\Models\PlayerAspectPvp;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingTest extends TestCase
{
    use RefreshDatabase;

    private function player(array $pvp = [], array $bw = []): User
    {
        $user = User::factory()->create();

        if ($pvp) {
            PlayerAspectPvp::create(array_merge(['user_id' => $user->id], $pvp));
        }

        if ($bw) {
            PlayerAspectBedwars::create(array_merge(['user_id' => $user->id], $bw));
        }

        return $user;
    }

    public function test_ranking_sorts_by_score_and_paginates_by_cursor(): void
    {
        // 7 игроков с разными очками
        $scores = [];

        for ($i = 1; $i <= 7; $i++) {
            $scores[$i] = $this->player(
                ['block_placing' => $i, 'rotka' => $i, 'movement' => $i, 'aim' => $i, 'game_sense' => $i],
                ['pvp' => $i, 'game_sense' => $i, 'bed_play' => $i, 'teamplay' => $i, 'building' => $i],
            );
        }

        // Игрок без аспектов не должен попадать в рейтинг
        User::factory()->create();

        $first = $this->getJson('/api/ranking?limit=5')->assertOk();

        $this->assertCount(5, $first->json('data'));
        $this->assertTrue($first->json('has_more'));

        // По убыванию очков
        $pageScores = collect($first->json('data'))->pluck('rating_score')->all();
        $sorted = $sorted ?? $pageScores;
        $copy = $pageScores;
        rsort($copy);
        $this->assertSame($copy, $pageScores, 'Рейтинг должен идти по убыванию');

        $cursor = $first->json('next_cursor');
        $this->assertNotNull($cursor);

        $second = $this->getJson(
            "/api/ranking?limit=5&cursor_score={$cursor['score']}&cursor_id={$cursor['id']}&offset={$cursor['offset']}"
        )->assertOk();

        $firstIds = collect($first->json('data'))->pluck('id');
        $secondIds = collect($second->json('data'))->pluck('id');

        $this->assertEmpty($firstIds->intersect($secondIds), 'Страницы не должны пересекаться');
        $this->assertFalse($second->json('has_more'));

        // Игрок без аспектов отсутствует во всём рейтинге
        $allIds = $firstIds->merge($secondIds);
        $this->assertCount(7, $allIds);
    }

    public function test_ranking_positions_continue_across_pages(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->player(['block_placing' => $i, 'rotka' => 0, 'movement' => 0, 'aim' => 0, 'game_sense' => 0]);
        }

        $first = $this->getJson('/api/ranking?limit=5')->assertOk();
        $this->assertSame([1, 2, 3, 4, 5], collect($first->json('data'))->pluck('position')->all());

        $cursor = $first->json('next_cursor');

        $second = $this->getJson(
            "/api/ranking?limit=5&cursor_score={$cursor['score']}&cursor_id={$cursor['id']}&offset={$cursor['offset']}"
        )->assertOk();

        fwrite(STDERR, "first: " . json_encode(collect($first->json('data'))->map(fn($p) => [$p['position'], $p['id'], $p['rating_score']])->all()) . "\n");
        fwrite(STDERR, "cursor: " . json_encode($cursor) . "\n");
        fwrite(STDERR, "second: " . json_encode(collect($second->json('data'))->map(fn($p) => [$p['position'], $p['id'], $p['rating_score']])->all()) . "\n");

        $this->assertSame([6], collect($second->json('data'))->pluck('position')->all());
    }

    public function test_ranking_modes_differ(): void
    {
        // У первого сильный PvP, у второго — BedWars
        $pvpKing = $this->player(['block_placing' => 20, 'rotka' => 20, 'movement' => 20, 'aim' => 20, 'game_sense' => 20]);
        $bwKing = $this->player([], ['pvp' => 20, 'game_sense' => 20, 'bed_play' => 20, 'teamplay' => 20, 'building' => 20]);

        $pvp = $this->getJson('/api/ranking?mode=pvp')->assertOk();
        $this->assertSame($pvpKing->id, $pvp->json('data.0.id'));
        $this->assertSame(100, $pvp->json('data.0.rating_score'));

        $bw = $this->getJson('/api/ranking?mode=bedwars')->assertOk();
        $this->assertSame($bwKing->id, $bw->json('data.0.id'));
        $this->assertSame(100, $bw->json('data.0.rating_score'));

        // В общем режиме баллы обоих режимов складываются.
        // Раньше считалось среднее, но при делении на 2 слабые игроки
        // обнулялись и выпадали из рейтинга.
        $overall = $this->getJson('/api/ranking?mode=overall')->assertOk();
        $this->assertSame(100, $overall->json('data.0.rating_score'));
    }

    /**
     * Игрок со слабыми аспектами обязан оставаться в рейтинге.
     *
     * Регрессия: формула общего рейтинга делила сумму на 2, поэтому
     * игрок с минимальным баллом обнулялся и пропадал из выдачи.
     */
    public function test_weak_players_stay_in_overall_ranking(): void
    {
        $weak = $this->player(['block_placing' => 1, 'rotka' => 0, 'movement' => 0, 'aim' => 0, 'game_sense' => 0]);
        $strong = $this->player(['block_placing' => 20, 'rotka' => 20, 'movement' => 20, 'aim' => 20, 'game_sense' => 20]);

        $overall = $this->getJson('/api/ranking?mode=overall')->assertOk();
        $ids = collect($overall->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($weak->id), 'Слабый игрок должен быть в рейтинге');
        $this->assertTrue($ids->contains($strong->id));

        $this->assertSame($strong->id, $overall->json('data.0.id'));
        $this->assertSame(100, $overall->json('data.0.rating_score'));
    }

    public function test_staff_is_excluded_from_ranking(): void
    {
        $this->player(['block_placing' => 20, 'rotka' => 20, 'movement' => 20, 'aim' => 20, 'game_sense' => 20]);

        $admin = User::factory()->create(['role' => 'admin']);
        PlayerAspectPvp::create([
            'user_id' => $admin->id,
            'block_placing' => 20, 'rotka' => 20, 'movement' => 20, 'aim' => 20, 'game_sense' => 20,
        ]);

        $response = $this->getJson('/api/ranking')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertNotSame($admin->id, $response->json('data.0.id'));
    }

    public function test_search_and_tier_filters_work(): void
    {
        $alpha = $this->player(['block_placing' => 10, 'rotka' => 0, 'movement' => 0, 'aim' => 0, 'game_sense' => 0]);
        $alpha->update(['username' => 'alphatest', 'tier' => 'B']);

        $beta = $this->player(['block_placing' => 8, 'rotka' => 0, 'movement' => 0, 'aim' => 0, 'game_sense' => 0]);
        $beta->update(['username' => 'betatest', 'tier' => 'C']);

        $search = $this->getJson('/api/ranking?search=alpha')->assertOk();
        $this->assertCount(1, $search->json('data'));
        $this->assertSame('alphatest', $search->json('data.0.username'));

        $tier = $this->getJson('/api/ranking?tier=C')->assertOk();
        $this->assertCount(1, $tier->json('data'));
        $this->assertSame('betatest', $tier->json('data.0.username'));
    }
}
