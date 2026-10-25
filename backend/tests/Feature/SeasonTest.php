<?php

namespace Tests\Feature;

use App\Domains\Seasons\Models\Season;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Сезоны: закрытие сохраняет места, не трогая тиры.
 */
class SeasonTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_season_is_created_on_demand(): void
    {
        $this->getJson('/api/seasons')
            ->assertOk()
            ->assertJsonPath('current.number', 1)
            ->assertJsonPath('current.active', true)
            ->assertJsonCount(0, 'past');
    }

    public function test_closing_snapshots_ranking_and_opens_next_season(): void
    {
        $first = User::factory()->create(['tier' => 'A', 'tier_score' => 80]);
        $second = User::factory()->create(['tier' => 'C', 'tier_score' => 45]);
        User::factory()->create(['tier' => 'E', 'tier_score' => 0]);
        User::factory()->create(['role' => 'admin', 'tier' => 'A', 'tier_score' => 99]);

        $this->artisan('apex:season-close', ['--force' => true, '--name' => 'Зимний сезон'])
            ->assertSuccessful();

        $this->assertSame('A', $first->fresh()->tier);
        $this->assertSame(2, Season::count());

        $this->getJson('/api/seasons')
            ->assertOk()
            ->assertJsonPath('current.name', 'Зимний сезон')
            ->assertJsonPath('past.0.top.0.user.id', $first->id)
            ->assertJsonCount(2, 'past.0.top');

        $this->getJson("/api/players/{$second->id}/seasons")
            ->assertOk()
            ->assertJsonPath('results.0.rank', 2)
            ->assertJsonPath('results.0.tier', 'C')
            ->assertJsonPath('results.0.players', 2);
    }
}
