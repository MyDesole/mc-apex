<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\News;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\User;
use Database\Seeders\SiteSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /api/home и /api/top — публичная главная страница.
 */
class HomeTest extends TestCase
{
    use RefreshDatabase;

    private function clan(string $name, int $power, array $attributes = []): Clan
    {
        static $i = 0;
        $i++;

        return Clan::create(array_merge([
            'name' => $name,
            'tag' => "T{$i}",
            'leader_id' => User::factory()->create()->id,
            'power' => $power,
        ], $attributes));
    }

    private function player(int $score, string $role = 'user', array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'tier_score' => $score,
        ], $attributes));
    }

    public function test_home_payload_has_all_expected_sections(): void
    {
        $this->seed(SiteSettingsSeeder::class);

        $response = $this->getJson('/api/home')->assertOk();

        foreach ([
            'hero', 'socials', 'footer', 'stats', 'stats_data', 'players', 'clans', 'news',
        ] as $key) {
            $this->assertArrayHasKey($key, $response->json());
        }

        $this->assertSame('APEX TIERS', $response->json('hero.hero_title'));
        $this->assertSame('© 2026 APEX TIERS. Все права защищены.', $response->json('footer.footer_text'));
        $this->assertTrue($response->json('stats.stats_show'));
    }

    public function test_home_settings_groups_default_to_empty_arrays(): void
    {
        $response = $this->getJson('/api/home')->assertOk();

        $this->assertSame([], $response->json('hero'));
        $this->assertSame([], $response->json('socials'));
        $this->assertSame([], $response->json('footer'));
        $this->assertSame([], $response->json('stats'));
    }

    public function test_home_stats_data_counts(): void
    {
        User::factory()->count(4)->create();

        $active = $this->clan('Активный', 10);
        $this->clan('Забаненный', 20, ['is_banned' => true]);

        $ongoing = Tournament::create([
            'name' => 'Идёт', 'slug' => 'ongoing', 'type' => 'solo', 'format' => 'single_elim',
            'status' => 'ongoing', 'max_participants' => 8, 'created_by' => User::factory()->create()->id,
        ]);
        Tournament::create([
            'name' => 'Черновик', 'slug' => 'draft', 'type' => 'solo', 'format' => 'single_elim',
            'status' => 'draft', 'max_participants' => 8, 'created_by' => User::factory()->create()->id,
        ]);
        Tournament::create([
            'name' => 'Регистрация', 'slug' => 'reg', 'type' => 'solo', 'format' => 'single_elim',
            'status' => 'registration', 'max_participants' => 8, 'created_by' => User::factory()->create()->id,
        ]);

        TournamentMatch::create([
            'tournament_id' => $ongoing->id, 'round' => 1, 'position' => 0, 'status' => 'completed',
        ]);
        TournamentMatch::create([
            'tournament_id' => $ongoing->id, 'round' => 1, 'position' => 1, 'status' => 'pending',
        ]);

        $response = $this->getJson('/api/home')->assertOk();

        // 4 игрока + 2 лидера кланов + 3 создателя турниров
        $this->assertSame(9, $response->json('stats_data.players'));
        $this->assertSame(1, $response->json('stats_data.clans'));
        $this->assertSame(2, $response->json('stats_data.tournaments'));
        $this->assertSame(1, $response->json('stats_data.matches'));

        $this->assertSame($active->id, $response->json('clans.0.id'));
    }

    public function test_home_players_exclude_staff_and_sort_by_score(): void
    {
        $media = $this->player(90, 'media', ['username' => 'media_king']);
        $best = $this->player(95, 'user', ['username' => 'best_player']);
        $this->player(100, 'admin', ['username' => 'admin_player']);
        $this->player(100, 'moderator', ['username' => 'mod_player']);
        $this->player(100, 'tester', ['username' => 'tester_player']);

        $response = $this->getJson('/api/home')->assertOk();

        $usernames = collect($response->json('players'))->pluck('username')->all();

        $this->assertSame(['best_player', 'media_king'], $usernames);
        $this->assertSame($best->id, $response->json('players.0.id'));
        $this->assertSame(95.0, (float) $response->json('players.0.tier_score'));
        $this->assertSame($media->id, $response->json('players.1.id'));

        // Поля карточки игрока
        foreach (['id', 'username', 'avatar_url', 'tier', 'tier_score', 'clan_tag', 'clan_color'] as $key) {
            $this->assertArrayHasKey($key, $response->json('players.0'));
        }
    }

    public function test_home_players_show_clan_tag_and_color(): void
    {
        $player = $this->player(50, 'user');
        $clan = $this->clan('Клан игрока', 5, ['banner_color' => '#abcdef']);

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $player->id,
            'role' => 'member',
        ]);

        $response = $this->getJson('/api/home')->assertOk();

        $this->assertSame($clan->tag, $response->json('players.0.clan_tag'));
        $this->assertSame('#abcdef', $response->json('players.0.clan_color'));
    }

    public function test_home_players_limited_to_ten(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->player($i);
        }

        $response = $this->getJson('/api/home')->assertOk();

        $this->assertCount(10, $response->json('players'));
        $this->assertSame(12.0, (float) $response->json('players.0.tier_score'));
    }

    public function test_home_clans_exclude_banned_sort_by_power_and_count_members(): void
    {
        $strong = $this->clan('Сильный', 500);
        $weak = $this->clan('Слабый', 100);
        $this->clan('Бан', 900, ['is_banned' => true]);

        ClanMember::create(['clan_id' => $strong->id, 'user_id' => $strong->leader_id, 'role' => 'leader']);
        ClanMember::create(['clan_id' => $strong->id, 'user_id' => User::factory()->create()->id, 'role' => 'member']);

        $response = $this->getJson('/api/home')->assertOk();

        $this->assertSame([$strong->id, $weak->id], collect($response->json('clans'))->pluck('id')->all());
        $this->assertSame(500, $response->json('clans.0.power'));
        $this->assertSame(2, $response->json('clans.0.members_count'));
        $this->assertArrayHasKey('leader', $response->json('clans.0'));
        $this->assertArrayHasKey('banner_color', $response->json('clans.0'));
    }

    public function test_home_news_are_published_pinned_first_and_limited_to_six(): void
    {
        $author = User::factory()->create();

        $pinned = News::create([
            'title' => 'Закреплённая', 'slug' => 'pinned', 'type' => 'news',
            'is_pinned' => true, 'is_published' => true, 'author_id' => $author->id,
            'published_at' => now()->subDays(10),
        ]);

        for ($i = 1; $i <= 6; $i++) {
            News::create([
                'title' => "Новость {$i}", 'slug' => "n{$i}", 'type' => 'news',
                'is_published' => true, 'author_id' => $author->id,
                'published_at' => now()->subDays($i),
            ]);
        }

        News::create([
            'title' => 'Черновик', 'slug' => 'draft', 'type' => 'news',
            'is_published' => false, 'author_id' => $author->id,
            'published_at' => now()->subDay(),
        ]);

        News::create([
            'title' => 'Будущая', 'slug' => 'future', 'type' => 'news',
            'is_published' => true, 'author_id' => $author->id,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/home')->assertOk();

        $titles = collect($response->json('news'))->pluck('title')->all();

        $this->assertCount(6, $titles);
        $this->assertSame('Закреплённая', $titles[0]);
        $this->assertNotContains('Черновик', $titles);
        $this->assertNotContains('Будущая', $titles);
    }

    /* --------------------------------- /top ------------------------------ */

    public function test_top_payload_shape_and_staff_exclusion(): void
    {
        $this->player(80, 'user', ['username' => 'top_player']);
        $this->player(99, 'admin', ['username' => 'admin_player']);
        $this->player(98, 'moderator');
        $this->player(97, 'tester');
        $this->player(96, 'media', ['username' => 'media_player']);

        $clan = $this->clan('Топ-клан', 300);

        $response = $this->getJson('/api/top')->assertOk();

        $this->assertArrayHasKey('players', $response->json());
        $this->assertArrayHasKey('clans', $response->json());

        $usernames = collect($response->json('players'))->pluck('username')->all();

        // Персонал исключён, медийка и игроки — по убыванию tier_score
        $this->assertSame(['media_player', 'top_player'], array_slice($usernames, 0, 2));
        $this->assertNotContains('admin_player', $usernames);

        $this->assertSame($clan->id, $response->json('clans.0.id'));
        $this->assertSame('Топ-клан', $response->json('clans.0.name'));
        $this->assertArrayHasKey('is_highlighted', $response->json('clans.0'));

        foreach (['id', 'username', 'avatar_url', 'tier', 'tier_score', 'clan_tag', 'clan_color'] as $key) {
            $this->assertArrayHasKey($key, $response->json('players.0'));
        }
    }

    /**
     * Исправлено: /api/home фильтровал забаненные кланы, а /api/top — нет,
     * поэтому забаненный клан висел в публичном топе.
     */
    public function test_banned_clans_are_hidden_from_both_home_and_top(): void
    {
        $banned = $this->clan('Забаненный топ', 1000, ['is_banned' => true]);
        $this->clan('Обычный', 10);

        $top = $this->getJson('/api/top')->assertOk();
        $home = $this->getJson('/api/home')->assertOk();

        $this->assertNotContains($banned->id, collect($top->json('clans'))->pluck('id')->all());
        $this->assertNotContains($banned->id, collect($home->json('clans'))->pluck('id')->all());
    }

    public function test_top_limits_to_ten(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->player($i);
        }

        for ($i = 1; $i <= 12; $i++) {
            $this->clan("Клан {$i}", $i);
        }

        $response = $this->getJson('/api/top')->assertOk();

        $this->assertCount(10, $response->json('players'));
        $this->assertCount(10, $response->json('clans'));
    }
}
