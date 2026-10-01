<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\ClanForumReply;
use App\Models\ClanForumTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ClanFixtures;
use Tests\TestCase;

/**
 * Api\ClanForumController: внутренний форум клана (топики, ответы, пин/лок).
 */
class ClanForumTest extends TestCase
{
    use ClanFixtures;
    use RefreshDatabase;

    private function topic(Clan $clan, User $author, array $attributes = []): ClanForumTopic
    {
        return ClanForumTopic::create(array_merge([
            'clan_id' => $clan->id,
            'author_id' => $author->id,
            'title' => 'Тема клана',
            'body' => 'Тело темы',
        ], $attributes));
    }

    // ------------------------------------------------------------------ index

    public function test_forum_index_lists_clan_topics_pinned_first(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $regular = $this->topic($clan, $leader, ['title' => 'Обычная тема']);
        $pinned = $this->topic($clan, $leader, ['title' => 'Закреплённая тема', 'is_pinned' => true]);

        // Тема чужого клана не должна попадать в список
        $this->topic($this->clan($this->user()), $this->user(), ['title' => 'Чужая тема']);

        $response = $this->actingAs($leader)->getJson('/api/my-clan/forum')->assertOk();

        $this->assertSame(2, $response->json('total'));
        $this->assertSame($pinned->id, $response->json('data.0.id'));
        $this->assertSame($regular->id, $response->json('data.1.id'));
        $this->assertSame($leader->username, $response->json('data.0.author.username'));
    }

    public function test_forum_index_hidden_from_users_without_clan(): void
    {
        $this->getJson('/api/my-clan/forum')->assertUnauthorized();

        $outsider = $this->user();

        $this->actingAs($outsider)->getJson('/api/my-clan/forum')
            ->assertForbidden()
            ->assertJsonPath('message', 'Вы не в клане.');
    }

    // ------------------------------------------------------------------ store

    public function test_leader_can_create_topic(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $response = $this->actingAs($leader)->postJson('/api/my-clan/forum', [
            'title' => 'Сбор на войну',
            'body' => 'Всем быть в 20:00',
        ])->assertCreated();

        $response->assertJsonPath('topic.title', 'Сбор на войну')
            ->assertJsonPath('topic.body', 'Всем быть в 20:00')
            ->assertJsonPath('topic.clan_id', $clan->id)
            ->assertJsonPath('topic.author_id', $leader->id)
            ->assertJsonPath('topic.author.username', $leader->username);

        // Текущее поведение: ответ на создание отдаёт модель как есть,
        // без дефолтов из БД — is_pinned/is_locked/views в нём отсутствуют
        // (в GET-ответе они уже приведены к false/0).
        $response->assertJsonMissingPath('topic.is_pinned')
            ->assertJsonMissingPath('topic.is_locked')
            ->assertJsonMissingPath('topic.views');

        $this->assertDatabaseHas('clan_forum_topics', [
            'id' => $response->json('topic.id'),
            'clan_id' => $clan->id,
            'author_id' => $leader->id,
            'title' => 'Сбор на войну',
        ]);
    }

    public function test_officer_can_create_topic(): void
    {
        $clan = $this->clan($this->user());
        $officer = $this->user();
        $this->member($clan, $officer, 'officer');

        $this->actingAs($officer)->postJson('/api/my-clan/forum', [
            'title' => 'Тема от офицера',
            'body' => 'Тело',
        ])->assertCreated();

        $this->assertDatabaseCount('clan_forum_topics', 1);
    }

    public function test_member_with_custom_forum_permission_can_create_topic(): void
    {
        $clan = $this->clan($this->user());
        $member = $this->user();
        $this->member($clan, $member, 'member', ['permissions' => ['forum']]);

        $this->actingAs($member)->postJson('/api/my-clan/forum', [
            'title' => 'Тема с правом',
            'body' => 'Тело',
        ])->assertCreated();
    }

