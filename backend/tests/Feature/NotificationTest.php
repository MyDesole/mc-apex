<?php

namespace Tests\Feature;

use App\Http\Controllers\NotificationController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Кладём уведомление напрямую в таблицу notifications,
     * чтобы тест не зависел от конкретного класса Notification.
     */
    private function notify(User $user, int $count = 1, ?string $title = null): void
    {
        for ($i = 0; $i < $count; $i++) {
            DatabaseNotification::create([
                'id' => (string) Str::uuid(),
                'type' => 'App\Notifications\TestNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => ['title' => $title ?? "Уведомление {$i}"],
                'read_at' => null,
            ]);
        }
    }

    public function test_guest_cannot_touch_notifications(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->postJson('/api/notifications/read-all')->assertUnauthorized();
        $this->postJson('/api/notifications/' . Str::uuid() . '/read')->assertUnauthorized();
    }

    public function test_index_lists_only_own_notifications_with_unread_count(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->notify($user, 2);
        $this->notify($other, 3);

        $response = $this->actingAs($user)->getJson('/api/notifications')->assertOk();

        $this->assertCount(2, $response->json('notifications.data'));
        $this->assertSame(2, $response->json('unread_count'));
        $this->assertSame(['title' => 'Уведомление 0'], $response->json('notifications.data.0.data'));
    }

    public function test_index_returns_newest_first(): void
    {
        $user = User::factory()->create();

        $this->notify($user, 1, 'Старое');
        $old = DatabaseNotification::where('notifiable_id', $user->id)->firstOrFail();
        $old->forceFill(['created_at' => now()->subDay()])->save();

        $this->notify($user, 1, 'Новое');

        $response = $this->actingAs($user)->getJson('/api/notifications')->assertOk();

        $this->assertSame('Новое', $response->json('notifications.data.0.data.title'));
        $this->assertSame('Старое', $response->json('notifications.data.1.data.title'));
    }

    public function test_index_paginates_twenty_per_page(): void
    {
        $user = User::factory()->create();
        $this->notify($user, 25);

        $response = $this->actingAs($user)->getJson('/api/notifications')->assertOk();

        $this->assertCount(20, $response->json('notifications.data'));
        $this->assertSame(25, $response->json('notifications.total'));
        $this->assertSame(25, $response->json('unread_count'));
    }

    public function test_mark_single_notification_as_read_returns_fresh_counter(): void
    {
        $user = User::factory()->create();
        $this->notify($user, 3);

        $id = DatabaseNotification::where('notifiable_id', $user->id)->firstOrFail()->id;

        $response = $this->actingAs($user)
            ->postJson("/api/notifications/{$id}/read")
            ->assertOk();

        $this->assertTrue($response->json('ok'));
        $this->assertSame(2, $response->json('unread_count'));
        $this->assertNotNull(DatabaseNotification::find($id)->read_at);

        // Повторное прочтение — счётчик не уходит в минус
        $again = $this->actingAs($user)
            ->postJson("/api/notifications/{$id}/read")
            ->assertOk();

        $this->assertSame(2, $again->json('unread_count'));

        // Список тоже отдаёт актуальный счётчик
        $list = $this->actingAs($user)->getJson('/api/notifications')->assertOk();
        $this->assertSame(2, $list->json('unread_count'));
    }

    public function test_cannot_read_someone_elses_notification(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->notify($other, 1);
        $foreignId = DatabaseNotification::where('notifiable_id', $other->id)->value('id');

        $this->actingAs($user)
            ->postJson("/api/notifications/{$foreignId}/read")
            ->assertNotFound();

        $this->assertNull(DatabaseNotification::find($foreignId)->read_at);
    }

    public function test_mark_unknown_notification_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/notifications/' . Str::uuid() . '/read')
            ->assertNotFound();

        $this->actingAs($user)
            ->postJson('/api/notifications/not-a-uuid/read')
            ->assertNotFound();
    }

    public function test_mark_all_as_read_reports_marked_count_and_zero_unread(): void
    {
        $user = User::factory()->create();
        $this->notify($user, 3);

        $response = $this->actingAs($user)
            ->postJson('/api/notifications/read-all')
            ->assertOk();

        $this->assertTrue($response->json('ok'));
        $this->assertSame(0, $response->json('unread_count'));
        $this->assertSame(3, $response->json('marked'));

        $this->assertSame(0, DatabaseNotification::whereNull('read_at')->count());

        // Список после этого — 0 непрочитанных
        $list = $this->actingAs($user)->getJson('/api/notifications')->assertOk();
        $this->assertSame(0, $list->json('unread_count'));

        // Повторный вызов ничего не помечает
        $again = $this->actingAs($user)
            ->postJson('/api/notifications/read-all')
            ->assertOk();

        $this->assertSame(0, $again->json('marked'));
        $this->assertSame(0, $again->json('unread_count'));
    }

    public function test_mark_all_as_read_does_not_touch_other_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->notify($user, 2);
        $this->notify($other, 2);

        $this->actingAs($user)->postJson('/api/notifications/read-all')->assertOk();

        $this->assertSame(2, DatabaseNotification::whereNull('read_at')->count());
        $this->assertSame(2, $other->unreadNotifications()->count());
    }

    public function test_read_one_then_all_read_counts_add_up(): void
    {
        $user = User::factory()->create();
        $this->notify($user, 4);

        $ids = DatabaseNotification::where('notifiable_id', $user->id)->pluck('id');

        $this->actingAs($user)
            ->postJson("/api/notifications/{$ids[0]}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 3);

        $this->actingAs($user)
            ->postJson("/api/notifications/{$ids[1]}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 2);

        $all = $this->actingAs($user)
            ->postJson('/api/notifications/read-all')
            ->assertOk();

        $this->assertSame(2, $all->json('marked'));
        $this->assertSame(0, $all->json('unread_count'));
    }

    /**
     * Регрессия на недавний баг: счётчик непрочитанных считался по
     * закешированной ленивой связи внутри одного запроса, из-за чего после
     * «прочитать всё» бейдж не пропадал до перезагрузки страницы.
     * Здесь контроллер вызывается дважды с одним и тем же инстансом юзера.
     */
    public function test_unread_counter_is_not_stale_within_same_request_lifecycle(): void
    {
        $user = User::factory()->create();
        $this->notify($user, 3);

        $controller = app(NotificationController::class);

        $request = Request::create('/api/notifications', 'GET');
        $request->setUserResolver(fn () => $user);

        $before = $controller->index($request)->getData(true);
        $this->assertSame(3, $before['unread_count']);

        $marked = $controller->markAllAsRead($request)->getData(true);
        $this->assertSame(0, $marked['unread_count']);
        $this->assertSame(3, $marked['marked']);

        $after = $controller->index($request)->getData(true);
        $this->assertSame(0, $after['unread_count']);
    }
}
