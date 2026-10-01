<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\ClanEvent;
use App\Models\ClanEventComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ClanFixtures;
use Tests\TestCase;

/**
 * ClanEventController + Api\ClanEventCommentController:
 * новости/ивенты клана и комментарии к ним.
 */
class ClanEventTest extends TestCase
{
    use ClanFixtures;
    use RefreshDatabase;

    private function event(Clan $clan, User $author, array $attributes = []): ClanEvent
    {
        return ClanEvent::create(array_merge([
            'clan_id' => $clan->id,
            'author_id' => $author->id,
            'type' => 'announcement',
            'title' => 'Новость клана',
            'body' => 'Текст новости',
        ], $attributes));
    }

    // ------------------------------------------------------------------ index

    public function test_events_index_is_available_to_any_authenticated_user(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $event = $this->event($clan, $leader, ['title' => 'Сбор состава']);

        // Даже посторонний авторизованный игрок видит список ивентов клана
        $stranger = $this->user();

        $response = $this->actingAs($stranger)->getJson("/api/clans/{$clan->id}/events")->assertOk();

        $this->assertSame(1, $response->json('total'));
        $this->assertSame($event->id, $response->json('data.0.id'));
        $this->assertSame('Сбор состава', $response->json('data.0.title'));
        $this->assertSame($leader->username, $response->json('data.0.author.username'));
    }

    public function test_events_index_returns_404_for_unknown_clan(): void
    {
        $this->actingAs($this->user())->getJson('/api/clans/999999/events')->assertNotFound();
    }

    public function test_events_index_requires_auth(): void
    {
        $clan = $this->clan($this->user());

        $this->getJson("/api/clans/{$clan->id}/events")->assertUnauthorized();
    }

    // ------------------------------------------------------------------ store

