<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function news(array $attributes = []): News
    {
        return News::create(array_merge([
            'title' => 'Новость',
            'slug' => 'news-' . uniqid(),
            'excerpt' => 'Кратко',
            'body' => 'Полный текст',
            'type' => 'news',
            'is_pinned' => false,
            'is_published' => true,
            'author_id' => User::factory()->create()->id,
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    /* ------------------------------ Публично ---------------------------- */

    public function test_public_index_returns_only_published_and_available_news(): void
    {
        $old = $this->news(['title' => 'Старая', 'published_at' => now()->subDays(3)]);
        $pinned = $this->news(['title' => 'Закреплённая', 'is_pinned' => true, 'published_at' => now()->subDays(5)]);
        $fresh = $this->news(['title' => 'Свежая', 'published_at' => now()->subDay()]);

        $draft = $this->news(['title' => 'Черновик', 'is_published' => false]);
        $future = $this->news(['title' => 'Будущая', 'published_at' => now()->addDay()]);

        $response = $this->getJson('/api/news')->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        // Закреплённые идут первыми, дальше по published_at desc
        $this->assertSame([$pinned->id, $fresh->id, $old->id], $ids);
        $this->assertNotContains($draft->id, $ids);
        $this->assertNotContains($future->id, $ids);
        $this->assertSame(12, $response->json('per_page'));
        $this->assertArrayHasKey('author', $response->json('data.0'));
    }

    public function test_public_index_keeps_news_without_published_at(): void
    {
        $noDate = $this->news(['title' => 'Без даты', 'published_at' => null]);

        $response = $this->getJson('/api/news')->assertOk();

        $this->assertContains($noDate->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_public_show_returns_prev_and_next(): void
    {
        $first = $this->news(['title' => 'Первая', 'published_at' => now()->subDays(3)]);
        $middle = $this->news(['title' => 'Средняя', 'published_at' => now()->subDays(2)]);
        $last = $this->news(['title' => 'Последняя', 'published_at' => now()->subDay()]);

        $response = $this->getJson("/api/news/{$middle->id}")->assertOk();

        $this->assertSame($middle->id, $response->json('news.id'));
        $this->assertSame($first->id, $response->json('prev.id'));
        $this->assertSame($last->id, $response->json('next.id'));
        $this->assertArrayHasKey('title', $response->json('prev'));

        // У первой нет prev, у последней нет next
        $this->assertNull($this->getJson("/api/news/{$first->id}")->json('prev'));
        $this->assertNull($this->getJson("/api/news/{$last->id}")->json('next'));
    }

    public function test_unknown_news_returns_404(): void
    {
        $this->getJson('/api/news/999999')->assertNotFound();
    }

    public function test_unpublished_news_is_hidden_from_guests_but_visible_to_admin(): void
    {
        $draft = $this->news(['is_published' => false]);

        $this->getJson("/api/news/{$draft->id}")->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/news/{$draft->id}")
            ->assertNotFound();

        $this->actingAs($this->admin())
            ->getJson("/api/news/{$draft->id}")
            ->assertOk()
            ->assertJsonPath('news.id', $draft->id);

        // Модератор тоже не админ по этому условию (isAdmin())
        $this->actingAs(User::factory()->create(['role' => 'moderator']))
            ->getJson("/api/news/{$draft->id}")
            ->assertNotFound();
    }

    public function test_scheduled_news_is_reachable_by_direct_link(): void
    {
        // Документируем текущее поведение: show проверяет только is_published,
        // поэтому новость с published_at в будущем доступна по прямой ссылке.
        $future = $this->news(['published_at' => now()->addWeek()]);

        $this->getJson("/api/news/{$future->id}")
            ->assertOk()
            ->assertJsonPath('news.id', $future->id);
    }

    /* -------------------------------- Админ ----------------------------- */

    public function test_admin_index_includes_unpublished(): void
    {
        $published = $this->news();
        $draft = $this->news(['is_published' => false]);
        $pinned = $this->news(['is_pinned' => true]);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/news')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame(20, $response->json('per_page'));
        $this->assertCount(3, $ids);
        $this->assertContains($draft->id, $ids);
        $this->assertSame($pinned->id, $ids[0]);
        $this->assertContains($published->id, $ids);
    }

    public function test_admin_store_creates_news_with_defaults(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/news', [
                'title' => 'Большое обновление',
                'excerpt' => 'Что нового',
                'body' => 'Подробности',
                'type' => 'update',
                'is_pinned' => true,
                'is_published' => true,
            ])
            ->assertCreated();

        $this->assertSame('Большое обновление', $response->json('news.title'));
        $this->assertSame($admin->id, $response->json('news.author_id'));
        $this->assertTrue($response->json('news.is_pinned'));
        $this->assertNotNull($response->json('news.published_at'));
        // Str::slug транслитерирует кириллицу (потери вроде «ш» → «s» — текущее поведение),
        // а Str::random(5) в суффиксе даёт заглавные буквы — итоговый slug не полностью lowercase.
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+-[A-Za-z0-9]{5}$/', $response->json('news.slug'));
        $this->assertStringContainsString('obnovlenie-', $response->json('news.slug'));
    }

    public function test_admin_store_validation(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/admin/news', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'type']);

        $this->actingAs($admin)
            ->postJson('/api/admin/news', ['title' => 'X', 'type' => 'gossip'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');

        $this->actingAs($admin)
            ->postJson('/api/admin/news', ['title' => str_repeat('a', 121), 'type' => 'news'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    public function test_admin_store_uploads_cover(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())
            ->post('/api/admin/news', [
                'title' => 'С картинкой',
                'type' => 'event',
                'cover' => UploadedFile::fake()->image('cover.jpg', 600, 400),
            ])
            ->assertCreated();

        $cover = $response->json('news.cover');

        $this->assertNotNull($cover);
        Storage::disk('public')->assertExists($cover);
    }

    public function test_admin_update_via_put_and_post(): void
    {
        $news = $this->news(['title' => 'Старый заголовок']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson("/api/admin/news/{$news->id}", ['title' => 'Новый заголовок'])
            ->assertOk();

        $this->assertSame('Новый заголовок', $news->fresh()->title);

        // Slug не меняется вместе с заголовком
        $this->assertStringStartsWith('news-', $news->fresh()->slug);

        $this->actingAs($admin)
            ->postJson("/api/admin/news/{$news->id}", ['is_published' => false])
            ->assertOk();

        $this->assertFalse($news->fresh()->is_published);

        // Скрытая новость исчезает из публичного списка
        $this->assertNotContains(
            $news->id,
            collect($this->getJson('/api/news')->json('data'))->pluck('id')->all()
        );
    }

    public function test_admin_update_validation(): void
    {
        $news = $this->news();

        $this->actingAs($this->admin())
            ->putJson("/api/admin/news/{$news->id}", ['type' => 'gossip'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_admin_destroy_deletes_news_and_cover(): void
    {
        Storage::fake('public');

        $news = $this->news();
        Storage::disk('public')->put($news->cover ?? 'news/cover.jpg', 'x');
        $news->update(['cover' => 'news/cover.jpg']);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/news/{$news->id}")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('news', ['id' => $news->id]);
        Storage::disk('public')->assertMissing('news/cover.jpg');
    }
}
