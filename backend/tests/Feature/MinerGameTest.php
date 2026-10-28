<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Мини-игра «Шахтёр»: забег, удары, монеты и перерыв.
 */
class MinerGameTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_digs_pays_and_cools_down(): void
    {
        $user = User::factory()->create(['apex_coins' => 0]);

        $state = $this->actingAs($user)->postJson('/api/games/miner/start')->assertOk()->json();
        $this->assertSame(24, $state['run']['energy']);
        // Клиенту видна только поверхность — руды ниже скрыты
        $this->assertNull($state['run']['cells'][8]);
        $this->assertNotNull($state['run']['cells'][0]);

        // Второй забег, пока идёт первый, — нельзя; далеко от открытых — нельзя
        $this->actingAs($user)->postJson('/api/games/miner/start')->assertStatus(422);
        $this->actingAs($user)->postJson('/api/games/miner/dig', ['cell' => 40])->assertStatus(422);

        // Подставим известную сетку: под поверхностью алмаз
        $run = DB::table('miner_runs')->where('user_id', $user->id)->first();
        $grid = array_fill(0, 56, 'stone');
        $grid[8] = 'diamond';
        $grid[16] = 'tnt';
        $grid[25] = 'emerald';
        DB::table('miner_runs')->where('id', $run->id)->update(['grid' => json_encode($grid), 'coins' => 0]);

        $this->actingAs($user)->postJson('/api/games/miner/dig', ['cell' => 8])
            ->assertOk()
            ->assertJsonPath('gain', 350)
            ->assertJsonPath('run.coins', 350);

        // TNT взрывает соседей: изумруд по диагонали открывается бесплатно
        $gain = $this->actingAs($user)->postJson('/api/games/miner/dig', ['cell' => 16])
            ->assertOk()
            ->json('gain');
        $this->assertGreaterThanOrEqual(550, $gain);

        $this->actingAs($user)->postJson('/api/games/miner/cash-out')
            ->assertOk()
            ->assertJsonPath('finished.coins', 350 + $gain);

        $this->assertSame(350 + $gain, (int) $user->fresh()->apex_coins);

        // Перерыв между забегами
        $this->actingAs($user)->postJson('/api/games/miner/start')->assertStatus(422);
        $this->travel(3)->minutes();
        $this->actingAs($user)->postJson('/api/games/miner/start')->assertOk();
    }
}
