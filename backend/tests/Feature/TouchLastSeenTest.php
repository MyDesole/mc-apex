<?php

namespace Tests\Feature;

use App\Domains\Chat\Services\PresenceService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Отметка последней активности.
 *
 * Запись редкая, но чаще окна онлайна: иначе активный игрок
 * периодически выпадал из окна и «мигал» оффлайн.
 */
class TouchLastSeenTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_is_refreshed_well_within_online_window(): void
    {
        $user = User::factory()->create(['last_seen_at' => now()->subSeconds(30)]);

        Sanctum::actingAs($user);

        $this->getJson('/api/chat/unread-count')->assertOk();

        $this->assertTrue($user->fresh()->last_seen_at->greaterThan(now()->subSeconds(5)));
    }

    public function test_frequent_requests_do_not_write_every_time(): void
    {
        $seen = now()->subSeconds(5)->startOfSecond();
        $user = User::factory()->create(['last_seen_at' => $seen]);

        Sanctum::actingAs($user);

        $this->getJson('/api/chat/unread-count')->assertOk();

        $this->assertTrue($user->fresh()->last_seen_at->equalTo($seen));
    }

    public function test_active_player_never_drops_out_between_writes(): void
    {
        // Худший случай: последняя запись была ровно перед порогом
        $user = User::factory()->create(['last_seen_at' => now()->subSeconds(19)]);

        $this->assertTrue(app(PresenceService::class)->isOnline($user->id));
    }
}
