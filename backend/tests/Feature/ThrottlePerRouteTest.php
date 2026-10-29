<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Лимиты запросов: у каждого маршрута свой счётчик.
 */
class ThrottlePerRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_busy_route_does_not_eat_other_routes_limit(): void
    {
        $user = User::factory()->create(['apex_coins' => 1000]);

        // 25 копаний (лимит 120) — больше лимита «Забрать» и «Начать» (20)
        foreach (range(1, 25) as $i) {
            $this->assertNotSame(429, $this->actingAs($user)->postJson('/api/games/miner/dig', ['x' => 0, 'y' => 0])->status());
        }

        $this->assertNotSame(429, $this->actingAs($user)->postJson('/api/games/miner/cash-out')->status());
        $this->assertNotSame(429, $this->actingAs($user)->postJson('/api/games/miner/start')->status());
    }

    public function test_route_limit_still_applies(): void
    {
        $user = User::factory()->create(['apex_coins' => 0]);

        foreach (range(1, 20) as $i) {
            $this->actingAs($user)->postJson('/api/games/miner/cash-out');
        }

        $this->actingAs($user)->postJson('/api/games/miner/cash-out')->assertStatus(429);
    }
}