    public function test_leader_can_create_event(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $response = $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/events", [
            'type' => 'training',
            'title' => 'Тренировка',
            'body' => 'Отжимания по расписанию',
            'starts_at' => now()->addDays(2)->toDateTimeString(),
        ])->assertCreated();

        $response->assertJsonPath('event.type', 'training')
            ->assertJsonPath('event.title', 'Тренировка')
            ->assertJsonPath('event.body', 'Отжимания по расписанию')
            ->assertJsonPath('event.clan_id', $clan->id)
            ->assertJsonPath('event.author_id', $leader->id)
            ->assertJsonPath('event.author.username', $leader->username);

        $this->assertDatabaseHas('clan_events', [
            'clan_id' => $clan->id,
            'author_id' => $leader->id,
            'type' => 'training',
            'title' => 'Тренировка',
        ]);
    }

    public function test_officer_can_create_event(): void
    {
        $clan = $this->clan($this->user());
        $officer = $this->user();
        $this->member($clan, $officer, 'officer');

        $this->actingAs($officer)->postJson("/api/clans/{$clan->id}/events", [
            'type' => 'event',
            'title' => 'Матч',
        ])->assertCreated();

        $this->assertDatabaseCount('clan_events', 1);
    }

    public function test_plain_member_cannot_create_event(): void
    {
        $clan = $this->clan($this->user());
        $member = $this->user();
        $this->member($clan, $member);

        $this->actingAs($member)->postJson("/api/clans/{$clan->id}/events", [
            'type' => 'announcement',
            'title' => 'Нельзя',
        ])->assertForbidden();

        $this->assertDatabaseCount('clan_events', 0);
    }

    public function test_stranger_without_clan_cannot_create_event(): void
    {
        $clan = $this->clan($this->user());

        $this->actingAs($this->user())->postJson("/api/clans/{$clan->id}/events", [
            'type' => 'announcement',
            'title' => 'Нельзя',
        ])->assertForbidden();
    }

    public function test_event_store_validates_payload(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/events", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'title']);

        $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/events", [
            'type' => 'unknown',
            'title' => 'ok',
        ])->assertStatus(422)->assertJsonValidationErrors('type');

        $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/events", [
            'type' => 'event',
            'title' => str_repeat('t', 121),
        ])->assertStatus(422)->assertJsonValidationErrors('title');

        $this->actingAs($leader)->postJson("/api/clans/{$clan->id}/events", [
            'type' => 'event',
            'title' => 'ok',
            'starts_at' => 'not-a-date',
        ])->assertStatus(422)->assertJsonValidationErrors('starts_at');

        $this->assertDatabaseCount('clan_events', 0);
    }

    public function test_event_store_returns_404_for_unknown_clan(): void
    {
        $leader = $this->user();
        $this->clan($leader);

        $this->actingAs($leader)->postJson('/api/clans/999999/events', [
            'type' => 'event',
            'title' => 'ok',
        ])->assertNotFound();
    }

    // ---------------------------------------------------------------- destroy

    public function test_leader_can_delete_any_event_of_clan(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $member = $this->user();
        $this->member($clan, $member);

        $event = $this->event($clan, $member);

        $this->actingAs($leader)->deleteJson("/api/clans/{$clan->id}/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('clan_events', ['id' => $event->id]);
    }

    public function test_author_can_delete_own_event(): void
    {
        $clan = $this->clan($this->user());
        $member = $this->user();
        $this->member($clan, $member);

        $event = $this->event($clan, $member);

        $this->actingAs($member)->deleteJson("/api/clans/{$clan->id}/events/{$event->id}")->assertOk();

        $this->assertDatabaseMissing('clan_events', ['id' => $event->id]);
    }

    /**
     * Исправлено: destroy() разрешал удаление только лидеру или автору,
     * хотя офицер имеет право создавать события. Теперь права симметричны.
     */
    public function test_officer_can_delete_foreign_event(): void
    {
        $clan = $this->clan($this->user());
        $officer = $this->user();
        $this->member($clan, $officer, 'officer');
        $member = $this->user();
        $this->member($clan, $member);

        $event = $this->event($clan, $member);

        $this->actingAs($officer)
            ->deleteJson("/api/clans/{$clan->id}/events/{$event->id}")
            ->assertOk();

        $this->assertDatabaseMissing('clan_events', ['id' => $event->id]);
    }

    /**
     * Обычный участник по-прежнему не может удалять чужие события.
     */
    public function test_plain_member_cannot_delete_foreign_event(): void
    {
        $clan = $this->clan($this->user());
        $member = $this->user();
        $this->member($clan, $member);
        $other = $this->user();
        $this->member($clan, $other);

        $event = $this->event($clan, $other);

        $this->actingAs($member)
            ->deleteJson("/api/clans/{$clan->id}/events/{$event->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('clan_events', ['id' => $event->id]);
    }

    public function test_event_destroy_returns_404_for_foreign_clan(): void
    {
        $foreignClan = $this->clan($this->user());
        $foreignEvent = $this->event($foreignClan, User::find($foreignClan->leader_id));

        $leader = $this->user();
        $clan = $this->clan($leader);

        $this->actingAs($leader)->deleteJson("/api/clans/{$clan->id}/events/{$foreignEvent->id}")->assertNotFound();

        $this->assertDatabaseHas('clan_events', ['id' => $foreignEvent->id]);
    }

    public function test_event_destroy_returns_404_for_unknown_event(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);

        $this->actingAs($leader)->deleteJson("/api/clans/{$clan->id}/events/999999")->assertNotFound();
    }

    // --------------------------------------------------------- comments index

    public function test_member_can_list_event_comments_with_replies(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $event = $this->event($clan, $leader);

        $root = ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $leader->id,
            'body' => 'Корневой комментарий',
        ]);

        ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $leader->id,
            'parent_id' => $root->id,
            'body' => 'Ответ',
        ]);

        // Комментарий другого ивента не должен попадать в выдачу
        $otherEvent = $this->event($clan, $leader, ['title' => 'Другой ивент']);
        ClanEventComment::create([
            'clan_event_id' => $otherEvent->id,
            'user_id' => $leader->id,
            'body' => 'Чужой комментарий',
        ]);

        $response = $this->actingAs($leader)
            ->getJson("/api/clans/{$clan->id}/events/{$event->id}/comments")
            ->assertOk();

        $this->assertCount(1, $response->json('comments'));
        $this->assertSame('Корневой комментарий', $response->json('comments.0.body'));
        $this->assertSame($leader->username, $response->json('comments.0.user.username'));
        $this->assertCount(1, $response->json('comments.0.replies'));
        $this->assertSame('Ответ', $response->json('comments.0.replies.0.body'));
    }

    public function test_comments_index_is_forbidden_for_non_members(): void
    {
        $clan = $this->clan($this->user());
        $event = $this->event($clan, User::find($clan->leader_id));

        $this->actingAs($this->user())
            ->getJson("/api/clans/{$clan->id}/events/{$event->id}/comments")
            ->assertForbidden();
    }

    public function test_comments_index_returns_404_for_foreign_event(): void
    {
        $foreignClan = $this->clan($this->user());
        $foreignEvent = $this->event($foreignClan, User::find($foreignClan->leader_id));

        $leader = $this->user();
        $clan = $this->clan($leader);

        $this->actingAs($leader)
            ->getJson("/api/clans/{$clan->id}/events/{$foreignEvent->id}/comments")
            ->assertNotFound();
    }

    // --------------------------------------------------------- comments store

    public function test_member_can_comment_on_event(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $event = $this->event($clan, $leader);

        $member = $this->user();
        $this->member($clan, $member);

        $response = $this->actingAs($member)
            ->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", ['body' => '  Буду!  '])
            ->assertCreated();

        $response->assertJsonPath('comment.body', 'Буду!')
            ->assertJsonPath('comment.clan_event_id', $event->id)
            ->assertJsonPath('comment.user_id', $member->id)
            ->assertJsonPath('comment.parent_id', null)
            ->assertJsonPath('comment.user.username', $member->username);

        $this->assertDatabaseHas('clan_event_comments', [
            'clan_event_id' => $event->id,
            'user_id' => $member->id,
            'body' => 'Буду!',
        ]);
    }

    public function test_comment_can_be_reply_to_same_event(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $event = $this->event($clan, $leader);
        $root = ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $leader->id,
            'body' => 'Родитель',
        ]);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", [
                'body' => 'Ответ',
                'parent_id' => $root->id,
            ])
            ->assertCreated()
            ->assertJsonPath('comment.parent_id', $root->id);
    }

    public function test_non_member_cannot_comment(): void
    {
        $clan = $this->clan($this->user());
        $event = $this->event($clan, User::find($clan->leader_id));

        $this->actingAs($this->user())
            ->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", ['body' => 'Привет'])
            ->assertForbidden();

        $this->assertDatabaseCount('clan_event_comments', 0);
    }

    public function test_comment_store_validates_payload(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $event = $this->event($clan, $leader);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", ['body' => str_repeat('b', 1001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", [
                'body' => 'ok',
                'parent_id' => 999999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');

        $this->assertDatabaseCount('clan_event_comments', 0);
    }

    public function test_comment_parent_must_belong_to_same_event(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $event = $this->event($clan, $leader);
        $otherEvent = $this->event($clan, $leader, ['title' => 'Другой ивент']);

        $foreignComment = ClanEventComment::create([
            'clan_event_id' => $otherEvent->id,
            'user_id' => $leader->id,
            'body' => 'Из другого ивента',
        ]);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", [
                'body' => 'Ответ',
                'parent_id' => $foreignComment->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Родительский комментарий из другого ивента.');

        $this->assertDatabaseCount('clan_event_comments', 1);
    }

    public function test_comment_store_returns_404_for_foreign_event(): void
    {
        $foreignClan = $this->clan($this->user());
        $foreignEvent = $this->event($foreignClan, User::find($foreignClan->leader_id));

        $leader = $this->user();
        $clan = $this->clan($leader);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/events/{$foreignEvent->id}/comments", ['body' => 'x'])
            ->assertNotFound();
    }

    public function test_comment_body_of_spaces_is_rejected(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $event = $this->event($clan, $leader);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", ['body' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->assertDatabaseCount('clan_event_comments', 0);
    }

    // ------------------------------------------------------- comments destroy

    public function test_comment_author_can_delete_own_comment(): void
    {
        $clan = $this->clan($this->user());
        $event = $this->event($clan, User::find($clan->leader_id));

        $member = $this->user();
        $this->member($clan, $member);

        $comment = ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $member->id,
            'body' => 'Свой комментарий',
        ]);

        $this->actingAs($member)
            ->deleteJson("/api/clans/{$clan->id}/events/{$event->id}/comments/{$comment->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('clan_event_comments', ['id' => $comment->id]);
    }

    public function test_leader_and_officer_can_delete_foreign_comment(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $officer = $this->user();
        $this->member($clan, $officer, 'officer');
        $member = $this->user();
        $this->member($clan, $member);

        $event = $this->event($clan, $leader);

        $first = ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $member->id,
            'body' => 'Первый',
        ]);
        $second = ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $member->id,
            'body' => 'Второй',
        ]);

        $this->actingAs($officer)
            ->deleteJson("/api/clans/{$clan->id}/events/{$event->id}/comments/{$first->id}")
            ->assertOk();

        $this->actingAs($leader)
            ->deleteJson("/api/clans/{$clan->id}/events/{$event->id}/comments/{$second->id}")
            ->assertOk();

        $this->assertDatabaseMissing('clan_event_comments', ['id' => $first->id]);
        $this->assertDatabaseMissing('clan_event_comments', ['id' => $second->id]);
    }

    public function test_plain_member_cannot_delete_foreign_comment(): void
    {
        $clan = $this->clan($this->user());
        $event = $this->event($clan, User::find($clan->leader_id));

        $author = $this->user();
        $this->member($clan, $author);
        $other = $this->user();
        $this->member($clan, $other);

        $comment = ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $author->id,
            'body' => 'Не трогать',
        ]);

        $this->actingAs($other)
            ->deleteJson("/api/clans/{$clan->id}/events/{$event->id}/comments/{$comment->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('clan_event_comments', ['id' => $comment->id]);
    }

    public function test_comment_destroy_returns_404_for_comment_of_other_event(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $event = $this->event($clan, $leader);
        $otherEvent = $this->event($clan, $leader, ['title' => 'Другой ивент']);

        $comment = ClanEventComment::create([
            'clan_event_id' => $otherEvent->id,
            'user_id' => $leader->id,
            'body' => 'Из другого ивента',
        ]);

        $this->actingAs($leader)
            ->deleteJson("/api/clans/{$clan->id}/events/{$event->id}/comments/{$comment->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('clan_event_comments', ['id' => $comment->id]);
    }

    // ------------------------------------------------------------ guest access

    public function test_guest_cannot_access_clan_events_and_comments(): void
    {
        $leader = $this->user();
        $clan = $this->clan($leader);
        $event = $this->event($clan, $leader);

        $this->getJson("/api/clans/{$clan->id}/events")->assertUnauthorized();
        $this->postJson("/api/clans/{$clan->id}/events", ['type' => 'event', 'title' => 'x'])->assertUnauthorized();
        $this->deleteJson("/api/clans/{$clan->id}/events/{$event->id}")->assertUnauthorized();
        $this->getJson("/api/clans/{$clan->id}/events/{$event->id}/comments")->assertUnauthorized();
        $this->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", ['body' => 'x'])->assertUnauthorized();
        $this->deleteJson("/api/clans/{$clan->id}/events/{$event->id}/comments/1")->assertUnauthorized();
    }
}
