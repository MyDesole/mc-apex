<?php

namespace Tests\Feature;

use App\Domains\Forum\Models\ForumCategory;
use App\Domains\Forum\Models\ForumReply;
use App\Domains\Forum\Models\ForumTopic;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumReplyNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function topic(User $author): ForumTopic
    {
        $category = ForumCategory::create([
            'name' => 'Общее',
            'slug' => 'general-notify',
            'post_policy' => 'all',
            'is_active' => true,
        ]);

        return ForumTopic::create([
            'category_id' => $category->id,
            'author_id' => $author->id,
            'title' => 'Тема для уведомлений',
            'body' => 'Тело темы',
        ]);
    }

    private function forumNotifications(User $user)
    {
        return $user->fresh()->notifications->filter(fn ($n) => str_starts_with($n->data['type'] ?? '', 'forum_reply'));
    }

    public function test_topic_author_is_notified_about_reply(): void
    {
        $author = User::factory()->create();
        $replier = User::factory()->create();
        $topic = $this->topic($author);

        $this->actingAs($replier)
            ->postJson("/api/forum/topics/{$topic->id}/reply", ['body' => 'Привет'])
            ->assertSuccessful();

        $notes = $this->forumNotifications($author);
        $this->assertCount(1, $notes);
        $this->assertSame('forum_reply', $notes->first()->data['type']);
        $this->assertSame($topic->id, $notes->first()->data['topic_id']);
        $this->assertCount(0, $this->forumNotifications($replier));
    }

    public function test_own_reply_does_not_notify_and_unread_is_not_duplicated(): void
    {
        $author = User::factory()->create();
        $replier = User::factory()->create();
        $topic = $this->topic($author);

        $this->actingAs($author)->postJson("/api/forum/topics/{$topic->id}/reply", ['body' => 'Сам себе'])->assertSuccessful();
        $this->assertCount(0, $this->forumNotifications($author));

        $this->actingAs($replier)->postJson("/api/forum/topics/{$topic->id}/reply", ['body' => 'Раз'])->assertSuccessful();
        $this->actingAs($replier)->postJson("/api/forum/topics/{$topic->id}/reply", ['body' => 'Два'])->assertSuccessful();
        $this->assertCount(1, $this->forumNotifications($author));
    }

    public function test_parent_reply_author_gets_personal_notification(): void
    {
        $author = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $topic = $this->topic($author);

        $parent = ForumReply::create(['topic_id' => $topic->id, 'author_id' => $first->id, 'body' => 'Первый']);

        $this->actingAs($second)
            ->postJson("/api/forum/topics/{$topic->id}/reply", ['body' => 'Ответ', 'parent_id' => $parent->id])
            ->assertSuccessful();

        $this->assertSame('forum_reply_to_you', $this->forumNotifications($first)->first()?->data['type']);
        $this->assertSame('forum_reply', $this->forumNotifications($author)->first()?->data['type']);
    }
}
