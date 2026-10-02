<?php

namespace Tests\Feature;

use App\Domains\Chat\Models\Conversation;
use App\Domains\Chat\Services\ChatService;
use App\Domains\Chat\Services\PresenceService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Присутствие: явный выход перебивает активность.
 *
 * Без этого игрок, нажавший «Выйти», оставался онлайн ещё минуту:
 * выход снимал отметку присутствия, но время последней активности
 * держало его в списке, и он «возвращался» при обновлении страницы.
 */
class PresenceLeftTest extends TestCase
{
    use RefreshDatabase;

    private function presence(): PresenceService
    {
        return app(PresenceService::class);
    }

    public function test_recent_activity_means_online(): void
    {
        $user = User::factory()->create(['last_seen_at' => now()->subSeconds(10)]);

        $this->assertTrue($this->presence()->isOnline($user->id));
    }

    public function test_old_activity_means_offline(): void
    {
        $user = User::factory()->create(['last_seen_at' => now()->subSeconds(120)]);

        $this->assertFalse($this->presence()->isOnline($user->id));
    }

    public function test_explicit_leave_overrides_recent_activity(): void
    {
        $user = User::factory()->create(['last_seen_at' => now()]);

        $this->presence()->markLeft($user->id);

        $this->assertFalse(
            $this->presence()->isOnline($user->id),
            'После явного выхода игрок не должен считаться онлайн'
        );
    }

    public function test_returning_clears_the_leave_mark(): void
    {
        $user = User::factory()->create(['last_seen_at' => now()]);

        $this->presence()->markLeft($user->id);
        $this->presence()->markOnline($user->id);

        $this->assertTrue(
            $this->presence()->isOnline($user->id),
            'Вернувшийся игрок снова онлайн'
        );
    }

    public function test_leave_stops_mattering_after_the_window(): void
    {
        $user = User::factory()->create(['last_seen_at' => now()]);

        $this->presence()->markLeft($user->id);

        // Время выходит за окно активности: активность и так не считается
        $this->travel(PresenceService::ONLINE_WINDOW_SECONDS + 5)->seconds();

        $this->assertFalse($this->presence()->isOnline($user->id));
    }

    public function test_endpoint_marks_leave(): void
    {
        $user = User::factory()->create(['last_seen_at' => now()]);

        Sanctum::actingAs($user);

        $this->postJson('/api/chat/presence/offline')->assertOk();

        $this->assertFalse($this->presence()->isOnline($user->id));
    }

    public function test_filtered_query_respects_leave(): void
    {
        $leaving = User::factory()->create(['last_seen_at' => now()]);
        $staying = User::factory()->create(['last_seen_at' => now()]);

        $this->presence()->markLeft($leaving->id);

        $this->assertSame(
            [$staying->id],
            $this->presence()->onlineAmong([$leaving->id, $staying->id])
        );
    }
}
