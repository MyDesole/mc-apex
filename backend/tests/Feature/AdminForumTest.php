<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminForumTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function category(array $attributes = []): ForumCategory
    {
        return ForumCategory::create(array_merge([
            'slug' => 'general-' . uniqid(),
            'name' => 'Общее',
            'description' => 'Свободное общение',
            'sort_order' => 10,
            'post_policy' => 'all',
            'is_active' => true,
        ], $attributes));
    }

    private function topic(?ForumCategory $category = null, array $attributes = []): ForumTopic
    {
        return ForumTopic::create(array_merge([
            'category_id' => ($category ?? $this->category())->id,
            'author_id' => User::factory()->create()->id,
            'title' => 'Тема',
            'body' => 'Тело темы',
        ], $attributes));
    }

    /* ------------------------------ Разделы ----------------------------- */

    public function test_categories_endpoint_lists_ordered_categories_and_policies(): void
    {
        $second = $this->category(['sort_order' => 20, 'name' => 'Второй']);
        $first = $this->category(['sort_order' => 5, 'name' => 'Первый']);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/forum/categories')
            ->assertOk();

        $this->assertSame(
            [$first->id, $second->id],
            collect($response->json('categories'))->pluck('id')->all()
        );

        $this->assertSame(
            [
                ['value' => 'all', 'label' => 'Все игроки'],
                ['value' => 'verified', 'label' => 'Только верифицированные'],
                ['value' => 'staff', 'label' => 'Только персонал'],
            ],
            $response->json('policies')
        );
    }

    public function test_store_category_generates_slug_and_defaults(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson('/api/admin/forum/categories', [
                'name' => 'Медиа',
                'description' => 'Скриншоты и клипы',
                'icon' => 'frame',
                'color' => '#22c55e',
                'post_policy' => 'verified',
                'sort_order' => 77,
            ])
            ->assertCreated();

        $this->assertSame('media', $response->json('category.slug'));
        $this->assertSame(77, $response->json('category.sort_order'));
        $this->assertSame('verified', $response->json('category.post_policy'));

        $this->assertDatabaseHas('forum_categories', [
            'id' => $response->json('category.id'),
            'slug' => 'media',
        ]);
    }

    public function test_store_category_validation(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/admin/forum/categories', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->actingAs($admin)
            ->postJson('/api/admin/forum/categories', ['name' => 'X'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->actingAs($admin)
            ->postJson('/api/admin/forum/categories', [
                'name' => 'Ок имя',
                'post_policy' => 'nobody',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('post_policy');

        $this->actingAs($admin)
            ->postJson('/api/admin/forum/categories', [
                'name' => 'Ок имя',
                'sort_order' => 10000,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort_order');
    }

    public function test_store_category_rejects_duplicate_slug(): void
    {
        $this->category(['slug' => 'taken']);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/forum/categories', [
                'name' => 'Дубль',
                'slug' => 'taken',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_update_category_allows_own_slug_and_rejects_foreign(): void
    {
        $category = $this->category(['slug' => 'mine']);
        $other = $this->category(['slug' => 'other']);

        $response = $this->actingAs($this->admin())
            ->putJson("/api/admin/forum/categories/{$category->id}", [
                'name' => 'Новое имя',
                'slug' => 'mine',
            ])
            ->assertOk();

        $this->assertSame('Новое имя', $response->json('category.name'));
        $this->assertSame('mine', $response->json('category.slug'));

        $this->actingAs($this->admin())
            ->putJson("/api/admin/forum/categories/{$category->id}", ['slug' => 'other'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        // name не обязателен при апдейте
        $this->actingAs($this->admin())
            ->putJson("/api/admin/forum/categories/{$category->id}", ['is_active' => false])
            ->assertOk();

        $this->assertFalse($category->fresh()->is_active);
        $this->assertSame($other->id, $other->fresh()->id);
    }

    public function test_destroy_category_with_topics_needs_force(): void
    {
        $category = $this->category();
        $this->topic($category);
        $this->topic($category);

        $response = $this->actingAs($this->admin())
            ->deleteJson("/api/admin/forum/categories/{$category->id}")
            ->assertStatus(409);

        $this->assertSame(2, $response->json('topics_count'));
        $this->assertStringContainsString('force=1', $response->json('message'));
        $this->assertDatabaseHas('forum_categories', ['id' => $category->id]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/forum/categories/{$category->id}?force=1")
            ->assertOk();

        $this->assertDatabaseMissing('forum_categories', ['id' => $category->id]);
        $this->assertSame(0, ForumTopic::where('category_id', $category->id)->count());
    }

    public function test_destroy_empty_category_does_not_need_force(): void
    {
        $category = $this->category();

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/forum/categories/{$category->id}")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('forum_categories', ['id' => $category->id]);
    }

    /* -------------------------------- Темы ------------------------------ */

    public function test_topics_endpoint_filters(): void
    {
        $category = $this->category();
        $other = $this->category();

        $wanted = $this->topic($category, ['title' => 'Ищем ротку']);
        $this->topic($other, ['title' => 'Другое']);
        $deleted = $this->topic($category, ['title' => 'Удалённая ротка', 'deleted_at' => now()]);
        $this->topic($category, ['title' => 'Иное']);

        $all = $this->actingAs($this->admin())->getJson('/api/admin/forum/topics')->assertOk();
        $this->assertSame(30, $all->json('per_page'));
        $this->assertCount(4, $all->json('data'));

        $byCategory = $this->actingAs($this->admin())
            ->getJson("/api/admin/forum/topics?category_id={$category->id}")
            ->assertOk();
        $this->assertCount(3, $byCategory->json('data'));

        $deletedOnly = $this->actingAs($this->admin())
            ->getJson('/api/admin/forum/topics?only_deleted=1')
            ->assertOk();
        $this->assertSame([$deleted->id], collect($deletedOnly->json('data'))->pluck('id')->all());

        // Поиск идёт и по удалённым темам
        $search = $this->actingAs($this->admin())
            ->getJson('/api/admin/forum/topics?search=ротк')
            ->assertOk();
        $this->assertEqualsCanonicalizing(
            [$deleted->id, $wanted->id],
            collect($search->json('data'))->pluck('id')->all()
        );
    }

    public function test_topics_endpoint_includes_relations(): void
    {
        $topic = $this->topic();

        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/forum/topics')
            ->assertOk();

        $this->assertSame($topic->id, $response->json('data.0.id'));
        $this->assertArrayHasKey('author', $response->json('data.0'));
        $this->assertArrayHasKey('category', $response->json('data.0'));
    }

    public function test_pin_and_lock_toggle(): void
    {
        $topic = $this->topic();
        $admin = $this->admin();

        $this->assertFalse((bool) $topic->fresh()->is_pinned);
        $this->assertFalse((bool) $topic->fresh()->is_locked);

        $this->actingAs($admin)
            ->postJson("/api/admin/forum/topics/{$topic->id}/pin")
            ->assertOk();
        $this->assertTrue($topic->fresh()->is_pinned);

        $this->actingAs($admin)
            ->postJson("/api/admin/forum/topics/{$topic->id}/pin")
            ->assertOk();
        $this->assertFalse($topic->fresh()->is_pinned);

        $this->actingAs($admin)
            ->postJson("/api/admin/forum/topics/{$topic->id}/lock")
            ->assertOk();
        $this->assertTrue($topic->fresh()->is_locked);

        $this->actingAs($admin)
            ->postJson("/api/admin/forum/topics/{$topic->id}/lock")
            ->assertOk();
        $this->assertFalse($topic->fresh()->is_locked);
    }

    public function test_destroy_and_restore_topic(): void
    {
        $topic = $this->topic();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->deleteJson("/api/admin/forum/topics/{$topic->id}")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $topic->refresh();
        $this->assertNotNull($topic->deleted_at);
        $this->assertSame($admin->id, $topic->deleted_by);

        // Удалённая тема недоступна публично
        $this->getJson("/api/forum/topics/{$topic->id}")->assertNotFound();

        $this->actingAs($admin)
            ->postJson("/api/admin/forum/topics/{$topic->id}/restore")
            ->assertOk();

        $topic->refresh();
        $this->assertNull($topic->deleted_at);
        $this->assertNull($topic->deleted_by);

        $this->getJson("/api/forum/topics/{$topic->id}")->assertOk();
    }

    /* ------------------------------- Ответы ----------------------------- */

    public function test_destroy_reply_soft_deletes_and_recounts_visible_replies(): void
    {
        $topic = $this->topic(null, ['replies_count' => 99]);
        $author = User::factory()->create();

        $first = ForumReply::create([
            'topic_id' => $topic->id,
            'author_id' => $author->id,
            'body' => 'Первый',
        ]);
        ForumReply::create([
            'topic_id' => $topic->id,
            'author_id' => $author->id,
            'body' => 'Второй',
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->deleteJson("/api/admin/forum/replies/{$first->id}")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $first->refresh();
        $this->assertNotNull($first->deleted_at);
        $this->assertSame($admin->id, $first->deleted_by);
        $this->assertDatabaseHas('forum_replies', ['id' => $first->id]);

        // Пересчитывается только по видимым ответам
        $this->assertSame(1, $topic->fresh()->replies_count);
    }

    /* ----------------------------- Статистика --------------------------- */

    public function test_stats_endpoint_counts(): void
    {
        $category = $this->category();
        $visible = $this->topic($category, ['is_pinned' => true]);
        $this->topic($category, ['is_locked' => true]);
        $this->topic($category, ['deleted_at' => now()]);

        ForumReply::create(['topic_id' => $visible->id, 'author_id' => $visible->author_id, 'body' => 'ok']);
        ForumReply::create([
            'topic_id' => $visible->id,
            'author_id' => $visible->author_id,
            'body' => 'скрытый',
            'deleted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/forum/stats')
            ->assertOk();

        $this->assertSame(1, $response->json('categories'));
        $this->assertSame(2, $response->json('topics'));
        $this->assertSame(1, $response->json('deleted_topics'));
        $this->assertSame(1, $response->json('replies'));
        $this->assertSame(1, $response->json('locked'));
        $this->assertSame(1, $response->json('pinned'));
        $this->assertSame(0, $response->json('attachments'));
    }
}
