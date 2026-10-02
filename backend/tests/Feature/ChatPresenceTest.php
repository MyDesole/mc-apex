<?php

namespace Tests\Feature;

use App\Domains\Chat\Services\PresenceService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Статус «онлайн».
 *
 * Источников два: presence-канал отмечает вход и выход, а время последней
 * активности служит запасным вариантом. Вместе они дают статус, который
 * работает и при недоступном Reverb.
 */
class ChatPresenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_player_is_offline_at_start(): void
    {
        $user = User::factory()->create();

        $this->assertSame([], app(PresenceService::class)->onlineUserIds());
        $this->assertFalse(app(PresenceService::class)->isOnline($user->id));
    }

    public function test_activity_marks_player_online(): void
    {
        $user = User::factory()->create();

        /* Активность отмечает middleware на любом запросе */
        $this->actingAs($user)->getJson('/api/chat/unread-count')->assertOk();

        $this->assertTrue(
            app(PresenceService::class)->isOnline($user->id),
            'после запроса игрок считается онлайн',
        );
    }

    public function test_stale_activity_means_offline(): void
    {
        $user = User::factory()->create();

        /* Активность была давно */
        $user->forceFill([
            'last_seen_at' => now()->subMinutes(PresenceService::ONLINE_WINDOW_SECONDS / 60 + 5),
        ])->save();

        $this->assertFalse(
            app(PresenceService::class)->isOnline($user->id),
            'давняя активность не считается онлайном',
        );
    }

    public function test_presence_channel_marks_online_and_offline(): void
    {
        $user = User::factory()->create();
        $presence = app(PresenceService::class);

        $this->actingAs($user)->postJson('/api/chat/presence/online')->assertOk();

        $this->assertTrue($presence->isOnline($user->id), 'вход отмечен');

        $this->actingAs($user)->postJson('/api/chat/presence/offline')->assertOk();

        /* Активность того же запроса ещё свежая, поэтому смотрим только канал */
        $this->assertNotContains(
            $user->id,
            (function () {
                $reflection = new \ReflectionMethod(PresenceService::class, 'fromPresenceChannel');
                $reflection->setAccessible(true);

                return $reflection->invoke(app(PresenceService::class));
            })(),
            'выход отмечен',
        );
    }

    public function test_endpoint_returns_only_requested_players(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $third = User::factory()->create();

        $presence = app(PresenceService::class);
        $presence->markOnline($other->id);

        $response = $this->actingAs($me)
            ->getJson('/api/chat/presence?user_ids=' . $other->id . ',' . $third->id)
            ->assertOk();

        $this->assertContains($other->id, $response->json('online'));
        $this->assertNotContains($third->id, $response->json('online'));
    }

    public function test_endpoint_returns_everyone_without_filter(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        app(PresenceService::class)->markOnline($other->id);

        $response = $this->actingAs($me)->getJson('/api/chat/presence')->assertOk();

        $this->assertContains($other->id, $response->json('online'));
    }

    public function test_guest_cannot_read_presence(): void
    {
        $this->getJson('/api/chat/presence')->assertUnauthorized();
    }
}
