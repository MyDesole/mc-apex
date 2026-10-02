<?php

namespace Tests\Feature;

use App\Domains\Forum\Models\ForumCategory;
use App\Domains\Forum\Models\ForumReply;
use App\Domains\Forum\Models\ForumTopic;
use App\Domains\Users\Models\User;
use Database\Seeders\ForumCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumNestedRepliesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ForumTopic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ForumCategorySeeder::class);

        $this->user = User::factory()->create();

        $category = ForumCategory::where('slug', 'general')->firstOrFail();

        $this->topic = ForumTopic::create([
            'category_id' => $category->id,
            'author_id' => $this->user->id,
            'title' => 'Тема с ветками',
            'body' => 'Тело темы',
        ]);
    }

    private function reply(?int $parentId, string $body, ?User $author = null): ForumReply
    {
        return ForumReply::create([
            'topic_id' => $this->topic->id,
            'author_id' => ($author ?? $this->user)->id,
            'parent_id' => $parentId,
            'body' => $body,
        ]);
    }

    public function test_replies_are_returned_as_tree(): void
    {
        $root = $this->reply(null, 'Корневой ответ');
        $child = $this->reply($root->id, 'Ответ на ответ');
        $grandchild = $this->reply($child->id, 'Третий уровень');
        $this->reply(null, 'Второй корневой');

        $response = $this->getJson("/api/forum/topics/{$this->topic->id}")->assertOk();

        $replies = $response->json('replies');

        // Два корневых ответа
        $this->assertCount(2, $replies);
        $this->assertSame('Корневой ответ', $replies[0]['body']);
        $this->assertSame(0, $replies[0]['depth']);

        // Вложенность
        $this->assertCount(1, $replies[0]['children']);
        $this->assertSame('Ответ на ответ', $replies[0]['children'][0]['body']);
        $this->assertSame(1, $replies[0]['children'][0]['depth']);

        $this->assertCount(1, $replies[0]['children'][0]['children']);
        $this->assertSame('Третий уровень', $replies[0]['children'][0]['children'][0]['body']);
        $this->assertSame(2, $replies[0]['children'][0]['children'][0]['depth']);

        // Плоского списка больше нет
        $this->assertSame(4, $response->json('replies_count'));
    }

    public function test_reply_to_reply_stores_parent(): void
    {
        $root = $this->reply(null, 'Первый');

        $created = $this->actingAs($this->user)
            ->postJson("/api/forum/topics/{$this->topic->id}/reply", [
                'body' => 'Ответ на первый',
                'parent_id' => $root->id,
            ])
            ->assertCreated();

        $this->assertSame($root->id, $created->json('reply.parent_id'));

        $tree = $this->getJson("/api/forum/topics/{$this->topic->id}")->json('replies');

        $this->assertCount(1, $tree);
        $this->assertCount(1, $tree[0]['children']);
    }

    public function test_cannot_reply_to_foreign_topic_reply(): void
    {
        // Ответ из другой темы
        $otherTopic = ForumTopic::create([
            'category_id' => $this->topic->category_id,
            'author_id' => $this->user->id,
            'title' => 'Другая тема',
            'body' => 'Тело',
        ]);

        $foreign = ForumReply::create([
            'topic_id' => $otherTopic->id,
            'author_id' => $this->user->id,
            'body' => 'Ответ в другой теме',
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/forum/topics/{$this->topic->id}/reply", [
                'body' => 'Попытка связать темы',
                'parent_id' => $foreign->id,
            ])
            ->assertStatus(422);
    }

    public function test_depth_is_limited(): void
    {
        $parentId = null;

        // Строим цепочку ровно до предела: уровни 0..MAX_DEPTH-1
        for ($i = 0; $i < ForumReply::MAX_DEPTH; $i++) {
            $reply = $this->reply($parentId, "Уровень {$i}");
            $parentId = $reply->id;
        }

        // Последний созданный — на максимальной глубине
        $deepest = ForumReply::find($parentId);
        $this->assertSame(ForumReply::MAX_DEPTH - 1, $deepest->depth());

        // Следующий уровень должен быть отклонён
        $this->actingAs($this->user)
            ->postJson("/api/forum/topics/{$this->topic->id}/reply", [
                'body' => 'Слишком глубоко',
                'parent_id' => $parentId,
            ])
            ->assertStatus(422);
    }

    public function test_descendant_ids_walks_the_whole_branch(): void
    {
        $root = $this->reply(null, 'Корень');
        $a = $this->reply($root->id, 'A');
        $b = $this->reply($root->id, 'B');
        $deep = $this->reply($a->id, 'A1');

        $ids = $root->descendantIds();

        sort($ids);

        $expected = [$a->id, $b->id, $deep->id];
        sort($expected);

        $this->assertSame($expected, $ids);
    }

    public function test_moderator_delete_removes_whole_branch(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator']);

        $root = $this->reply(null, 'Корень');
        $child = $this->reply($root->id, 'Ребёнок');
        $grandchild = $this->reply($child->id, 'Внук');

        $this->actingAs($moderator)
            ->deleteJson("/api/forum/replies/{$root->id}")
            ->assertOk();

        $this->assertNotNull($root->fresh()->deleted_at);
        $this->assertNotNull($child->fresh()->deleted_at);
        $this->assertNotNull($grandchild->fresh()->deleted_at);

        // В дереве ничего не осталось
        $tree = $this->getJson("/api/forum/topics/{$this->topic->id}")->json('replies');
        $this->assertCount(0, $tree);
    }

    public function test_orphan_reply_with_missing_parent_becomes_root(): void
    {
        $root = $this->reply(null, 'Корень');
        $orphan = $this->reply($root->id, 'Осиротевший');

        // Родитель пропал (например, удалён жёстко)
        $root->forceDelete();

        $tree = $this->getJson("/api/forum/topics/{$this->topic->id}")->json('replies');

        $this->assertCount(1, $tree);
        $this->assertSame('Осиротевший', $tree[0]['body']);
    }
}
