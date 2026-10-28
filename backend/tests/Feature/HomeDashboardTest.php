<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Панель игрока на главной: кулдауны и быстрые действия.
 */
class HomeDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/home/me')->assertUnauthorized();
    }

    public function test_player_sees_cooldowns(): void
    {
        $user = User::factory()->create(['apex_coins' => 250]);

        $this->actingAs($user)->getJson('/api/home/me')
            ->assertOk()
            ->assertJsonPath('coins', 250)
            ->assertJsonPath('chest.available', true)
            ->assertJsonPath('miner.active', false)
            ->assertJsonPath('miner.cooldown_until', null)
            ->assertJsonPath('clan', null)
            ->assertJsonPath('auction.mine', 0)
            ->assertJsonStructure(['reset_at', 'pets', 'equipped']);
    }
}
