<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanEvent;
use App\Domains\Clan\Models\ClanEventComment;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Комментарии к клан-ивентам: /api/clans/{clan}/events/{event}/comments
 */
class ClanEventCommentTest extends TestCase
{
    use RefreshDatabase;

    private function clan(User $leader): Clan
    {
        static $i = 0;
        $i++;

        $clan = Clan::create([
            'name' => "Клан {$i}",
            'tag' => "C{$i}",
            'leader_id' => $leader->id,
        ]);

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $leader->id,
            'role' => 'leader',
        ]);

        return $clan;
    }

    private function member(Clan $clan, string $role = 'member'): User
    {
        $user = User::factory()->create();

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $user;
    }

    private function event(Clan $clan, ?User $author = null, array $attributes = []): ClanEvent
    {
        return ClanEvent::create(array_merge([
            'clan_id' => $clan->id,
            'author_id' => ($author ?? User::factory()->create())->id,
            'type' => 'event',
            'title' => 'Сбор клана',
            'body' => 'В субботу в 20:00',
        ], $attributes));
    }

    public function test_guest_cannot_read_or_write_comments(): void
    {
        $clan = $this->clan(User::factory()->create());
        $event = $this->event($clan);
        $comment = ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $event->author_id,
            'body' => 'Текст',
        ]);

        $this->getJson("/api/clans/{$clan->id}/events/{$event->id}/comments")->assertUnauthorized();
        $this->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", ['body' => 'x'])->assertUnauthorized();
        $this->deleteJson("/api/clans/{$clan->id}/events/{$event->id}/comments/{$comment->id}")->assertUnauthorized();
    }

    public function test_non_member_cannot_read_or_write_comments(): void
    {
        $clan = $this->clan(User::factory()->create());
        $event = $this->event($clan);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson("/api/clans/{$clan->id}/events/{$event->id}/comments")
            ->assertForbidden();

        $this->actingAs($stranger)
            ->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", ['body' => 'я не в клане'])
            ->assertForbidden();
    }

    public function test_member_sees_top_level_comments_with_replies(): void
    {
        $leader = User::factory()->create();
        $clan = $this->clan($leader);
        $event = $this->event($clan);

        $root = ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $leader->id,
            'body' => 'Корневой',
        ]);

        ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $leader->id,
            'parent_id' => $root->id,
            'body' => 'Ответ',
        ]);

        $response = $this->actingAs($leader)
            ->getJson("/api/clans/{$clan->id}/events/{$event->id}/comments")
            ->assertOk();

        $this->assertCount(1, $response->json('comments'));
        $this->assertSame('Корневой', $response->json('comments.0.body'));
        $this->assertSame('Ответ', $response->json('comments.0.replies.0.body'));
        $this->assertArrayHasKey('username', $response->json('comments.0.user'));
    }

    public function test_member_can_create_comment_and_body_is_trimmed(): void
    {
        $leader = User::factory()->create();
        $clan = $this->clan($leader);
        $event = $this->event($clan);
        $member = $this->member($clan);

        $response = $this->actingAs($member)
            ->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", [
                'body' => '  Готов участвовать  ',
            ])
            ->assertCreated();

        $this->assertSame('Готов участвовать', $response->json('comment.body'));
        $this->assertSame($member->id, $response->json('comment.user_id'));
        $this->assertSame($member->id, $response->json('comment.user.id'));
        $this->assertNull($response->json('comment.parent_id'));

        $this->assertDatabaseHas('clan_event_comments', [
            'id' => $response->json('comment.id'),
            'clan_event_id' => $event->id,
            'body' => 'Готов участвовать',
        ]);
    }

    public function test_member_can_reply_to_comment(): void
    {
        $leader = User::factory()->create();
        $clan = $this->clan($leader);
        $event = $this->event($clan);
        $member = $this->member($clan);

        $parent = ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $leader->id,
            'body' => 'Родитель',
        ]);

        $response = $this->actingAs($member)
            ->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", [
                'body' => 'Ответ',
                'parent_id' => $parent->id,
            ])
            ->assertCreated();

        $this->assertSame($parent->id, $response->json('comment.parent_id'));
    }

    public function test_reply_to_comment_from_another_event_is_rejected(): void
    {
        $leader = User::factory()->create();
        $clan = $this->clan($leader);
        $event = $this->event($clan);
        $otherEvent = $this->event($clan, null, ['title' => 'Другой ивент']);

        $foreign = ClanEventComment::create([
            'clan_event_id' => $otherEvent->id,
            'user_id' => $leader->id,
            'body' => 'Из другого ивента',
        ]);

        $response = $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", [
                'body' => 'Ответ',
                'parent_id' => $foreign->id,
            ])
            ->assertStatus(422);

        $this->assertSame('Родительский комментарий из другого ивента.', $response->json('message'));
    }

    public function test_comment_body_validation(): void
    {
        $leader = User::factory()->create();
        $clan = $this->clan($leader);
        $event = $this->event($clan);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", ['body' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", ['body' => str_repeat('a', 1001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/events/{$event->id}/comments", ['parent_id' => 999999, 'body' => 'ok'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_author_leader_and_officer_can_delete_comment(): void
    {
        $leader = User::factory()->create();
        $clan = $this->clan($leader);
        $event = $this->event($clan);
        $officer = $this->member($clan, 'officer');
        $member = $this->member($clan);

        // Автор удаляет свой
        $own = ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $member->id,
            'body' => 'Мой комментарий',
        ]);

        $this->actingAs($member)
            ->deleteJson("/api/clans/{$clan->id}/events/{$event->id}/comments/{$own->id}")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('clan_event_comments', ['id' => $own->id]);

        // Офицер удаляет чужой
        $byOfficer = ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $member->id,
            'body' => 'Чужой',
        ]);

        $this->actingAs($officer)
            ->deleteJson("/api/clans/{$clan->id}/events/{$event->id}/comments/{$byOfficer->id}")
            ->assertOk();

        // Лидер удаляет чужой
        $byLeader = ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $member->id,
            'body' => 'Ещё один',
        ]);

        $this->actingAs($leader)
            ->deleteJson("/api/clans/{$clan->id}/events/{$event->id}/comments/{$byLeader->id}")
            ->assertOk();
    }

    public function test_regular_member_cannot_delete_foreign_comment(): void
    {
        $leader = User::factory()->create();
        $clan = $this->clan($leader);
        $event = $this->event($clan);
        $member = $this->member($clan);
        $other = $this->member($clan);

        $comment = ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $other->id,
            'body' => 'Не твоё',
        ]);

        $this->actingAs($member)
            ->deleteJson("/api/clans/{$clan->id}/events/{$event->id}/comments/{$comment->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('clan_event_comments', ['id' => $comment->id]);
    }

    public function test_comments_are_scoped_to_clan_and_event(): void
    {
        $leader = User::factory()->create();
        $clan = $this->clan($leader);
        $otherClan = $this->clan(User::factory()->create());

        $event = $this->event($clan);
        $otherEvent = $this->event($clan, null, ['title' => 'Ивент №2']);

        $comment = ClanEventComment::create([
            'clan_event_id' => $otherEvent->id,
            'user_id' => $leader->id,
            'body' => 'Комментарий',
        ]);

        // Ивент из другого клана
        $this->actingAs($leader)
            ->getJson("/api/clans/{$otherClan->id}/events/{$event->id}/comments")
            ->assertNotFound();

        // Комментарий из другого ивента
        $this->actingAs($leader)
            ->deleteJson("/api/clans/{$clan->id}/events/{$event->id}/comments/{$comment->id}")
            ->assertNotFound();
    }
}
