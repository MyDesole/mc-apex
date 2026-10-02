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
 * Модерация клан-постов и комментариев: /api/admin/comments, /api/admin/clan-events
 */
class AdminCommentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function clan(?User $leader = null): Clan
    {
        static $i = 0;
        $i++;

        $leader ??= User::factory()->create();

        $clan = Clan::create([
            'name' => "Модер-клан {$i}",
            'tag' => "M{$i}",
            'leader_id' => $leader->id,
        ]);

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $leader->id,
            'role' => 'leader',
        ]);

        return $clan;
    }

    private function event(Clan $clan, array $attributes = []): ClanEvent
    {
        return ClanEvent::create(array_merge([
            'clan_id' => $clan->id,
            'author_id' => $clan->leader_id,
            'type' => 'announcement',
            'title' => 'Пост клана',
            'body' => 'Текст поста',
        ], $attributes));
    }

    private function comment(ClanEvent $event, array $attributes = []): ClanEventComment
    {
        return ClanEventComment::create(array_merge([
            'clan_event_id' => $event->id,
            'user_id' => $event->author_id,
            'body' => 'Комментарий',
        ], $attributes));
    }

    public function test_access_is_limited_to_staff(): void
    {
        $this->getJson('/api/admin/comments')->assertUnauthorized();
        $this->getJson('/api/admin/clan-events')->assertUnauthorized();

        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/admin/comments')->assertForbidden();
        $this->actingAs($user)->getJson('/api/admin/clan-events')->assertForbidden();

        $moderator = User::factory()->create(['role' => 'moderator']);
        $this->actingAs($moderator)->getJson('/api/admin/comments')->assertOk();
        $this->actingAs($moderator)->getJson('/api/admin/clan-events')->assertOk();

        $this->actingAs($this->admin())->getJson('/api/admin/comments')->assertOk();
    }

    public function test_comments_index_lists_with_relations_and_pagination(): void
    {
        $clan = $this->clan();
        $event = $this->event($clan);
        $this->comment($event, ['body' => 'Первый']);
        $this->comment($event, ['body' => 'Второй']);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/comments')
            ->assertOk();

        $this->assertSame(30, $response->json('per_page'));
        $this->assertSame(2, $response->json('total'));
        $this->assertArrayHasKey('user', $response->json('data.0'));
        $this->assertArrayHasKey('event', $response->json('data.0'));
        $this->assertSame($event->title, $response->json('data.0.event.title'));
        $this->assertSame($clan->name, $response->json('data.0.event.clan.name'));
    }

    public function test_comments_index_search_by_body(): void
    {
        $clan = $this->clan();
        $event = $this->event($clan);
        $wanted = $this->comment($event, ['body' => 'Ищем ротку в тексте']);
        $this->comment($event, ['body' => 'Обычный комментарий']);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/comments?search=ротку')
            ->assertOk();

        $this->assertSame([$wanted->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_admin_can_hard_delete_comment(): void
    {
        $clan = $this->clan();
        $event = $this->event($clan);
        $comment = $this->comment($event);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/comments/{$comment->id}")
            ->assertOk()
            ->assertJson(['ok' => true]);

        // Удаление жёсткое (в отличие от форума, где soft delete)
        $this->assertDatabaseMissing('clan_event_comments', ['id' => $comment->id]);
    }

    public function test_events_index_lists_with_relations_and_search(): void
    {
        $clan = $this->clan();
        $wanted = $this->event($clan, ['title' => 'Ищем ивент', 'body' => 'детали']);
        $this->event($clan, ['title' => 'Другой ивент', 'body' => 'другое']);

        $all = $this->actingAs($this->admin())
            ->getJson('/api/admin/clan-events')
            ->assertOk();

        $this->assertSame(30, $all->json('per_page'));
        $this->assertSame(2, $all->json('total'));
        $this->assertArrayHasKey('author', $all->json('data.0'));
        $this->assertArrayHasKey('clan', $all->json('data.0'));

        $byTitle = $this->actingAs($this->admin())
            ->getJson('/api/admin/clan-events?search=Ищем')
            ->assertOk();
        $this->assertSame([$wanted->id], collect($byTitle->json('data'))->pluck('id')->all());

        $byBody = $this->actingAs($this->admin())
            ->getJson('/api/admin/clan-events?search=другое')
            ->assertOk();
        $this->assertCount(1, $byBody->json('data'));
        $this->assertNotSame($wanted->id, $byBody->json('data.0.id'));
    }

    public function test_admin_can_delete_event_and_its_comments(): void
    {
        $clan = $this->clan();
        $event = $this->event($clan);
        $comment = $this->comment($event);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/clan-events/{$event->id}")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('clan_events', ['id' => $event->id]);
        $this->assertDatabaseMissing('clan_event_comments', ['id' => $comment->id]);
    }
}
