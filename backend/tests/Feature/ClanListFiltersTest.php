<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ClanFixtures;
use Tests\TestCase;

/**
 * Список кланов: поиск, фильтры, сортировки и место в выдаче.
 */
class ClanListFiltersTest extends TestCase
{
    use ClanFixtures, RefreshDatabase;

    private function ids(string $query): array
    {
        return collect($this->getJson('/api/clans'.$query)->assertOk()->json('data'))->pluck('id')->all();
    }

    public function test_sorts_by_wins_winrate_and_average_rating(): void
    {
        $veteran = $this->clan($this->user(), ['wins' => 10, 'losses' => 10, 'power' => 5]);
        $sharp = $this->clan($this->user(['rating_points' => 90]), ['wins' => 4, 'losses' => 0, 'power' => 1]);

        $this->assertSame([$veteran->id, $sharp->id], $this->ids('?sort=wins'));
        $this->assertSame([$sharp->id, $veteran->id], $this->ids('?sort=winrate'));
        $this->assertSame([$sharp->id, $veteran->id], $this->ids('?sort=rating'));

        $first = $this->getJson('/api/clans?sort=rating')->json('data.0');
        $this->assertSame(1, $first['position']);
        $this->assertSame(90, $first['avg_rating']);
    }

    public function test_open_and_free_slot_filters_and_search(): void
    {
        $open = $this->clan($this->user(), ['name' => 'Open Wolves', 'tag' => 'OPW', 'is_open' => true, 'max_members' => 10]);
        $full = $this->clan($this->user(), ['name' => 'Full House', 'tag' => 'FUL', 'is_open' => true, 'max_members' => 1]);
        $closed = $this->clan($this->user(), ['name' => 'Closed Door', 'tag' => 'CLD', 'is_open' => false]);
        $this->clan($this->user(), ['name' => 'Banned', 'tag' => 'BAN', 'is_banned' => true]);

        $this->assertEqualsCanonicalizing([$open->id, $full->id, $closed->id], $this->ids(''));
        $this->assertEqualsCanonicalizing([$open->id, $full->id], $this->ids('?open=1'));
        $this->assertSame([$open->id], $this->ids('?open=1&has_slots=1'));
        $this->assertSame([$closed->id], $this->ids('?search=door'));
        $this->assertSame([$full->id], $this->ids('?search=FUL'));
        // Спецсимволы LIKE ищутся буквально
        $this->assertSame([], $this->ids('?search=%25'));
    }
}
