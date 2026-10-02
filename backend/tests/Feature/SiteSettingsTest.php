<?php

namespace Tests\Feature;

use App\Domains\Core\Models\SiteSetting;
use App\Domains\Users\Models\User;
use Database\Seeders\SiteSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_access_is_admin_only(): void
    {
        $this->getJson('/api/admin/site-settings')->assertUnauthorized();
        $this->putJson('/api/admin/site-settings', [])->assertUnauthorized();

        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/admin/site-settings')->assertForbidden();
        $this->actingAs($user)->putJson('/api/admin/site-settings', ['hero' => []])->assertForbidden();

        $moderator = User::factory()->create(['role' => 'moderator']);
        $this->actingAs($moderator)->getJson('/api/admin/site-settings')->assertForbidden();
        $this->actingAs($moderator)->putJson('/api/admin/site-settings', ['hero' => []])->assertForbidden();

        $this->actingAs($this->admin())->getJson('/api/admin/site-settings')->assertOk();
    }

    public function test_index_returns_four_groups(): void
    {
        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/site-settings')
            ->assertOk();

        $this->assertSame(['hero', 'socials', 'footer', 'stats'], array_keys($response->json()));
    }

    public function test_index_returns_seeded_values_with_types(): void
    {
        $this->seed(SiteSettingsSeeder::class);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/site-settings')
            ->assertOk();

        $this->assertSame('APEX TIERS', $response->json('hero.hero_title'));
        $this->assertSame('/players', $response->json('hero.hero_primary_url'));
        $this->assertSame('', $response->json('socials.social_discord'));
        $this->assertTrue($response->json('stats.stats_show'));
    }

    public function test_update_writes_settings_and_casts_types(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson('/api/admin/site-settings', [
                'hero' => [
                    'hero_title' => 'НОВЫЙ ЗАГОЛОВОК',
                    'hero_priority' => 7,
                ],
                'stats' => [
                    'stats_show' => false,
                ],
                'footer' => [
                    'footer_links' => ['discord' => 'https://discord.gg/x'],
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('site_settings', [
            'key' => 'hero_title',
            'value' => 'НОВЫЙ ЗАГОЛОВОК',
            'type' => 'string',
            'group' => 'hero',
        ]);

        $this->assertDatabaseHas('site_settings', [
            'key' => 'hero_priority',
            'value' => '7',
            'type' => 'int',
            'group' => 'hero',
        ]);

        $this->assertDatabaseHas('site_settings', [
            'key' => 'stats_show',
            'value' => '0',
            'type' => 'bool',
            'group' => 'stats',
        ]);

        $this->assertDatabaseHas('site_settings', [
            'key' => 'footer_links',
            'type' => 'json',
            'group' => 'footer',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/site-settings')
            ->assertOk();

        $this->assertSame('НОВЫЙ ЗАГОЛОВОК', $response->json('hero.hero_title'));
        $this->assertSame(7, $response->json('hero.hero_priority'));
        $this->assertFalse($response->json('stats.stats_show'));
        $this->assertSame(['discord' => 'https://discord.gg/x'], $response->json('footer.footer_links'));
    }

    public function test_update_overwrites_existing_key(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson('/api/admin/site-settings', ['hero' => ['hero_title' => 'Первый']])
            ->assertOk();

        $this->actingAs($admin)
            ->putJson('/api/admin/site-settings', ['hero' => ['hero_title' => 'Второй']])
            ->assertOk();

        $this->assertSame(1, SiteSetting::where('key', 'hero_title')->count());
        $this->assertSame('Второй', SiteSetting::where('key', 'hero_title')->value('value'));
    }

    public function test_update_validates_groups_are_arrays(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson('/api/admin/site-settings', ['hero' => 'не массив'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('hero');

        $this->actingAs($admin)
            ->putJson('/api/admin/site-settings', ['unknown_group' => ['a' => 1]])
            ->assertOk();
    }

    public function test_update_with_empty_group_is_noop(): void
    {
        $this->seed(SiteSettingsSeeder::class);
        $before = SiteSetting::count();

        $this->actingAs($this->admin())
            ->putJson('/api/admin/site-settings', ['hero' => [], 'socials' => []])
            ->assertOk();

        $this->assertSame($before, SiteSetting::count());
    }

    public function test_settings_are_visible_on_public_home(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/admin/site-settings', [
                'hero' => ['hero_title' => 'Публичный заголовок'],
                'stats' => ['stats_show' => true],
            ])
            ->assertOk();

        $home = $this->getJson('/api/home')->assertOk();

        $this->assertSame('Публичный заголовок', $home->json('hero.hero_title'));
        $this->assertTrue($home->json('stats.stats_show'));
    }

    public function test_same_key_in_another_group_moves_the_setting(): void
    {
        // Документируем текущее поведение: ключ в site_settings уникален глобально,
        // поэтому запись того же ключа в другую группу переносит настройку
        // и она исчезает из старой группы.
        $this->seed(SiteSettingsSeeder::class);

        $this->assertArrayHasKey('social_discord', $this->getJson('/api/home')->json('socials'));

        $this->actingAs($this->admin())
            ->putJson('/api/admin/site-settings', [
                'hero' => ['social_discord' => 'https://discord.gg/apex'],
            ])
            ->assertOk();

        $home = $this->getJson('/api/home')->assertOk();

        $this->assertArrayNotHasKey('social_discord', $home->json('socials'));
        $this->assertSame('https://discord.gg/apex', $home->json('hero.social_discord'));
        $this->assertSame(1, SiteSetting::where('key', 'social_discord')->count());
    }

    public function test_string_false_is_stored_as_truthy_string(): void
    {
        // Документируем текущее поведение: тип определяется по PHP-типу значения,
        // поэтому строка "false" остаётся строкой (в отличие от JSON-булева false).
        $this->actingAs($this->admin())
            ->putJson('/api/admin/site-settings', [
                'stats' => ['stats_show' => 'false'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('site_settings', [
            'key' => 'stats_show',
            'value' => 'false',
            'type' => 'string',
        ]);

        $this->assertSame('false', $this->getJson('/api/home')->json('stats.stats_show'));
    }
}
