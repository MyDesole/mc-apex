<?php

namespace Tests\Feature;

use App\Domains\Project\Models\ChangelogEntry;
use App\Domains\Project\Models\RoadmapItem;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChangelogRoadmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_publishes_entry_with_shareable_slug(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $slug = $this->actingAs($admin)->postJson('/api/admin/project/changelog', [
            'title' => 'Макеты профиля',
            'category' => 'feature',
            'version' => '1.4',
            'body' => "Восемь новых макетов профиля в магазине.\nКнига и перо, Терминал и другие.",
        ])->assertCreated()->json('entry.slug');

        // Кириллица транслитерируется — ссылка читается и в Telegram
        $this->assertSame('makety-profilia', $slug);

        $this->getJson('/api/changelog')->assertOk()->assertJsonPath('data.0.slug', $slug);
        $this->getJson("/api/changelog/{$slug}")->assertOk()->assertJsonPath('entry.version', '1.4');

        // Превью для Telegram: og-теги с заголовком поста
        $this->get("/og/changelog/{$slug}")
            ->assertOk()
            ->assertSee('Макеты профиля', false)
            ->assertSee('og:type" content="article', false);
    }

    public function test_drafts_and_future_posts_are_hidden(): void
    {
        ChangelogEntry::create(['title' => 'Черновик', 'slug' => 'draft', 'category' => 'fix', 'body' => 'x', 'is_published' => false]);
        ChangelogEntry::create(['title' => 'Будущее', 'slug' => 'future', 'category' => 'fix', 'body' => 'x', 'published_at' => now()->addDay()]);

        $this->getJson('/api/changelog')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/changelog/draft')->assertNotFound();
        $this->get('/og/changelog/future')->assertNotFound();
    }

    public function test_slug_does_not_change_on_edit_and_is_unique(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = ['title' => 'Фикс чата', 'category' => 'fix', 'body' => 'Починили даты.'];

        $first = $this->actingAs($admin)->postJson('/api/admin/project/changelog', $payload)->json('entry');
        $second = $this->actingAs($admin)->postJson('/api/admin/project/changelog', $payload)->json('entry');
        $this->assertNotSame($first['slug'], $second['slug']);

        $this->actingAs($admin)->putJson("/api/admin/project/changelog/{$first['id']}", [...$payload, 'title' => 'Фикс чата и дат'])
            ->assertOk()
            ->assertJsonPath('entry.slug', $first['slug']);
    }

    public function test_roadmap_has_goals_with_auto_progress(): void
    {
        Cache::flush();
        User::factory()->count(3)->create(['last_seen_at' => now()->subDay()]);
        User::factory()->create(['last_seen_at' => now()->subDays(60)]);

        $items = collect($this->getJson('/api/roadmap')->assertOk()->json('items'));

        $players = $items->firstWhere('metric', 'active_players');
        $this->assertSame('100 активных игроков', $players['title']);
        $this->assertSame(3, $players['progress_current']);
        $this->assertSame(3, $players['progress_percent']);
        $this->assertNotNull($items->firstWhere('title', 'Открыть Minecraft-сервер для всех'));
    }

    public function test_only_admin_edits_roadmap(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator']);
        $admin = User::factory()->create(['role' => 'admin']);
        $item = RoadmapItem::first();

        $this->actingAs($moderator)->putJson("/api/admin/project/roadmap/{$item->id}", ['title' => 'x', 'status' => 'done'])
            ->assertForbidden();

        $this->actingAs($admin)->putJson("/api/admin/project/roadmap/{$item->id}", [
            'title' => $item->title, 'status' => 'done', 'metric' => $item->metric, 'progress_target' => 100,
        ])->assertOk()->assertJsonPath('item.progress_percent', 100);
    }
}
