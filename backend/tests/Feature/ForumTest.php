<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\User;
use Database\Seeders\ForumCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ForumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ForumCategorySeeder::class);
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    public function test_forum_index_is_public_and_lists_categories(): void
    {
        $response = $this->getJson('/api/forum')->assertOk();

        $this->assertNotEmpty($response->json('categories'));
        $this->assertArrayHasKey('stats', $response->json());
    }

    public function test_topic_list_is_public(): void
    {
        $this->getJson('/api/forum/topics')->assertOk();
    }

    public function test_user_can_create_topic_and_read_it(): void
    {
        $user = $this->user();
        $category = ForumCategory::where('slug', 'general')->firstOrFail();

        $created = $this->actingAs($user)->postJson('/api/forum/topics', [
            'category_id' => $category->id,
            'title' => 'Как поднять тир на BedWars',
            'body' => 'Делюсь опытом: главное — мувмент и игра на кроватях.',
        ])->assertCreated();

        $topicId = $created->json('topic.id');

        $this->assertDatabaseHas('forum_topics', ['id' => $topicId, 'title' => 'Как поднять тир на BedWars']);

        $show = $this->getJson("/api/forum/topics/{$topicId}")->assertOk();

        $this->assertSame('Как поднять тир на BedWars', $show->json('topic.title'));
        $this->assertFalse($show->json('topic.is_pinned'));
        $this->assertTrue($show->json('can_reply'));
    }

    public function test_reply_updates_topic_counters(): void
    {
        $author = $this->user();
        $replier = $this->user();
        $category = ForumCategory::where('slug', 'general')->firstOrFail();

        $topic = ForumTopic::create([
            'category_id' => $category->id,
            'author_id' => $author->id,
            'title' => 'Тема для ответа',
            'body' => 'Тело темы',
        ]);

        $this->actingAs($replier)
            ->postJson("/api/forum/topics/{$topic->id}/reply", ['body' => 'Первый ответ'])
            ->assertCreated();

        $topic->refresh();

        $this->assertSame(1, $topic->replies_count);
        $this->assertSame($replier->id, $topic->last_reply_user_id);
    }

    public function test_locked_topic_rejects_replies(): void
    {
        $user = $this->user();
        $category = ForumCategory::where('slug', 'general')->firstOrFail();

        $topic = ForumTopic::create([
            'category_id' => $category->id,
            'author_id' => $user->id,
            'title' => 'Закрытая тема',
            'body' => 'Тело',
            'is_locked' => true,
        ]);

        $this->actingAs($user)
            ->postJson("/api/forum/topics/{$topic->id}/reply", ['body' => 'нельзя'])
            ->assertStatus(422);
    }

    public function test_staff_only_category_blocks_regular_users(): void
    {
        $user = $this->user();
        $announcements = ForumCategory::where('slug', 'announcements')->firstOrFail();

        $this->actingAs($user)->postJson('/api/forum/topics', [
            'category_id' => $announcements->id,
            'title' => 'Объявление от игрока',
            'body' => 'Не должно пройти',
        ])->assertStatus(403);

        $admin = $this->user(['role' => 'admin']);

        $this->actingAs($admin)->postJson('/api/forum/topics', [
            'category_id' => $announcements->id,
            'title' => 'Настоящее объявление',
            'body' => 'От администрации',
        ])->assertCreated();
    }

    public function test_attachment_upload_and_attach_to_topic(): void
    {
        Storage::fake('public');

        $user = $this->user();
        $category = ForumCategory::where('slug', 'general')->firstOrFail();

        $upload = $this->actingAs($user)
            ->post('/api/forum/upload', [
                'file' => UploadedFile::fake()->image('proof.png', 600, 400)->size(500),
            ])
            ->assertCreated();

        $attachmentId = $upload->json('attachment.id');
        $this->assertTrue($upload->json('attachment.is_image'));

        $this->actingAs($user)->postJson('/api/forum/topics', [
            'category_id' => $category->id,
            'title' => 'Тема с картинкой',
            'body' => 'Смотрите скриншот',
            'attachments' => [$attachmentId],
        ])->assertCreated();

        $this->assertDatabaseHas('forum_attachments', [
            'id' => $attachmentId,
            'attachable_type' => 'topic',
        ]);
    }

    public function test_like_toggles_topic_likes(): void
    {
        $author = $this->user();
        $liker = $this->user();
        $category = ForumCategory::where('slug', 'general')->firstOrFail();

        $topic = ForumTopic::create([
            'category_id' => $category->id,
            'author_id' => $author->id,
            'title' => 'Нравится?',
            'body' => 'Тело',
        ]);

        $first = $this->actingAs($liker)->postJson("/api/forum/like/topic/{$topic->id}")->assertOk();
        $this->assertTrue($first->json('liked'));
        $this->assertSame(1, $first->json('count'));

        $second = $this->actingAs($liker)->postJson("/api/forum/like/topic/{$topic->id}")->assertOk();
        $this->assertFalse($second->json('liked'));
        $this->assertSame(0, $second->json('count'));
    }

    public function test_author_can_edit_and_delete_own_topic(): void
    {
        $author = $this->user();
        $stranger = $this->user();
        $category = ForumCategory::where('slug', 'general')->firstOrFail();

        $topic = ForumTopic::create([
            'category_id' => $category->id,
            'author_id' => $author->id,
            'title' => 'Исходное название',
            'body' => 'Тело',
        ]);

        // Чужой не может править
        $this->actingAs($stranger)
            ->putJson("/api/forum/topics/{$topic->id}", ['title' => 'Взлом названия'])
            ->assertForbidden();

        // Автор может
        $this->actingAs($author)
            ->putJson("/api/forum/topics/{$topic->id}", ['title' => 'Новое название'])
            ->assertOk();

        $this->assertSame('Новое название', $topic->fresh()->title);

        // Удаление мягкое: тема пропадает из списка, но остаётся в БД
        $this->actingAs($author)->deleteJson("/api/forum/topics/{$topic->id}")->assertOk();

        $this->getJson("/api/forum/topics/{$topic->id}")->assertNotFound();
        $this->assertNotNull($topic->fresh()->deleted_at);
    }

    public function test_search_finds_topics_and_replies(): void
    {
        $user = $this->user();
        $category = ForumCategory::where('slug', 'general')->firstOrFail();

        ForumTopic::create([
            'category_id' => $category->id,
            'author_id' => $user->id,
            'title' => 'Гайд по ротке',
            'body' => 'Ротка — важный аспект',
        ]);

        $response = $this->getJson('/api/forum/search?q=ротке')->assertOk();

        $this->assertNotEmpty($response->json('topics'));
    }

    public function test_admin_can_pin_lock_and_restore_topic(): void
    {
        $admin = $this->user(['role' => 'admin']);
        $author = $this->user();
        $category = ForumCategory::where('slug', 'general')->firstOrFail();

        $topic = ForumTopic::create([
            'category_id' => $category->id,
            'author_id' => $author->id,
            'title' => 'Тема для модерации',
            'body' => 'Тело',
        ]);

        $this->actingAs($admin)->postJson("/api/admin/forum/topics/{$topic->id}/pin")->assertOk();
        $this->assertTrue($topic->fresh()->is_pinned);

        $this->actingAs($admin)->postJson("/api/admin/forum/topics/{$topic->id}/lock")->assertOk();
        $this->assertTrue($topic->fresh()->is_locked);

        $this->actingAs($admin)->deleteJson("/api/admin/forum/topics/{$topic->id}")->assertOk();
        $this->assertNotNull($topic->fresh()->deleted_at);

        $this->actingAs($admin)->postJson("/api/admin/forum/topics/{$topic->id}/restore")->assertOk();
        $this->assertNull($topic->fresh()->deleted_at);
    }

    public function test_regular_user_cannot_access_admin_forum(): void
    {
        $user = $this->user();

        $this->actingAs($user)->getJson('/api/admin/forum/stats')->assertForbidden();
        $this->actingAs($user)->getJson('/api/admin/forum/categories')->assertForbidden();
    }

    public function test_admin_can_manage_categories(): void
    {
        $admin = $this->user(['role' => 'admin']);

        $created = $this->actingAs($admin)->postJson('/api/admin/forum/categories', [
            'name' => 'Медиа',
            'description' => 'Скриншоты и клипы',
            'icon' => 'frame',
            'color' => '#22c55e',
            'post_policy' => 'all',
        ])->assertCreated();

        $id = $created->json('category.id');
        $this->assertSame('media', $created->json('category.slug'));

        $this->actingAs($admin)->putJson("/api/admin/forum/categories/{$id}", [
            'name' => 'Медиа и клипы',
        ])->assertOk();

        $this->actingAs($admin)->deleteJson("/api/admin/forum/categories/{$id}")->assertOk();
        $this->assertDatabaseMissing('forum_categories', ['id' => $id]);
    }

    public function test_guest_cannot_create_topic(): void
    {
        $category = ForumCategory::where('slug', 'general')->firstOrFail();

        $this->postJson('/api/forum/topics', [
            'category_id' => $category->id,
            'title' => 'Аноним',
            'body' => 'Не должно пройти',
        ])->assertUnauthorized();
    }
}
