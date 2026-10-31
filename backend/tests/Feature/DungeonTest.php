<?php

namespace Tests\Feature;

use App\Domains\Players\Services\PetGearService;
use App\Domains\Players\Services\PetService;
use App\Domains\Players\Support\DungeonBattle;
use App\Domains\Players\Support\Dungeons;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use Database\Seeders\DungeonGearSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Подземелья: пошаговый бой, стихии и командные способности, связки,
 * босс в конце этажа, добыча и экипировка для арены.
 */
class DungeonTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DungeonGearSeeder::class);

        $this->user = User::factory()->create(['apex_coins' => 0]);
    }

    private function pet(User $owner, string $species = 'fox', int $xp = 600): UserInventory
    {
        $item = ShopItem::firstOrCreate(['slug' => "pet-{$species}"], [
            'name' => "Питомец {$species}", 'type' => 'profile_pet', 'effect_value' => $species,
            'price' => 1000, 'is_active' => true, 'rarity' => 'rare',
        ]);

        $row = UserInventory::create(['user_id' => $owner->id, 'shop_item_id' => $item->id, 'quantity' => 1]);
        $row->forceFill(['pet_xp' => $xp])->save();

        return $row->fresh();
    }

    private function gear(User $owner, string $slug): UserInventory
    {
        $id = app(PetGearService::class)->grant($owner, $slug);

        $this->assertNotNull($id, "предмет {$slug} должен быть в базе");

        return UserInventory::find($id);
    }

    /** Поход из двух питомцев указанных видов. */
    private function startRun(array $species = ['fox', 'cat'], string $dungeon = 'catacombs', int $xp = 600): array
    {
        $a = $this->pet($this->user, $species[0], $xp);
        $b = $this->pet($this->user, $species[1], $xp);

        return $this->actingAs($this->user)
            ->postJson('/api/dungeons/start', ['dungeon' => $dungeon, 'squad' => [$a->id, $b->id]])
            ->assertCreated()
            ->json();
    }

    /** Войти в зал и вернуть состояние боя. */
    private function enter(array $run): array
    {
        return $this->actingAs($this->user)
            ->postJson("/api/dungeons/{$run['id']}/advance")
            ->assertOk()
            ->json();
    }

    /** Подменить состояние похода в базе. */
    private function patchState(int $runId, callable $mutator): void
    {
        $state = json_decode(DB::table('dungeon_runs')->where('id', $runId)->value('state'), true);
        $state = $mutator($state);
        DB::table('dungeon_runs')->where('id', $runId)->update(['state' => json_encode($state)]);
    }

    public function test_overview_exposes_elements_abilities_and_combos(): void
    {
        $this->pet($this->user, 'axolotl');

        $data = $this->actingAs($this->user)
            ->getJson('/api/dungeons')
            ->assertOk()
            ->assertJsonCount(5, 'dungeons')
            ->assertJsonCount(2, 'dungeons.0.bosses')
            ->assertJsonStructure(['dungeons' => [['key', 'name', 'emblem', 'tone', 'elements', 'bosses', 'rooms', 'available']]])
            ->assertJsonPath('runs_left', Dungeons::DAILY_RUNS)
            ->json();

        $pet = $data['pets'][0];

        $this->assertSame('water', $pet['element']);
        $this->assertSame('Цунами', $pet['ability']['ultimate']['name']);
        $this->assertNotEmpty($data['reference']['combos']);
        $this->assertArrayHasKey('steam', $data['reference']['combos']);
        $this->assertSame('nature', $data['reference']['combos']['steam']['counters']);
        $this->assertNotEmpty($data['features']);
    }

    public function test_squad_needs_at_least_two_pets(): void
    {
        $one = $this->pet($this->user);

        $this->actingAs($this->user)
            ->postJson('/api/dungeons/start', ['dungeon' => 'catacombs', 'squad' => [$one->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('squad');
    }

    public function test_low_level_squad_is_rejected(): void
    {
        $a = $this->pet($this->user, 'fox', 0);
        $b = $this->pet($this->user, 'cat', 0);

        $this->actingAs($this->user)
            ->postJson('/api/dungeons/start', ['dungeon' => 'forge', 'squad' => [$a->id, $b->id]])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'не дорос'));
    }

    public function test_enter_room_starts_turn_based_battle(): void
    {
        $run = $this->startRun();

        $this->assertSame('idle', $run['phase']);
        $this->assertNotNull($run['pending'], 'противника видно до боя');
        $this->assertNull($run['battle']);

        $battle = $this->enter($run);

        $this->assertSame('battle', $battle['phase']);
        $this->assertSame(1, $battle['battle']['round']);
        $this->assertCount(2, $battle['battle']['units']);
        $this->assertSame($battle['battle']['foe']['hp'], $battle['battle']['foe']['max']);
        $this->assertNotEmpty($battle['battle']['foe']['trait_name']);
        $this->assertNotEmpty($battle['battle']['order'], 'очередь хода нужна интерфейсу');
    }

    public function test_move_resolves_round_and_damages_both_sides(): void
    {
        // Питомцы первого уровня: бой длится дольше одного раунда
        $run = $this->startRun(['fox', 'cat'], 'catacombs', 0);
        $battle = $this->enter($run);

        $foeHpBefore = $battle['battle']['foe']['hp'];

        $after = $this->actingAs($this->user)
            ->postJson("/api/dungeons/{$run['id']}/move", ['moves' => []])
            ->assertOk()
            ->json();

        $this->assertSame(2, $after['battle']['round'], 'раунд сдвинулся');
        $this->assertLessThan($foeHpBefore, $after['battle']['foe']['hp'], 'враг получил урон');
        $this->assertNotEmpty($after['battle']['log'], 'в бою есть лог раунда');

        $unitHp = array_sum(array_column($after['battle']['units'], 'hp'));
        $this->assertGreaterThan(0, $unitHp, 'отряд ещё жив на первом раунде');
    }

    public function test_move_without_battle_is_rejected(): void
    {
        $run = $this->startRun();

        $this->actingAs($this->user)
            ->postJson("/api/dungeons/{$run['id']}/move", ['moves' => []])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Бой ещё не начат.');
    }

    public function test_element_combo_gives_super_counter(): void
    {
        // Вода + огонь = «Пар», контрит природу
        $run = $this->startRun(['axolotl', 'dragon']);
        $battle = $this->enter($run);

        $this->assertSame('steam', $battle['battle']['combo']['key']);
        $this->assertSame('nature', $battle['battle']['combo']['counters']);
    }

    /** Поход из трёх питомцев: у трёх стихий сразу три связки. */
    private function startTrio(array $species): array
    {
        $ids = array_map(fn (string $s) => $this->pet($this->user, $s)->id, $species);

        return $this->actingAs($this->user)
            ->postJson('/api/dungeons/start', ['dungeon' => 'catacombs', 'squad' => $ids])
            ->assertCreated()
            ->json();
    }

    public function test_combo_prefers_the_one_countering_the_foe(): void
    {
        /*
         * Вода + огонь + природа дают три связки: Пар (контрит природу),
         * Пепел (тень) и Роса (огонь). Против природы должен выбраться Пар.
         */
        $run = $this->startTrio(['axolotl', 'dragon', 'frog']);

        $this->patchState($run['id'], function (array $state) {
            $state['pending']['element'] = 'nature';

            return $state;
        });

        $battle = $this->enter($run);

        $this->assertSame('steam', $battle['battle']['combo']['key']);
        $this->assertSame('nature', $battle['battle']['combo']['counters']);
    }

    public function test_combo_switches_when_the_foe_element_changes(): void
    {
        // Вода + природа + тень: Глубина (тень), Роса (огонь), Тлен (зверь)
        $run = $this->startTrio(['axolotl', 'frog', 'ghost']);

        $this->patchState($run['id'], function (array $state) {
            $state['pending']['element'] = 'shadow';

            return $state;
        });

        $battle = $this->enter($run);

        $this->assertSame('abyss', $battle['battle']['combo']['key'], 'против тени — Глубина');
        $this->assertSame('shadow', $battle['battle']['combo']['counters']);
    }

    public function test_all_pairs_have_a_counter(): void
    {
        // Десять пар: у каждой стихии ровно две контры
        $counters = array_count_values(array_column(\App\Domains\Players\Support\DungeonBattle::COMBOS, 'counters'));

        $this->assertCount(10, \App\Domains\Players\Support\DungeonBattle::COMBOS);
        $this->assertSame(2, $counters['fire']);
        $this->assertSame(2, $counters['water']);
        $this->assertSame(2, $counters['nature']);
        $this->assertSame(2, $counters['shadow']);
        $this->assertSame(2, $counters['beast']);
    }

    public function test_water_ultimate_heals_the_whole_squad(): void
    {
        $run = $this->startRun(['axolotl', 'cat']);
        $battle = $this->enter($run);

        // Раним отряд и даём воде полную ярость
        $this->patchState($run['id'], function (array $state) {
            foreach ($state['battle']['units'] as $i => $unit) {
                $state['battle']['units'][$i]['hp'] = (int) round($unit['max'] * 0.4);
                $state['battle']['units'][$i]['rage'] = $unit['element'] === 'water' ? DungeonBattle::RAGE_MAX : 0;
            }

            return $state;
        });

        $moves = [];
        foreach ($battle['battle']['units'] as $unit) {
            $moves[(string) $unit['id']] = $unit['element'] === 'water' ? 'ultimate' : 'defend';
        }

        $after = $this->actingAs($this->user)
            ->postJson("/api/dungeons/{$run['id']}/move", ['moves' => $moves])
            ->assertOk()
            ->json();

        $healed = collect($after['battle']['log'])->firstWhere('kind', 'heal');

        $this->assertNotNull($healed, 'вода должна лечить отряд');
        $this->assertStringContainsString('Отряд восстанавливает', $healed['text']);
    }

    public function test_winning_a_room_grants_loot_and_moves_on(): void
    {
        $run = $this->startRun();
        $this->enter($run);

        // Добиваем врага: один ход при почти нулевом здоровье
        $this->patchState($run['id'], function (array $state) {
            $state['battle']['foe']['hp'] = 1;

            return $state;
        });

        $after = $this->actingAs($this->user)
            ->postJson("/api/dungeons/{$run['id']}/move", ['moves' => []])
            ->assertOk()
            ->json();

        $this->assertSame('idle', $after['phase']);
        $this->assertNull($after['battle']);
        $this->assertGreaterThan(0, $after['loot']['coins'], 'за зал начислены монеты');
        $this->assertArrayHasKey('items', $after['loot'], 'добыча приходит со списком предметов');
        $this->assertSame(2, $after['room'], 'следующий зал');
        $this->assertNotNull($after['pending']);
    }

    public function test_floor_ends_with_boss(): void
    {
        $run = $this->startRun();

        // Проходим первый зал
        $this->enter($run);
        $this->patchState($run['id'], fn (array $s) => tap($s, function (&$x) { $x['battle']['foe']['hp'] = 1; }));
        $this->actingAs($this->user)->postJson("/api/dungeons/{$run['id']}/move", ['moves' => []])->assertOk();

        // Проходим второй
        $this->enter($run);
        $this->patchState($run['id'], fn (array $s) => tap($s, function (&$x) { $x['battle']['foe']['hp'] = 1; }));
        $third = $this->actingAs($this->user)
            ->postJson("/api/dungeons/{$run['id']}/move", ['moves' => []])
            ->assertOk()
            ->json();

        $this->assertTrue($third['is_boss_next'], 'третий зал этажа — босс');
        $this->assertTrue($third['pending']['is_boss']);
        $this->assertStringStartsWith('boss-', $third['pending']['sprite']);
        $this->assertNotEmpty($third['pending']['element'], 'у босса есть своя стихия');
    }

    public function test_wipe_closes_run(): void
    {
        $run = $this->startRun();
        $this->enter($run);

        $this->patchState($run['id'], function (array $state) {
            foreach ($state['battle']['units'] as $i => $unit) {
                $state['battle']['units'][$i]['hp'] = 1;
            }

            $state['battle']['foe']['atk'] = 100000;
            $state['battle']['foe']['hp'] = 100000;

            return $state;
        });

        // Враг бьёт слабейшего, поэтому отряд вымирает не за один раунд
        $after = $run;

        for ($i = 0; $i < 6 && $after['status'] === 'active'; $i++) {
            $after = $this->actingAs($this->user)
                ->postJson("/api/dungeons/{$run['id']}/move", ['moves' => []])
                ->assertOk()
                ->json();
        }

        $this->assertSame('wiped', $after['status']);
    }

    /** Довести поход до поражения: враг бьёт на 100000. */
    private function wipeNow(array $run): array
    {
        $this->enter($run);

        $this->patchState($run['id'], function (array $state) {
            foreach ($state['battle']['units'] as $i => $unit) {
                $state['battle']['units'][$i]['hp'] = 1;
            }

            $state['battle']['foe']['atk'] = 100000;
            $state['battle']['foe']['hp'] = 100000;

            return $state;
        });

        $after = $run;

        for ($i = 0; $i < 6 && $after['status'] === 'active'; $i++) {
            $after = $this->actingAs($this->user)
                ->postJson("/api/dungeons/{$run['id']}/move", ['moves' => []])
                ->assertOk()
                ->json();
        }

        $this->assertSame('wiped', $after['status']);

        return $after;
    }

    public function test_wipe_takes_coins(): void
    {
        $this->user->forceFill(['apex_coins' => 5000])->save();

        $after = $this->wipeNow($this->startRun());

        $penalty = Dungeons::wipePenalty('catacombs');
        $this->assertGreaterThan(0, $penalty, 'в катакомбах штраф есть');

        $this->assertSame($penalty, (int) $after['loot']['penalty']);
        $this->assertSame($penalty, (int) $after['loot']['penalty_full']);

        // Баланс: было + собрано за поход − штраф
        $this->assertSame(
            5000 + (int) $after['loot']['coins'] - $penalty,
            (int) $this->user->fresh()->apex_coins,
        );

        // В ответе есть баланс — интерфейсу нужно показать, сколько осталось
        $this->assertArrayHasKey('balance', $after);
    }

    public function test_wipe_penalty_does_not_go_below_zero(): void
    {
        $this->user->forceFill(['apex_coins' => 30])->save();

        $after = $this->wipeNow($this->startRun());

        // Списать больше, чем есть, нельзя: платим сколько можем
        $this->assertSame(30, (int) $after['loot']['penalty']);
        $this->assertSame(Dungeons::wipePenalty('catacombs'), (int) $after['loot']['penalty_full']);
        $this->assertSame(0, (int) $this->user->fresh()->apex_coins);
    }

    public function test_retreat_does_not_take_coins(): void
    {
        $this->user->forceFill(['apex_coins' => 5000])->save();

        $run = $this->startRun();

        $closed = $this->actingAs($this->user)
            ->postJson("/api/dungeons/{$run['id']}/retreat")
            ->assertOk()
            ->json();

        $this->assertSame('retreat', $closed['status']);
        $this->assertSame(0, (int) ($closed['loot']['penalty'] ?? 0));
        $this->assertSame(5000, (int) $this->user->fresh()->apex_coins, 'отступление бесплатно');
    }

    public function test_retreat_keeps_loot(): void
    {
        $run = $this->startRun();

        $closed = $this->actingAs($this->user)
            ->postJson("/api/dungeons/{$run['id']}/retreat")
            ->assertOk()
            ->json();

        $this->assertSame('retreat', $closed['status']);
        $this->assertNull($closed['pending']);

        // По этому ответу интерфейс рисует окно итогов
        $this->assertGreaterThanOrEqual(1, $closed['room']);
        $this->assertGreaterThanOrEqual(1, $closed['floors']);
        $this->assertArrayHasKey('items', $closed['loot']);
        $this->assertArrayHasKey('keys', $closed['loot']);

        $this->actingAs($this->user)
            ->postJson("/api/dungeons/{$run['id']}/advance")
            ->assertStatus(422);
    }

    /** Заглушить бесплатные походы: столько записей за сегодня, сколько нужно. */
    private function burnRuns(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            DB::table('dungeon_runs')->insert([
                'user_id' => $this->user->id,
                'dungeon' => 'catacombs',
                'status' => 'cleared',
                'squad' => json_encode([]),
                'state' => json_encode(['floor' => 1, 'encounter' => 0, 'phase' => 'done', 'members' => [], 'pending' => null, 'battle' => null, 'log' => [], 'loot' => ['coins' => 0, 'gear' => [], 'keys' => []]]),
                'coins' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_extra_run_can_be_bought_for_coins(): void
    {
        $this->user->forceFill(['apex_coins' => 5000])->save();
        $this->burnRuns(Dungeons::DAILY_RUNS);

        $this->actingAs($this->user)
            ->getJson('/api/dungeons')
            ->assertOk()
            ->assertJsonPath('runs_left', 0)
            ->assertJsonPath('next_run_price', Dungeons::EXTRA_RUN_BASE);

        $bought = $this->actingAs($this->user)
            ->postJson('/api/dungeons/buy-run')
            ->assertOk()
            ->assertJsonPath('runs_left', 1)
            ->assertJsonPath('next_price', Dungeons::EXTRA_RUN_BASE + Dungeons::EXTRA_RUN_STEP)
            ->json();

        $this->assertSame(5000 - Dungeons::EXTRA_RUN_BASE, $bought['balance']);
        $this->assertDatabaseHas('dungeon_extra_runs', ['user_id' => $this->user->id, 'bought' => 1]);
    }

    public function test_bought_run_lets_start_a_new_run(): void
    {
        $this->user->forceFill(['apex_coins' => 5000])->save();
        $this->burnRuns(Dungeons::DAILY_RUNS);

        $a = $this->pet($this->user);
        $b = $this->pet($this->user, 'cat');

        // Без покупки четвёртый поход не начать
        $this->actingAs($this->user)
            ->postJson('/api/dungeons/start', ['dungeon' => 'catacombs', 'squad' => [$a->id, $b->id]])
            ->assertStatus(422);

        $this->actingAs($this->user)->postJson('/api/dungeons/buy-run')->assertOk();

        $this->actingAs($this->user)
            ->postJson('/api/dungeons/start', ['dungeon' => 'catacombs', 'squad' => [$a->id, $b->id]])
            ->assertCreated();
    }

    public function test_buy_run_fails_without_coins(): void
    {
        $this->user->forceFill(['apex_coins' => 100])->save();
        $this->burnRuns(Dungeons::DAILY_RUNS);

        $this->actingAs($this->user)
            ->postJson('/api/dungeons/buy-run')
            ->assertStatus(422)
            ->assertJsonPath('missing', Dungeons::EXTRA_RUN_BASE - 100);
    }

    /** Сила питомца из сводки — по ней подбирается отряд. */
    private function powerOf(int $petId): int
    {
        $pets = $this->actingAs($this->user)->getJson('/api/dungeons')->assertOk()->json('pets');

        foreach ($pets as $row) {
            if ((int) $row['inventory_id'] === $petId) {
                return (int) $row['power'];
            }
        }

        return 0;
    }

    public function test_pet_power_includes_equipped_gear(): void
    {
        $pet = $this->pet($this->user);

        $before = $this->powerOf($pet->id);

        $this->actingAs($this->user)->postJson("/api/dungeons/gear/{$pet->id}/equip", [
            'gear' => $this->gear($this->user, 'gear-hammer')->id,
        ])->assertOk();

        $after = $this->powerOf($pet->id);

        $this->assertGreaterThan($before, $after, 'кнопка «Подобрать отряд» должна видеть экипировку');
    }

    public function test_every_dungeon_has_twelve_mobs_with_real_sprites(): void
    {
        // Спрайты живут во фронтенде — сверяем, что ключи не выдуманы
        $sprites = (string) file_get_contents(base_path('../frontend/src/data/shop/pixelSprites.js'));

        $this->assertCount(5, Dungeons::DUNGEONS, 'пять подземелий');

        foreach (Dungeons::DUNGEONS as $key => $dungeon) {
            $this->assertCount(12, Dungeons::MOBS[$key], "у «{$dungeon['name']}» должно быть 12 мобов");
            $this->assertGreaterThanOrEqual((int) $dungeon['floors'], count(Dungeons::BOSSES[$key]), 'босс на каждый этаж');

            foreach (array_merge(Dungeons::MOBS[$key], Dungeons::BOSSES[$key]) as $mob) {
                $this->assertStringContainsString("'{$mob['sprite']}'", $sprites, "спрайт {$mob['sprite']} должен быть нарисован");
                $this->assertArrayHasKey($mob['trait'], Dungeons::TRAITS, 'особенность должна существовать');
                $this->assertArrayHasKey($mob['element'], DungeonBattle::ELEMENTS, 'стихия должна быть известной');
            }
        }
    }

    public function test_dungeon_requirements_fit_max_pet_level(): void
    {
        /*
         * Максимальный уровень питомца — PetService::MAX_LEVEL (30).
         * Требование выше него делает подземелье недостижимым.
         */
        foreach (Dungeons::DUNGEONS as $key => $dungeon) {
            $this->assertLessThanOrEqual(
                PetService::MAX_LEVEL,
                (int) $dungeon['min_level'],
                "в «{$dungeon['name']}» нельзя требовать уровень выше максимального",
            );
        }
    }

    public function test_every_boss_has_its_own_sprite(): void
    {
        $sprites = [];

        foreach (Dungeons::BOSSES as $bosses) {
            foreach ($bosses as $boss) {
                $sprites[] = $boss['sprite'];
            }
        }

        $this->assertSame(
            count($sprites),
            count(array_unique($sprites)),
            'у каждого босса должен быть свой спрайт, без повторов',
        );
    }

    public function test_new_dungeons_scale_rewards(): void
    {
        // Ступени 4 и 5 должны уметь считать монеты и редкость
        foreach ([4, 5] as $stage) {
            $this->assertArrayHasKey($stage, Dungeons::COINS_PER_MOB);
            $this->assertArrayHasKey($stage, Dungeons::COINS_PER_BOSS);
            $this->assertArrayHasKey($stage, Dungeons::DROPS);
            $this->assertArrayHasKey($stage, Dungeons::DROP_RARITY);
            $this->assertContains(Dungeons::rollRarity($stage), array_keys(Dungeons::RARITY_MULT));
        }

        $this->assertGreaterThan(Dungeons::COINS_PER_MOB[3], Dungeons::COINS_PER_MOB[4]);
        $this->assertGreaterThan(Dungeons::COINS_PER_MOB[4], Dungeons::COINS_PER_MOB[5]);
    }

    public function test_every_stage_offers_variants_in_every_slot(): void
    {
        $sprites = (string) file_get_contents(base_path('../frontend/src/data/shop/pixelSprites.js'));

        // 5 ступеней × 3 слота × 2 варианта
        $this->assertCount(30, Dungeons::GEAR);

        foreach ([1, 2, 3, 4, 5] as $stage) {
            $pool = Dungeons::gearForStage($stage);

            foreach (Dungeons::SLOTS as $slot) {
                $variants = array_filter($pool, fn (array $gear) => $gear['slot'] === $slot);

                $this->assertGreaterThanOrEqual(
                    2,
                    count($variants),
                    "на ступени {$stage} должно быть хотя бы два варианта слота «{$slot}»",
                );
            }

            // Дроп не должен тянуть мусор с нижних ступеней
            foreach ($pool as $gear) {
                $this->assertGreaterThanOrEqual($stage - 1, $gear['stage']);
                $this->assertLessThanOrEqual($stage, $gear['stage']);

                $this->assertStringContainsString("'{$gear['sprite']}'", $sprites, "спрайт {$gear['sprite']} должен быть нарисован");
            }
        }
    }

    public function test_rarity_scales_gear_stats(): void
    {
        $gear = Dungeons::GEAR['gear-hammer'];

        $common = Dungeons::scaledStats($gear, 'common');
        $rare = Dungeons::scaledStats($gear, 'rare');
        $epic = Dungeons::scaledStats($gear, 'epic');
        $legendary = Dungeons::scaledStats($gear, 'legendary');

        $this->assertSame((int) $gear['atk'], $common['atk'], 'обычный предмет = база');
        $this->assertGreaterThan($common['atk'], $rare['atk']);
        $this->assertGreaterThan($rare['atk'], $epic['atk']);
        $this->assertGreaterThan($epic['atk'], $legendary['atk']);
        $this->assertGreaterThan($common['def'], $legendary['def']);
    }

    public function test_rarity_roll_stays_within_stage_table(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->assertContains(Dungeons::rollRarity(1), ['common', 'rare', 'epic']);
            $this->assertContains(Dungeons::rollRarity(3), array_keys(Dungeons::RARITY_MULT));
            $this->assertContains(Dungeons::rollRarity(3, true), array_keys(Dungeons::RARITY_MULT));
        }
    }

    public function test_granted_gear_keeps_its_own_rarity_and_stats(): void
    {
        $id = app(PetGearService::class)->grant($this->user, 'gear-hammer', 'epic');
        $row = UserInventory::find($id);

        $expected = Dungeons::scaledStats(Dungeons::GEAR['gear-hammer'], 'epic');

        $this->assertSame('epic', $row->gear_stats['rarity']);
        $this->assertSame($expected['atk'], $row->gear_stats['atk']);

        // Надетое даёт бонус именно этого экземпляра
        $pet = $this->pet($this->user);

        $this->actingAs($this->user)
            ->postJson("/api/dungeons/gear/{$pet->id}/equip", ['gear' => $id])
            ->assertOk()
            ->assertJsonPath('bonus.atk', $expected['atk'])
            ->assertJsonPath('gear.weapon.rarity', 'epic')
            ->assertJsonPath('gear.weapon.rarity_name', 'Эпический');
    }

    public function test_dropped_gear_carries_rarity_and_stats(): void
    {
        $run = $this->startRun();
        $this->enter($run);

        // Добиваем врага и смотрим, что попало в окно итогов
        $this->patchState($run['id'], function (array $state) {
            $state['battle']['foe']['hp'] = 1;

            return $state;
        });

        $after = $this->actingAs($this->user)
            ->postJson("/api/dungeons/{$run['id']}/move", ['moves' => []])
            ->assertOk()
            ->json();

        foreach ($after['loot']['items'] as $item) {
            $this->assertArrayHasKey('rarity', $item);
            $this->assertArrayHasKey('rarity_name', $item);
            $this->assertArrayHasKey('atk', $item);
            $this->assertArrayHasKey('hp', $item);
            $this->assertContains($item['rarity'], array_keys(Dungeons::RARITY_MULT));
        }
    }

    public function test_overview_returns_last_finished_run(): void
    {
        $run = $this->startRun();

        $this->actingAs($this->user)
            ->postJson("/api/dungeons/{$run['id']}/retreat")
            ->assertOk();

        // Отдельный запрос сводки — как после перезагрузки страницы
        $this->actingAs($this->user)
            ->getJson('/api/dungeons')
            ->assertOk()
            ->assertJsonPath('run', null)
            ->assertJsonPath('last_result.id', $run['id'])
            ->assertJsonPath('last_result.status', 'retreat')
            ->assertJsonPath('last_result.room', 1);
    }
    public function test_gear_equips_to_pet_and_cannot_be_worn_twice(): void
    {
        $pet = $this->pet($this->user);
        $other = $this->pet($this->user, 'cat');
        $gear = $this->gear($this->user, 'gear-fang');

        $this->actingAs($this->user)
            ->postJson("/api/dungeons/gear/{$pet->id}/equip", ['gear' => $gear->id])
            ->assertOk()
            ->assertJsonPath('gear.weapon.name', 'Крысиный клык')
            ->assertJsonPath('bonus.atk', 4);

        $this->actingAs($this->user)
            ->postJson("/api/dungeons/gear/{$other->id}/equip", ['gear' => $gear->id])
            ->assertStatus(422);

        $this->actingAs($this->user)
            ->postJson("/api/dungeons/gear/{$pet->id}/unequip/weapon")
            ->assertOk()
            ->assertJsonPath('gear', []);
    }

    public function test_gear_bonuses_apply_on_arena(): void
    {
        $pet = $this->pet($this->user);
        $foe = $this->pet(User::factory()->create(), 'frog');

        $battle = $this->actingAs($this->user)
            ->postJson("/api/pets/{$pet->id}/battle", ['opponent' => $foe->id])
            ->assertCreated()
            ->json('id');

        $before = json_decode(DB::table('pet_battles')->where('id', $battle)->value('state'), true)['me'];
        $this->actingAs($this->user)->postJson("/api/pets/battles/{$battle}/forfeit")->assertOk();

        $gear = $this->gear($this->user, 'gear-hide');

        $this->actingAs($this->user)
            ->postJson("/api/dungeons/gear/{$pet->id}/equip", ['gear' => $gear->id])
            ->assertOk();

        $battle2 = $this->actingAs($this->user)
            ->postJson("/api/pets/{$pet->id}/battle", ['opponent' => $foe->id])
            ->assertCreated()
            ->json('id');

        $after = json_decode(DB::table('pet_battles')->where('id', $battle2)->value('state'), true)['me'];

        // На арене видно снаряжение обоих
        $this->actingAs($this->user)
            ->getJson("/api/pets/battles/{$battle2}")
            ->assertOk()
            ->assertJsonPath('gear.me.items.0.name', 'Крысиная шкура')
            ->assertJsonPath('gear.me.bonus.def', 3)
            ->assertJsonPath('gear.foe.items', []);

        $this->assertSame($before['def'] + 3, $after['def'], 'доспех прибавляет защиту');
        $this->assertGreaterThan($before['max'], $after['max'], 'доспех прибавляет здоровье');
    }

    public function test_gear_seeder_creates_every_item(): void
    {
        $this->seed(\Database\Seeders\DungeonGearSeeder::class);

        // Сидер обязан покрывать всю таблицу: иначе часть предметов не выпадет
        $this->assertSame(
            count(Dungeons::GEAR),
            ShopItem::where('type', ShopItem::TYPE_PET_GEAR)->count(),
        );
    }
    public function test_gear_items_are_hidden_from_shop(): void
    {
        $this->assertSame(
            0,
            ShopItem::where('type', ShopItem::TYPE_PET_GEAR)->where('is_active', true)->count(),
        );
    }
}
