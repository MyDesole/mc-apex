<?php

namespace Tests\Feature;

use App\Domains\Games\Services\MinerProgress;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Мини-игра «Шахтёр»: забег, удары, монеты и перерыв; комбо, опасности,
 * сундуки, жители, ярусы, улучшения, задания и топ недели.
 */
class MinerGameTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_digs_pays_and_cools_down(): void
    {
        $user = User::factory()->create(['apex_coins' => 0]);

        $state = $this->actingAs($user)->postJson('/api/games/miner/start')->assertOk()->json();
        $this->assertSame(24, $state['run']['energy']);
        $this->assertSame(1, $state['run']['tier']);
        // Клиенту видна только поверхность — руды ниже скрыты
        $this->assertNull($state['run']['cells'][8]);
        $this->assertNotNull($state['run']['cells'][0]);
        $this->assertContains(8, $state['run']['reach']);
        $this->assertNotContains(40, $state['run']['reach']);

        // Второй забег, пока идёт первый, — нельзя; далеко от открытых — нельзя
        $this->actingAs($user)->postJson('/api/games/miner/start')->assertStatus(422);
        $this->actingAs($user)->postJson('/api/games/miner/dig', ['cell' => 40])->assertStatus(422);

        // Подставим известную сетку: под поверхностью алмаз
        $this->setGrid($user, [8 => 'diamond', 16 => 'tnt', 25 => 'emerald'], ['coins' => 0]);

        $this->actingAs($user)->postJson('/api/games/miner/dig', ['cell' => 8])
            ->assertOk()
            ->assertJsonPath('gain', 160)
            ->assertJsonPath('run.coins', 160);

        // TNT взрывает соседей: изумруд по диагонали открывается бесплатно
        $gain = $this->actingAs($user)->postJson('/api/games/miner/dig', ['cell' => 16])
            ->assertOk()
            ->assertJsonPath('events.0.type', 'blast')
            ->json('gain');
        $this->assertGreaterThanOrEqual(250, $gain);

        $this->actingAs($user)->postJson('/api/games/miner/cash-out')
            ->assertOk()
            ->assertJsonPath('finished.coins', 160 + $gain);

        $this->assertSame(160 + $gain, (int) $user->fresh()->apex_coins);

        // Перерыв между забегами
        $this->actingAs($user)->postJson('/api/games/miner/start')->assertStatus(422);
        $this->travel(11)->minutes();
        $this->actingAs($user)->postJson('/api/games/miner/start')->assertOk();
    }

    public function test_ore_in_a_row_builds_combo_and_rock_breaks_it(): void
    {
        $user = $this->startRun();
        $this->setGrid($user, [8 => 'iron', 16 => 'gold', 24 => 'stone', 32 => 'gold']);

        $this->dig($user, 8)->assertJsonPath('gain', 22)->assertJsonPath('run.combo', 1);
        // Вторая руда подряд — ×1.05
        $this->dig($user, 16)->assertJsonPath('gain', 57)->assertJsonPath('run.combo', 2);
        $this->dig($user, 24)->assertJsonPath('run.combo', 0)->assertJsonPath('events.0.type', 'combo_lost');
        $this->dig($user, 32)->assertJsonPath('gain', 54);
    }

    public function test_lava_burns_strikes_and_gravel_collapses_or_opens_cave(): void
    {
        $user = $this->startRun();
        $this->setGrid($user, [8 => 'lava', 9 => 'gravel']);

        $this->dig($user, 8)->assertJsonPath('run.energy', 21)->assertJsonPath('events.0.type', 'lava');

        $res = $this->dig($user, 9)->json();
        $type = collect($res['events'])->pluck('type')->first(fn ($t) => in_array($t, ['cave', 'collapse'], true));
        // Обвал съедает лишний удар, пещера открывает соседей
        $this->assertSame($type === 'collapse' ? 19 : 20, $res['run']['energy']);
        if ($type === 'cave') {
            $this->assertNotNull($res['run']['cells'][17]);
        }
    }

    public function test_bedrock_is_shown_for_free_and_netherite_breaks_it_on_second_hit(): void
    {
        $user = $this->startRun();
        $this->setGrid($user, [8 => 'bedrock']);

        $this->dig($user, 8)
            ->assertJsonPath('run.energy', 24)
            ->assertJsonPath('run.cells.8', 'bedrock')
            ->assertJsonPath('events.0.type', 'wall');
        $this->assertNotContains(8, $this->dig($user, 9)->json('run.reach'));

        // Незеритовая кирка: первый удар показывает, второй раскалывает за 3 удара
        $other = User::factory()->create();
        DB::table('miner_profiles')->insert(['user_id' => $other->id, 'pickaxe' => 5, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($other)->postJson('/api/games/miner/start')->assertOk()->assertJsonPath('run.energy', 29);
        $this->setGrid($other, [8 => 'bedrock']);

        $this->dig($other, 8)->assertJsonPath('run.energy', 29);
        $this->dig($other, 8)->assertJsonPath('run.energy', 26)->assertJsonPath('events.0.type', 'wall_break');
    }

    public function test_chest_pays_and_mimic_bites(): void
    {
        $user = $this->startRun();
        $this->setGrid($user, [8 => 'chest', 9 => 'mimic', 10 => 'diamond'], ['coins' => 0]);

        // Мимик до открытия выглядит как сундук
        $this->dig($user, 9)->assertJsonPath('run.cells.9', 'chest')->assertJsonPath('run.chests.9', 'closed');
        $this->dig($user, 10);
        $this->dig($user, 8);

        $coins = $this->actingAs($user)->postJson('/api/games/miner/chest', ['cell' => 8])
            ->assertOk()
            ->assertJsonPath('run.chests.8', 'open')
            ->json('run.coins');
        $this->assertGreaterThanOrEqual(160 + 30, $coins);
        $this->assertLessThanOrEqual(160 + 80, $coins);

        $this->actingAs($user)->postJson('/api/games/miner/chest', ['cell' => 9])
            ->assertOk()
            ->assertJsonPath('run.cells.9', 'mimic')
            ->assertJsonPath('events.0.type', 'mimic')
            ->assertJsonPath('run.coins', $coins - (int) floor($coins * 0.3));

        // Открытый сундук второй раз не открыть
        $this->actingAs($user)->postJson('/api/games/miner/chest', ['cell' => 8])->assertStatus(422);
    }

    public function test_villager_sells_strikes_for_a_share_of_the_haul(): void
    {
        $user = $this->startRun();
        $this->setGrid($user, [8 => 'emerald', 16 => 'villager'], ['coins' => 0]);

        $this->dig($user, 8);
        $this->dig($user, 16)->assertJsonPath('run.villagers.16.status', 'open');

        // 25% от 250, но не меньше 30
        $this->actingAs($user)->postJson('/api/games/miner/trade', ['cell' => 16, 'offer' => 'strikes'])
            ->assertOk()
            ->assertJsonPath('run.energy', 27)
            ->assertJsonPath('run.coins', 250 - 62)
            ->assertJsonPath('run.villagers.16.status', 'used');

        $this->actingAs($user)->postJson('/api/games/miner/trade', ['cell' => 16, 'offer' => 'map'])->assertStatus(422);
    }

    public function test_descend_to_the_next_tier_costs_strikes(): void
    {
        $user = $this->startRun();
        $this->setGrid($user, []);

        $this->actingAs($user)->postJson('/api/games/miner/descend')->assertStatus(422);

        // Колодец до дна по первому столбцу
        DB::table('miner_runs')->where('user_id', $user->id)->update(['opened' => json_encode([...range(0, 7), 8, 16, 24, 32, 40, 48])]);

        $res = $this->actingAs($user)->postJson('/api/games/miner/descend')
            ->assertOk()
            ->assertJsonPath('run.tier', 2)
            ->assertJsonPath('run.tier_name', 'Глубинный сланец')
            ->assertJsonPath('run.energy', 20)
            ->json('run');

        // Новый ярус: открыта только площадка из сланца
        $this->assertSame(array_fill(0, 8, 'deepslate'), array_slice($res['cells'], 0, 8));
        $this->assertNull($res['cells'][8]);
    }

    public function test_dynamite_blasts_a_cross_without_a_strike(): void
    {
        $user = User::factory()->create();
        DB::table('miner_profiles')->insert(['user_id' => $user->id, 'dynamite' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->postJson('/api/games/miner/start')->assertOk()->assertJsonPath('run.dynamite', 1);
        $this->setGrid($user, [9 => 'gold', 8 => 'iron', 10 => 'iron', 17 => 'coal', 16 => 'diamond']);

        $res = $this->actingAs($user)->postJson('/api/games/miner/blast', ['cell' => 9])
            ->assertOk()
            ->assertJsonPath('run.energy', 24)
            ->assertJsonPath('run.dynamite', 0)
            ->json('run');

        foreach ([9, 8, 10, 17] as $cell) {
            $this->assertNotNull($res['cells'][$cell]);
        }
        // Крест, а не квадрат: диагональ осталась закрытой
        $this->assertNull($res['cells'][16]);

        $this->actingAs($user)->postJson('/api/games/miner/blast', ['cell' => 16])->assertStatus(422);
    }

    public function test_upgrades_cost_coins_and_apply_to_next_run(): void
    {
        $user = User::factory()->create(['apex_coins' => 1500]);

        $this->actingAs($user)->postJson('/api/games/miner/upgrade', ['upgrade' => 'pickaxe'])
            ->assertOk()
            ->assertJsonPath('upgrades.pickaxe.level', 1)
            ->assertJsonPath('upgrades.pickaxe.price', 2000)
            ->assertJsonPath('energy', 25)
            ->assertJsonPath('balance', 700);

        // Не хватает монет — ничего не списано
        $this->actingAs($user)->postJson('/api/games/miner/upgrade', ['upgrade' => 'cart'])->assertStatus(422);
        $this->assertSame(700, (int) $user->fresh()->apex_coins);
        $this->actingAs($user)->postJson('/api/games/miner/upgrade', ['upgrade' => 'jetpack'])->assertStatus(422);

        DB::table('miner_profiles')->where('user_id', $user->id)->update(['cart' => 2]);
        $this->actingAs($user)->postJson('/api/games/miner/start')->assertOk()->assertJsonPath('run.energy', 25);
        $this->actingAs($user)->postJson('/api/games/miner/cash-out')->assertOk()->assertJsonPath('cooldown', 570);

        // Вагонетка 2 ур.: 570 с вместо 600
        $this->travel(580)->seconds();
        $this->actingAs($user)->postJson('/api/games/miner/start')->assertOk();
    }

    public function test_lantern_lights_only_around_the_last_hit_and_never_leaks_ore(): void
    {
        $user = User::factory()->create();
        DB::table('miner_profiles')->insert(['user_id' => $user->id, 'lantern' => 2, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->postJson('/api/games/miner/start')->assertOk();
        $this->setGrid($user, [8 => 'stone', 16 => 'lava', 9 => 'bedrock', 17 => 'diamond', 14 => 'lava']);

        $hints = $this->dig($user, 8)->json('run.hints');

        $this->assertSame('danger', $hints['16']);
        $this->assertSame('wall', $hints['9']);
        // Руду фонарь не выдаёт, а дальняя лава — вне круга света
        $this->assertArrayNotHasKey('17', $hints);
        $this->assertArrayNotHasKey('14', $hints);
    }

    public function test_relic_goes_to_collection_and_adds_bonus(): void
    {
        $user = $this->startRun();
        $this->setGrid($user, [8 => 'relic', 16 => 'gold'], ['coins' => 0]);

        $this->dig($user, 8)->assertJsonPath('events.0.type', 'relic')->assertJsonPath('events.0.new', true);
        $this->dig($user, 16);

        $state = $this->actingAs($user)->postJson('/api/games/miner/cash-out')
            ->assertOk()
            // 54 + 1% за артефакт
            ->assertJsonPath('finished.mined', 54)
            ->assertJsonPath('finished.artifact_bonus', 0)
            ->json();

        $this->assertSame(1, collect($state['artifacts']['items'])->sum('count'));
        $this->assertSame(0.01, $state['artifacts']['bonus']);
    }

    public function test_daily_quests_progress_and_pay_once(): void
    {
        $user = User::factory()->create(['apex_coins' => 0]);
        DB::table('miner_profiles')->insert([
            'user_id' => $user->id,
            'quests' => json_encode(['date' => today()->toDateString(), 'items' => [
                ['id' => 'diamonds', 'progress' => 0, 'claimed' => false],
                ['id' => 'runs', 'progress' => 0, 'claimed' => false],
                ['id' => 'trade', 'progress' => 0, 'claimed' => false],
            ]]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->postJson('/api/games/miner/start')->assertOk();
        $this->setGrid($user, [8 => 'diamond', 16 => 'diamond', 24 => 'diamond'], ['coins' => 0]);

        $this->actingAs($user)->postJson('/api/games/miner/quests/diamonds/claim')->assertStatus(422);

        foreach ([8, 16, 24] as $cell) {
            $this->dig($user, $cell);
        }

        $this->actingAs($user)->postJson('/api/games/miner/quests/diamonds/claim')
            ->assertOk()
            ->assertJsonPath('reward', 300)
            ->assertJsonPath('quests.0.claimed', true);
        $this->actingAs($user)->postJson('/api/games/miner/quests/diamonds/claim')->assertStatus(422);
        $this->actingAs($user)->postJson('/api/games/miner/quests/nether/claim')->assertStatus(422);

        $this->actingAs($user)->postJson('/api/games/miner/cash-out')->assertOk()->assertJsonPath('quests.1.progress', 1);
        $this->assertGreaterThanOrEqual(300, (int) $user->fresh()->apex_coins);
    }

    public function test_weekly_top_shows_best_runs_and_pays_prizes_once(): void
    {
        $users = User::factory()->count(4)->create(['apex_coins' => 0]);
        $lastWeek = now()->subWeek()->startOfWeek()->addDay();

        foreach ([900, 500, 700, 100] as $i => $coins) {
            DB::table('miner_runs')->insert([
                'user_id' => $users[$i]->id,
                'grid' => '[]',
                'opened' => '[]',
                'energy' => 0,
                'coins' => $coins,
                'status' => 'finished',
                'finished_at' => $lastWeek,
                'created_at' => $lastWeek,
                'updated_at' => $lastWeek,
            ]);
        }

        $this->assertSame(3, app(MinerProgress::class)->awardWeek());
        $this->assertSame(0, app(MinerProgress::class)->awardWeek());

        $this->assertSame(6000, (int) $users[0]->fresh()->apex_coins);
        $this->assertSame(2000, (int) $users[1]->fresh()->apex_coins);
        $this->assertSame(4000, (int) $users[2]->fresh()->apex_coins);
        $this->assertSame(0, (int) $users[3]->fresh()->apex_coins);

        // Текущая неделя: прошлые забеги не в счёт
        $this->actingAs($users[0])->getJson('/api/games/miner')
            ->assertOk()
            ->assertJsonCount(0, 'top.rows')
            ->assertJsonPath('top.me.place', null);
    }

    public function test_gold_rush_hours_are_the_same_for_everyone(): void
    {
        $hours = MinerProgress::rushHours('2026-10-29');

        $this->assertCount(3, $hours);
        $this->assertSame($hours, MinerProgress::rushHours('2026-10-29'));
        $this->assertTrue(collect($hours)->every(fn ($h) => $h >= 12 && $h <= 23));

        $this->assertTrue(MinerProgress::rush(now()->setDate(2026, 10, 29)->setTime($hours[0], 30))['active']);
        $this->assertFalse(MinerProgress::rush(now()->setDate(2026, 10, 29)->setTime(9, 0))['active']);
    }

    private function startRun(): User
    {
        $user = User::factory()->create(['apex_coins' => 0]);
        $this->actingAs($user)->postJson('/api/games/miner/start')->assertOk();

        return $user;
    }

    /** Подменить сетку забега: всё — камень, кроме заданных клеток. */
    private function setGrid(User $user, array $blocks, array $extra = []): void
    {
        $grid = array_fill(0, 56, 'stone');
        foreach ($blocks as $cell => $block) {
            $grid[$cell] = $block;
        }

        DB::table('miner_runs')->where('user_id', $user->id)->where('status', 'active')
            ->update(['grid' => json_encode($grid)] + $extra);
    }

    private function dig(User $user, int $cell)
    {
        return $this->actingAs($user)->postJson('/api/games/miner/dig', ['cell' => $cell])->assertOk();
    }
}