    public function test_plain_member_cannot_create_topic(): void
    {
        $clan = $this->clan($this->user());
        $member = $this->user();
        $this->member($clan, $member);

        $this->actingAs($member)->postJson('/api/my-clan/forum', [
            'title' => 'Нельзя',
            'body' => 'Тело',
        ])->assertForbidden();

        $this->assertDatabaseCount('clan_forum_topics', 0);
    }

    public function test_topic_store_validates_payload(): void
    {
        $leader = $this->user();
        $this->clan($leader);

        $this->actingAs($leader)->postJson('/api/my-clan/forum', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'body']);

        $this->actingAs($leader)->postJson('/api/my-clan/forum', [
            'title' => str_repeat('t', 161),
            'body' => 'ok',
        ])->assertStatus(422)->assertJsonValidationErrors('title');

        $this->actingAs($leader)->postJson('/api/my-clan/forum', [
            'title' => 'ok',
            'body' => str_repeat('b', 10001),
        ])->assertStatus(422)->assertJsonValidationErrors('body');

        $this->assertDatabaseCount('clan_forum_topics', 0);
    }

    // ------------------------------------------------------------------- show

    public function test_show_increments_views_and_returns_replies(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $topic = $this->topic($clan, $leader);

        ClanForumReply::create([
            'topic_id' => $topic->id,
            'author_id' => $leader->id,
            'body' => 'Первый ответ',
        ]);

        $response = $this->actingAs($leader)->getJson("/api/my-clan/forum/{$topic->id}")->assertOk();

        $response->assertJsonPath('topic.id', $topic->id)
            ->assertJsonPath('topic.views', 1)
            ->assertJsonPath('topic.author.username', $leader->username);

        $this->assertCount(1, $response->json('topic.replies'));
        $this->assertSame('Первый ответ', $response->json('topic.replies.0.body'));
        $this->assertSame($leader->username, $response->json('topic.replies.0.author.username'));

        // Каждый просмотр увеличивает счётчик
        $this->actingAs($leader)->getJson("/api/my-clan/forum/{$topic->id}")->assertOk();
        $this->assertSame(2, $topic->fresh()->views);
    }

    public function test_show_returns_404_for_topic_of_another_clan(): void
    {
        $foreignClan = $this->clan($this->user());
        $foreignTopic = $this->topic($foreignClan, User::find($foreignClan->leader_id));

        $leader = $this->user();
        $this->clan($leader);

        $this->actingAs($leader)->getJson("/api/my-clan/forum/{$foreignTopic->id}")->assertNotFound();
        $this->assertSame(0, $foreignTopic->fresh()->views);
    }

    public function test_show_unknown_topic_returns_404(): void
    {
        $leader = $this->user();
        $this->clan($leader);

        $this->actingAs($leader)->getJson('/api/my-clan/forum/999999')->assertNotFound();
    }

    // ------------------------------------------------------------------ reply

    public function test_any_member_can_reply_and_counters_are_updated(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $topic = $this->topic($clan, $leader);

        $member = $this->user();
        $this->member($clan, $member);

        $response = $this->actingAs($member)
            ->postJson("/api/my-clan/forum/{$topic->id}/reply", ['body' => 'Согласен'])
            ->assertCreated();

        $response->assertJsonPath('reply.body', 'Согласен')
            ->assertJsonPath('reply.topic_id', $topic->id)
            ->assertJsonPath('reply.author_id', $member->id)
            ->assertJsonPath('reply.author.username', $member->username);

        $this->assertDatabaseHas('clan_forum_replies', [
            'topic_id' => $topic->id,
            'author_id' => $member->id,
            'body' => 'Согласен',
        ]);

        $fresh = $topic->fresh();
        $this->assertSame(1, $fresh->replies_count);
        $this->assertSame($member->id, $fresh->last_reply_user_id);
        $this->assertNotNull($fresh->last_reply_at);
    }

    public function test_reply_to_locked_topic_is_rejected(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $topic = $this->topic($clan, $leader, ['is_locked' => true]);

        $this->actingAs($leader)
            ->postJson("/api/my-clan/forum/{$topic->id}/reply", ['body' => 'Нельзя'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Топик закрыт.');

        $this->assertDatabaseCount('clan_forum_replies', 0);
    }

    public function test_reply_validates_body_and_parent(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $topic = $this->topic($clan, $leader);

        $this->actingAs($leader)->postJson("/api/my-clan/forum/{$topic->id}/reply", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->actingAs($leader)->postJson("/api/my-clan/forum/{$topic->id}/reply", [
            'body' => 'ok',
            'parent_id' => 999999,
        ])->assertStatus(422)->assertJsonValidationErrors('parent_id');

        $this->assertDatabaseCount('clan_forum_replies', 0);
    }

    public function test_reply_returns_404_for_topic_of_another_clan(): void
    {
        $foreignClan = $this->clan($this->user());
        $foreignTopic = $this->topic($foreignClan, User::find($foreignClan->leader_id));

        $leader = $this->user();
        $this->clan($leader);

        $this->actingAs($leader)
            ->postJson("/api/my-clan/forum/{$foreignTopic->id}/reply", ['body' => 'x'])
            ->assertNotFound();
    }

    /**
     * Исправлено: parent_id проверялся только на существование, поэтому
     * ответ мог ссылаться на сообщение из другой темы. Теперь это 422.
     */
    public function test_parent_reply_from_another_topic_is_rejected(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $firstTopic = $this->topic($clan, $leader, ['title' => 'Первая']);
        $secondTopic = $this->topic($clan, $leader, ['title' => 'Вторая']);

        $foreignReply = ClanForumReply::create([
            'topic_id' => $secondTopic->id,
            'author_id' => $leader->id,
            'body' => 'Ответ во второй теме',
        ]);

        $this->actingAs($leader)
            ->postJson("/api/my-clan/forum/{$firstTopic->id}/reply", [
                'body' => 'Ответ с чужим родителем',
                'parent_id' => $foreignReply->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);

        $this->assertDatabaseMissing('clan_forum_replies', [
            'topic_id' => $firstTopic->id,
            'parent_id' => $foreignReply->id,
        ]);
    }

    // --------------------------------------------------------------- pin/lock

    public function test_pin_and_lock_toggle_topic_flags(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $topic = $this->topic($clan, $leader);

        $this->actingAs($leader)->postJson("/api/my-clan/forum/{$topic->id}/pin")
            ->assertOk()
            ->assertJsonPath('topic.is_pinned', true);
        $this->assertTrue($topic->fresh()->is_pinned);

        $this->actingAs($leader)->postJson("/api/my-clan/forum/{$topic->id}/pin")
            ->assertOk()
            ->assertJsonPath('topic.is_pinned', false);
        $this->assertFalse($topic->fresh()->is_pinned);

        $this->actingAs($leader)->postJson("/api/my-clan/forum/{$topic->id}/lock")
            ->assertOk()
            ->assertJsonPath('topic.is_locked', true);
        $this->assertTrue($topic->fresh()->is_locked);
    }

    public function test_officer_can_pin_and_lock(): void
    {
        $clan = $this->clan($this->user());
        $officer = $this->user();
        $this->member($clan, $officer, 'officer');
        $topic = $this->topic($clan, $officer);

        $this->actingAs($officer)->postJson("/api/my-clan/forum/{$topic->id}/pin")->assertOk();
        $this->actingAs($officer)->postJson("/api/my-clan/forum/{$topic->id}/lock")->assertOk();

        $this->assertTrue($topic->fresh()->is_pinned);
        $this->assertTrue($topic->fresh()->is_locked);
    }

    public function test_plain_member_cannot_pin_or_lock(): void
    {
        $clan = $this->clan($this->user());
        $member = $this->user();
        $this->member($clan, $member);
        $topic = $this->topic($clan, $member);

        $this->actingAs($member)->postJson("/api/my-clan/forum/{$topic->id}/pin")->assertForbidden();
        $this->actingAs($member)->postJson("/api/my-clan/forum/{$topic->id}/lock")->assertForbidden();

        $this->assertFalse($topic->fresh()->is_pinned);
        $this->assertFalse($topic->fresh()->is_locked);
    }

    public function test_pin_returns_404_for_foreign_topic(): void
    {
        $foreignClan = $this->clan($this->user());
        $foreignTopic = $this->topic($foreignClan, User::find($foreignClan->leader_id));

        $leader = $this->user();
        $this->clan($leader);

        $this->actingAs($leader)->postJson("/api/my-clan/forum/{$foreignTopic->id}/pin")->assertNotFound();
        $this->actingAs($leader)->postJson("/api/my-clan/forum/{$foreignTopic->id}/lock")->assertNotFound();
    }

    // ---------------------------------------------------------------- destroy

    public function test_author_can_delete_own_topic(): void
    {
        $clan = $this->clan($this->user());
        $member = $this->user();
        $this->member($clan, $member);
        $topic = $this->topic($clan, $member);

        $this->actingAs($member)->deleteJson("/api/my-clan/forum/{$topic->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('clan_forum_topics', ['id' => $topic->id]);
    }

    public function test_leader_and_officer_can_delete_foreign_topic(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $officer = $this->user();
        $this->member($clan, $officer, 'officer');
        $member = $this->user();
        $this->member($clan, $member);

        $first = $this->topic($clan, $member);
        $second = $this->topic($clan, $member);

        $this->actingAs($officer)->deleteJson("/api/my-clan/forum/{$first->id}")->assertOk();
        $this->actingAs($leader)->deleteJson("/api/my-clan/forum/{$second->id}")->assertOk();

        $this->assertDatabaseMissing('clan_forum_topics', ['id' => $first->id]);
        $this->assertDatabaseMissing('clan_forum_topics', ['id' => $second->id]);
    }

    public function test_plain_member_cannot_delete_foreign_topic(): void
    {
        $clan = $this->clan($this->user());
        $author = $this->user();
        $this->member($clan, $author);
        $other = $this->user();
        $this->member($clan, $other);

        $topic = $this->topic($clan, $author);

        $this->actingAs($other)->deleteJson("/api/my-clan/forum/{$topic->id}")->assertForbidden();

        $this->assertDatabaseHas('clan_forum_topics', ['id' => $topic->id]);
    }

    public function test_destroy_returns_404_for_foreign_topic(): void
    {
        $foreignClan = $this->clan($this->user());
        $foreignTopic = $this->topic($foreignClan, User::find($foreignClan->leader_id));

        $leader = $this->user();
        $this->clan($leader);

        $this->actingAs($leader)->deleteJson("/api/my-clan/forum/{$foreignTopic->id}")->assertNotFound();

        $this->assertDatabaseHas('clan_forum_topics', ['id' => $foreignTopic->id]);
    }

    // ------------------------------------------------------------ guest access

    public function test_guest_cannot_access_clan_forum(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $topic = $this->topic($clan, $leader);

        $this->getJson('/api/my-clan/forum')->assertUnauthorized();
        $this->postJson('/api/my-clan/forum', ['title' => 'x', 'body' => 'y'])->assertUnauthorized();
        $this->getJson("/api/my-clan/forum/{$topic->id}")->assertUnauthorized();
        $this->postJson("/api/my-clan/forum/{$topic->id}/reply", ['body' => 'x'])->assertUnauthorized();
        $this->postJson("/api/my-clan/forum/{$topic->id}/pin")->assertUnauthorized();
        $this->postJson("/api/my-clan/forum/{$topic->id}/lock")->assertUnauthorized();
        $this->deleteJson("/api/my-clan/forum/{$topic->id}")->assertUnauthorized();
    }
}
