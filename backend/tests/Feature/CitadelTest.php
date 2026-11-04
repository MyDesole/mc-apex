<?php

namespace Tests\Feature;

use App\Domains\Players\Services\WorldService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Две локации: боевые действия закрыты, пока не куплен вход в Цитадель.
 */
class CitadelTest extends TestCase
{
    use RefreshDatabase;

    private function locked(int $coins = 0): User
    {
        return User::factory()->citadelLocked()->create(['apex_coins' => $coins]);
    }

    public function test_world_reports_locked_citadel_and_price(): void
    {
        $this->actingAs($this->locked())->getJson('/api/world')
            ->assertOk()
            ->assertJsonPath('citadel.unlocked', false)
            ->assertJsonPath('citadel.price', WorldService::CITADEL_PRICE);
    }

    public function test_combat_actions_are_refused_by_the_server_until_unlocked(): void
    {
        $user = $this->locked(1_000_000);

        $this->actingAs($user)->postJson('/api/dungeons/start', ['dungeon' => 'catacombs', 'squad' => []])
            ->assertStatus(403)->assertJsonPath('code', 'citadel_locked');
        $this->actingAs($user)->postJson('/api/dungeons/buy-run')->assertStatus(403);
        $this->actingAs($user)->postJson('/api/dungeons/craft', ['material' => 'claw'])->assertStatus(403);
        $this->actingAs($user)->postJson('/api/raids', ['squad' => []])->assertStatus(403);
        $this->actingAs($user)->postJson('/api/market/sell-material', [])->assertStatus(403);
        $this->actingAs($user)->postJson('/api/market/1/buy')->assertStatus(403);
        $this->actingAs($user)->postJson('/api/talents/1/learn', ['talent' => 'w_might'])->assertStatus(403);
        $this->actingAs($user)->postJson('/api/talents/1/respec')->assertStatus(403);
        $this->actingAs($user)->postJson('/api/pets/1/battle', [])->assertStatus(403);
        $this->actingAs($user)->postJson('/api/pets/arena/buy-fight')->assertStatus(403);

        // Чтение и мирные механики Деревни открыты
        $this->actingAs($user)->getJson('/api/world')->assertOk();
        $this->actingAs($user)->getJson('/api/farm')->assertOk();
        $this->actingAs($user)->getJson('/api/quests')->assertOk();
        $this->actingAs($user)->getJson('/api/market')->assertOk();
        $this->assertSame(100000, WorldService::CITADEL_PRICE);
    }

    public function test_unlock_costs_coins_once(): void
    {
        $user = $this->locked(WorldService::CITADEL_PRICE + 100);

        $this->actingAs($user)->postJson('/api/world/citadel')
            ->assertOk()->assertJsonPath('citadel.unlocked', true);

        $this->assertSame(100, (int) $user->fresh()->apex_coins);
        $this->assertNotNull($user->fresh()->citadel_unlocked_at);

        // Второй раз монеты не списываются
        $this->actingAs($user)->postJson('/api/world/citadel')->assertStatus(422);
        $this->assertSame(100, (int) $user->fresh()->apex_coins);
    }

    public function test_unlock_needs_enough_coins(): void
    {
        $user = $this->locked(WorldService::CITADEL_PRICE - 1);

        $this->actingAs($user)->postJson('/api/world/citadel')->assertStatus(422);

        $this->assertSame(WorldService::CITADEL_PRICE - 1, (int) $user->fresh()->apex_coins);
        $this->assertNull($user->fresh()->citadel_unlocked_at);
    }

    public function test_unlocked_player_passes_the_gate(): void
    {
        $user = $this->locked(WorldService::CITADEL_PRICE);
        $this->actingAs($user)->postJson('/api/world/citadel')->assertOk();
        $user = $user->fresh();

        // Пройдя ворота, запрос упирается уже в правила самой механики, а не в 403
        $this->actingAs($user)->postJson('/api/raids', ['squad' => []])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/talents/1/learn', ['talent' => 'w_might'])->assertStatus(422);
    }
}
