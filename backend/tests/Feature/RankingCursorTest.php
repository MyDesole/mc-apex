<?php

namespace Tests\Feature;

use App\Domains\Players\Models\PlayerAspectBedwars;
use App\Domains\Players\Models\PlayerAspectPvp;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Курсорная пагинация рейтинга.
 *
 * Баг: фронтенд вызывал api.get(url, { params }) , но слой запросов
 * второй аргумент игнорировал, поэтому ни mode, ни cursor до сервера
 * не доходили: вторая страница повторяла первую, а переключатель
 * режима не влиял на выборку.
 *
 * Здесь фиксируется, что курсор принимается в обоих видах:
 *   - курсор как объект: cursor[score]=..&cursor[id]=..&cursor[offset]=..
 *   - плоские ключи: cursor_score=..&cursor_id=..&offset=..
 */
class RankingCursorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Создаёт игрока с указанной суммой аспектов PvP (баллы в block_placing).
     */
    private function pvpPlayer(int $score): User
    {
        $user = User::factory()->create();

        PlayerAspectPvp::create([
            'user_id' => $user->id,
            'block_placing' => $score,
            'rotka' => 0,
            'movement' => 0,
            'aim' => 0,
            'game_sense' => 0,
        ]);

        return $user;
    }

    private function bedwarsPlayer(int $score): User
    {
        $user = User::factory()->create();

        PlayerAspectBedwars::create([
            'user_id' => $user->id,
            'pvp' => $score,
            'game_sense' => 0,
            'bed_play' => 0,
            'teamplay' => 0,
            'building' => 0,
        ]);

        return $user;
    }

    /* ---------------------- Курсор объектом ---------------------- */

    public function test_cursor_as_object_does_not_repeat_first_page(): void
    {
        foreach (range(1, 7) as $i) {
            $this->pvpPlayer($i);
        }

        $first = $this->getJson('/api/ranking?limit=5')->assertOk();

        $this->assertCount(5, $first->json('data'));
        $cursor = $first->json('next_cursor');
        $this->assertNotNull($cursor);

        $second = $this->getJson(
            "/api/ranking?limit=5"
            . "&cursor[score]={$cursor['score']}"
            . "&cursor[id]={$cursor['id']}"
            . "&cursor[offset]={$cursor['offset']}"
        )->assertOk();

        $firstIds = collect($first->json('data'))->pluck('id');
        $secondIds = collect($second->json('data'))->pluck('id');

        $this->assertEmpty(
            $firstIds->intersect($secondIds),
            'Вторая страница не должна повторять первую'
        );

        // Суммарно все семь игроков, без дублей
        $this->assertCount(7, $firstIds->merge($secondIds)->unique());
    }

    public function test_positions_continue_on_second_page_with_object_cursor(): void
    {
        foreach (range(1, 6) as $i) {
            $this->pvpPlayer($i);
        }

        $first = $this->getJson('/api/ranking?limit=5')->assertOk();
        $this->assertSame([1, 2, 3, 4, 5], collect($first->json('data'))->pluck('position')->all());

        $cursor = $first->json('next_cursor');

        $second = $this->getJson(
            "/api/ranking?limit=5"
            . "&cursor[score]={$cursor['score']}"
            . "&cursor[id]={$cursor['id']}"
            . "&cursor[offset]={$cursor['offset']}"
        )->assertOk();

        $this->assertSame([6], collect($second->json('data'))->pluck('position')->all());
        $this->assertFalse($second->json('has_more'));
    }

    /* ---------------------- Плоские ключи ---------------------- */

    public function test_flat_cursor_keys_are_supported(): void
    {
        foreach (range(1, 7) as $i) {
            $this->pvpPlayer($i);
        }

        $first = $this->getJson('/api/ranking?limit=5')->assertOk();
        $cursor = $first->json('next_cursor');

        $second = $this->getJson(
            "/api/ranking?limit=5"
            . "&cursor_score={$cursor['score']}"
            . "&cursor_id={$cursor['id']}"
            . "&offset={$cursor['offset']}"
        )->assertOk();

        $this->assertCount(2, $second->json('data'));

        $firstIds = collect($first->json('data'))->pluck('id');
        $secondIds = collect($second->json('data'))->pluck('id');

        $this->assertEmpty($firstIds->intersect($secondIds));
    }

    /* ---------------------- Режимы ---------------------- */

    public function test_mode_parameter_switches_ranking(): void
    {
        $pvpKing = $this->pvpPlayer(50);
        $bedwarsKing = $this->bedwarsPlayer(50);

        // Общий режим: оба в списке
        $overall = $this->getJson('/api/ranking?mode=overall')->assertOk();
        $overallIds = collect($overall->json('data'))->pluck('id');

        $this->assertTrue($overallIds->contains($pvpKing->id));
        $this->assertTrue($overallIds->contains($bedwarsKing->id));

        // Только PvP: игрок BedWars не должен попасть
        $pvp = $this->getJson('/api/ranking?mode=pvp')->assertOk();
        $pvpIds = collect($pvp->json('data'))->pluck('id');

        $this->assertTrue($pvpIds->contains($pvpKing->id));
        $this->assertFalse($pvpIds->contains($bedwarsKing->id));
        $this->assertSame('pvp', $pvp->json('mode'));

        // Только BedWars: наоборот
        $bedwars = $this->getJson('/api/ranking?mode=bedwars')->assertOk();
        $bedwarsIds = collect($bedwars->json('data'))->pluck('id');

        $this->assertTrue($bedwarsIds->contains($bedwarsKing->id));
        $this->assertFalse($bedwarsIds->contains($pvpKing->id));
        $this->assertSame('bedwars', $bedwars->json('mode'));
    }

    public function test_limit_parameter_is_respected(): void
    {
        foreach (range(1, 10) as $i) {
            $this->pvpPlayer($i);
        }

        $this->assertCount(3, $this->getJson('/api/ranking?limit=3')->assertOk()->json('data'));
        $this->assertCount(7, $this->getJson('/api/ranking?limit=7')->assertOk()->json('data'));
    }

    public function test_search_parameter_filters_ranking(): void
    {
        $alice = $this->pvpPlayer(10);
        $alice->update(['username' => 'AliceWonder']);
        $this->pvpPlayer(9);

        $data = $this->getJson('/api/ranking?search=Alice')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('AliceWonder', $data[0]['username']);
    }

    public function test_ties_are_paginated_stably(): void
    {
        // Пять игроков с одинаковым баллом: страницы не должны пересекаться
        foreach (range(1, 5) as $i) {
            $this->pvpPlayer(10);
        }

        $first = $this->getJson('/api/ranking?limit=3')->assertOk();
        $cursor = $first->json('next_cursor');

        $second = $this->getJson(
            "/api/ranking?limit=3"
            . "&cursor[score]={$cursor['score']}"
            . "&cursor[id]={$cursor['id']}"
            . "&cursor[offset]={$cursor['offset']}"
        )->assertOk();

        $firstIds = collect($first->json('data'))->pluck('id');
        $secondIds = collect($second->json('data'))->pluck('id');

        $this->assertEmpty(
            $firstIds->intersect($secondIds),
            'При равных баллах страницы не должны пересекаться'
        );
        $this->assertCount(5, $firstIds->merge($secondIds)->unique());
    }
}
