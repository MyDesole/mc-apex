<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\Clan;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Поиск для командной палитры (Ctrl+K).
 */
class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_query_returns_empty_groups(): void
    {
        User::factory()->create(['username' => 'desole']);

        $this->getJson('/api/search?q=d')
            ->assertOk()
            ->assertExactJson(['players' => [], 'clans' => [], 'topics' => [], 'news' => []]);
    }

    public function test_finds_players_and_prefers_prefix_matches(): void
    {
        User::factory()->create(['username' => 'ultradesole', 'tier_score' => 90]);
        User::factory()->create(['username' => 'desole', 'tier_score' => 10]);

        $players = $this->getJson('/api/search?q=des')
            ->assertOk()
            ->json('players');

        $this->assertSame(['desole', 'ultradesole'], array_column($players, 'username'));
    }

    public function test_finds_player_by_minecraft_nickname(): void
    {
        User::factory()->create(['username' => 'site_user', 'minecraft_username' => 'Notch']);

        $this->getJson('/api/search?q=notch')
            ->assertOk()
            ->assertJsonPath('players.0.username', 'site_user');
    }

    public function test_finds_clans_by_tag_and_hides_banned(): void
    {
        $leader = User::factory()->create();

        Clan::create(['name' => 'Team Apex', 'tag' => 'APX', 'leader_id' => $leader->id]);
        Clan::create(['name' => 'Apex Banned', 'tag' => 'BAN', 'leader_id' => $leader->id, 'is_banned' => true]);

        $clans = $this->getJson('/api/search?q=apx')->assertOk()->json('clans');

        $this->assertCount(1, $clans);
        $this->assertSame('Team Apex', $clans[0]['name']);

        $this->assertSame(
            ['Team Apex'],
            array_column($this->getJson('/api/search?q=apex')->json('clans'), 'name')
        );
    }

    public function test_like_wildcards_in_query_are_literal(): void
    {
        User::factory()->create(['username' => 'abc_def']);
        User::factory()->create(['username' => 'abcXdef']);

        $players = $this->getJson('/api/search?q=' . urlencode('c_d'))->json('players');

        $this->assertSame(['abc_def'], array_column($players, 'username'));
    }

    public function test_cyrillic_search_ignores_case(): void
    {
        $leader = User::factory()->create();

        Clan::create(['name' => 'Северный Ветер', 'tag' => 'SEV', 'leader_id' => $leader->id]);

        $this->assertSame(
            ['Северный Ветер'],
            array_column($this->getJson('/api/search?q=' . urlencode('северный'))->json('clans'), 'name')
        );
    }
}
