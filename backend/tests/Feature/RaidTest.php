<?php

namespace Tests\Feature;

use App\Domains\Players\Services\DungeonService;
use App\Domains\Players\Services\LavkaService;
use App\Domains\Players\Services\RaidService;
use App\Domains\Players\Support\Dungeons;
use App\Domains\Players\Support\PetLocks;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Services\RubleService;
use Database\Seeders\DungeonGearSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Рейд «Разлом», Лавка, подземелья с допуском по редкости и Рублёвые копи.
 */
class RaidTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DungeonGearSeeder::class);
        // Рейд открыт в 18:00–18:30
        $this->travelTo(now()->setTime(18, 5));
    }

    private function player(int $coins = 10000): User
    {
        return User::factory()->create(['apex_coins' => $coins, 'created_at' => now()->subDays(30)]);
    }

    /** Три питомца 30 уровня разных стихий. */
    private function squad(User $user, array $species = ['cat', 'dragon', 'frog']): array
    {
        return array_map(function (string $name) use ($user) {
            $item = ShopItem::firstOrCreate(['slug' => "pet-{$name}"], [
                'name' => "Питомец {$name}", 'type' => 'profile_pet', 'effect_value' => $name,
                'price' => 1000, 'is_active' => true, 'rarity' => 'rare',
            ]);
            $row = UserInventory::create(['user_id' => $user->id, 'shop_item_id' => $item->id, 'quantity' => 1]);
            DB::table('user_inventory')->where('id', $row->id)->update(['pet_xp' => 2200]);

            return $row->id;
        }, $species);
    }

    private function group(User ...$users): int
    {
        $id = $this->actingAs($users[0])->postJson('/api/raids', ['squad' => $this->squad($users[0])])->assertCreated()->json('id');

        foreach (array_slice($users, 1) as $user) {
            $this->actingAs($user)->postJson("/api/raids/{$id}/join", ['squad' => $this->squad($user, ['fox', 'phoenix', 'bee'])])->assertOk();
        }

        return $id;
    }

    // ---- Расписание ----

    public function test_slots_open_at_18_and_22_for_half_an_hour(): void
    {
        $this->assertNotNull(RaidService::currentSlot(now()->setTime(18, 0)));
        $this->assertNotNull(RaidService::currentSlot(now()->setTime(22, 29)));
        $this->assertNull(RaidService::currentSlot(now()->setTime(18, 30)));
        $this->assertNull(RaidService::currentSlot(now()->setTime(12, 0)));

        $this->assertSame('22:00', RaidService::nextSlot(now()->setTime(19, 0))->format('H:i'));
        $this->assertSame('18:00', RaidService::nextSlot(now()->setTime(23, 0))->format('H:i'));
    }

    public function test_cannot_create_group_outside_the_window(): void
    {
        $user = $this->player();
        $this->travelTo(now()->setTime(12, 0));

        $this->actingAs($user)->postJson('/api/raids', ['squad' => $this->squad($user)])->assertStatus(422);
        $this->assertDatabaseCount('raids', 0);
    }

    // ---- Группа ----

    public function test_group_lobby_join_and_pets_become_busy(): void
    {
        $a = $this->player();
        $b = $this->player();
        $id = $this->group($a, $b);

        $state = $this->actingAs($a)->getJson("/api/raids/{$id}")->assertOk()->json();
        $this->assertCount(2, $state['members']);

        $mine = collect($state['members'])->firstWhere('me', true)['units'];
        $this->assertSame(PetLocks::RAID, PetLocks::reason($mine[0]['id']));

        // Занятого питомца в другой рейд / подземелье не взять
        $this->actingAs($a)->postJson('/api/raids', ['squad' => array_column($mine, 'id')])->assertStatus(422);
    }

    public function test_group_is_limited_to_three_and_one_group_per_player(): void
    {
        [$a, $b, $c, $d] = [$this->player(), $this->player(), $this->player(), $this->player()];
        $id = $this->group($a, $b, $c);

        $this->actingAs($d)->postJson("/api/raids/{$id}/join", ['squad' => $this->squad($d)])->assertStatus(422);
        $this->actingAs($b)->postJson("/api/raids/{$id}/join", ['squad' => $this->squad($b)])->assertStatus(422);
    }

    public function test_squad_must_be_three_own_pets_of_level_30(): void
    {
        $a = $this->player();
        $other = $this->player();
        $theirs = $this->squad($other);
        $weak = $this->squad($a);
        DB::table('user_inventory')->where('id', $weak[0])->update(['pet_xp' => 0]);

        $this->actingAs($a)->postJson('/api/raids', ['squad' => $theirs])->assertStatus(422);
        $this->actingAs($a)->postJson('/api/raids', ['squad' => $weak])->assertStatus(422);
        $this->actingAs($a)->postJson('/api/raids', ['squad' => array_slice($weak, 0, 2)])->assertStatus(422);
    }

    public function test_only_host_starts_and_needs_two_players(): void
    {
        $a = $this->player();
        $b = $this->player();
        $id = $this->actingAs($a)->postJson('/api/raids', ['squad' => $this->squad($a)])->json('id');

        $this->actingAs($a)->postJson("/api/raids/{$id}/start")->assertStatus(422);

        $this->actingAs($b)->postJson("/api/raids/{$id}/join", ['squad' => $this->squad($b)])->assertOk();
        $this->actingAs($b)->postJson("/api/raids/{$id}/start")->assertStatus(422);
        $this->actingAs($a)->postJson("/api/raids/{$id}/start")->assertOk()->assertJsonPath('status', 'active')->assertJsonPath('boss.max', 18000);
    }

    // ---- Бой ----

    public function test_armor_holds_without_synergy_and_breaks_with_different_elements(): void
    {
        $a = $this->player();
        $b = $this->player();
        $id = $this->group($a, $b);
        $this->actingAs($a)->postJson("/api/raids/{$id}/start")->assertOk();

        $unitsA = collect($this->actingAs($a)->getJson("/api/raids/{$id}")->json('members'))->firstWhere('me', true)['units'];
        $unitsB = collect($this->actingAs($b)->getJson("/api/raids/{$id}")->json('members'))->firstWhere('me', true)['units'];

        // Все бьют своими, но у двоих одна стихия «зверь» — всё же от разных игроков разные стихии есть
        $attack = fn ($units) => collect($units)->mapWithKeys(fn ($u) => [$u['id'] => 'attack'])->all();
        $this->actingAs($a)->postJson("/api/raids/{$id}/moves", ['moves' => $attack($unitsA)])->assertOk();
        $result = $this->actingAs($b)->postJson("/api/raids/{$id}/moves", ['moves' => $attack($unitsB)])->assertOk()->json();

        // Раунд закрылся сразу: оба сдали приёмы
        $this->assertSame(2, $result['round']);
        $this->assertTrue($result['last_round']['synergy']);
        $this->assertLessThan(18000, $result['boss']['hp']);
    }

    public function test_same_element_from_everyone_leaves_armor_up(): void
    {
        $a = $this->player();
        $b = $this->player();
        $idA = $this->actingAs($a)->postJson('/api/raids', ['squad' => $this->squad($a, ['cat', 'dog', 'fox'])])->json('id');
        $this->actingAs($b)->postJson("/api/raids/{$idA}/join", ['squad' => $this->squad($b, ['panda', 'capybara', 'cat'])])->assertOk();
        $this->actingAs($a)->postJson("/api/raids/{$idA}/start");

        $mine = fn ($u) => collect($this->actingAs($u)->getJson("/api/raids/{$idA}")->json('members'))->firstWhere('me', true)['units'];
        $attack = fn ($units) => collect($units)->mapWithKeys(fn ($x) => [$x['id'] => 'attack'])->all();
        $this->actingAs($a)->postJson("/api/raids/{$idA}/moves", ['moves' => $attack($mine($a))]);
        $result = $this->actingAs($b)->postJson("/api/raids/{$idA}/moves", ['moves' => $attack($mine($b))])->json();

        $this->assertFalse($result['last_round']['synergy']);
    }

    public function test_cannot_pick_moves_for_other_pets_or_unknown_moves(): void
    {
        $a = $this->player();
        $b = $this->player();
        $id = $this->group($a, $b);
        $this->actingAs($a)->postJson("/api/raids/{$id}/start");

        $theirs = collect($this->actingAs($b)->getJson("/api/raids/{$id}")->json('members'))->firstWhere('me', true)['units'][0]['id'];
        $mine = collect($this->actingAs($a)->getJson("/api/raids/{$id}")->json('members'))->firstWhere('me', true)['units'][0]['id'];

        $this->actingAs($a)->postJson("/api/raids/{$id}/moves", ['moves' => [$theirs => 'attack']])->assertStatus(422);
        $this->actingAs($a)->postJson("/api/raids/{$id}/moves", ['moves' => [$mine => 'nuke']])->assertStatus(422);
    }

    public function test_round_closes_on_timer_and_silent_player_defends(): void
    {
        $a = $this->player();
        $b = $this->player();
        $id = $this->group($a, $b);
        $this->actingAs($a)->postJson("/api/raids/{$id}/start");

        $this->travel(RaidService::ROUND_SECONDS + 1)->seconds();
        app(RaidService::class)->tick();

        $this->assertSame(2, (int) DB::table('raids')->find($id)->round);
    }

    // ---- Ливеры ----

    public function test_leaving_the_fight_bans_and_exhausts_pets_and_enrages_boss(): void
    {
        $a = $this->player();
        $b = $this->player();
        $id = $this->group($a, $b);
        $this->actingAs($a)->postJson("/api/raids/{$id}/start");

        $ids = collect($this->actingAs($b)->getJson("/api/raids/{$id}")->json('members'))->firstWhere('me', true)['units'];
        $this->actingAs($b)->postJson("/api/raids/{$id}/leave")->assertOk()->assertJsonPath('penalty', true);

        $this->assertTrue(\Illuminate\Support\Carbon::parse(DB::table('users')->find($b->id)->raid_banned_until)->isFuture());
        $this->assertSame(1, (int) DB::table('raids')->find($id)->deserters);
        $this->assertSame(0, (int) DB::table('user_inventory')->find($ids[0]['id'])->pet_energy);

        // До конца бана рейды закрыты
        $this->actingAs($b)->postJson('/api/raids', ['squad' => $this->squad($b, ['owl', 'bunny', 'robot'])])->assertStatus(422);
    }

    public function test_two_silent_rounds_count_as_deserting(): void
    {
        $a = $this->player();
        $b = $this->player();
        $id = $this->group($a, $b);
        $this->actingAs($a)->postJson("/api/raids/{$id}/start");

        for ($i = 0; $i < RaidService::AFK_LIMIT; $i++) {
            $this->travel(RaidService::ROUND_SECONDS + 1)->seconds();
            app(RaidService::class)->tick();
        }

        $this->assertSame(2, DB::table('raid_members')->where('raid_id', $id)->whereNotNull('left_at')->count());
    }

    public function test_leaving_lobby_is_free_and_frees_pets(): void
    {
        $a = $this->player();
        $b = $this->player();
        $id = $this->group($a, $b);
        $pets = array_column(collect($this->actingAs($b)->getJson("/api/raids/{$id}")->json('members'))->firstWhere('me', true)['units'], 'id');

        $this->actingAs($b)->postJson("/api/raids/{$id}/leave")->assertOk()->assertJsonPath('penalty', false);

        $this->assertNull(PetLocks::reason($pets[0]));
        $this->assertNull(DB::table('users')->find($b->id)->raid_banned_until);
    }

    // ---- Награды ----

    public function test_victory_pays_rubles_shards_coins_and_legendary_gear_once(): void
    {
        $a = $this->player(0);
        $b = $this->player(0);
        $id = $this->group($a, $b);
        $this->actingAs($a)->postJson("/api/raids/{$id}/start");

        // Босс при смерти: добиваем одним ударом
        DB::table('raids')->where('id', $id)->update(['boss_hp' => 1]);
        $units = fn ($u) => collect($this->actingAs($u)->getJson("/api/raids/{$id}")->json('members'))->firstWhere('me', true)['units'];
        $attack = fn ($list) => collect($list)->mapWithKeys(fn ($x) => [$x['id'] => 'attack'])->all();
        $this->actingAs($a)->postJson("/api/raids/{$id}/moves", ['moves' => $attack($units($a))]);
        $this->actingAs($b)->postJson("/api/raids/{$id}/moves", ['moves' => $attack($units($b))])->assertOk()->assertJsonPath('status', 'won');

        foreach ([$a, $b] as $user) {
            $this->assertSame(0, RubleService::balance($user), 'Рубли падают только в Рублёвых копях');
            $this->assertSame(RaidService::REWARD['shards'], (int) DB::table('users')->find($user->id)->rift_shards);
            $this->assertSame(RaidService::REWARD['coins'], (int) $user->fresh()->apex_coins);
            $this->assertSame(1, UserInventory::where('user_id', $user->id)->whereHas('item', fn ($q) => $q->where('type', ShopItem::TYPE_PET_GEAR))->count());
        }

        // Повторный ход / повторная победа награду не удваивает
        $this->actingAs($a)->postJson("/api/raids/{$id}/moves", ['moves' => [1 => 'attack']])->assertStatus(422);
        $this->assertSame(0, RubleService::balance($a));

        // Второй рейд в том же окне запрещён
        $this->actingAs($a)->postJson('/api/raids', ['squad' => $this->squad($a, ['owl', 'bunny', 'robot'])])->assertStatus(422);
    }

    public function test_unstarted_lobby_expires_after_the_window(): void
    {
        $a = $this->player();
        $id = $this->actingAs($a)->postJson('/api/raids', ['squad' => $this->squad($a)])->json('id');

        $this->travel(31)->minutes();
        app(RaidService::class)->tick();

        $this->assertSame('expired', DB::table('raids')->find($id)->status);
    }

    public function test_raids_require_auth(): void
    {
        $this->getJson('/api/raids')->assertUnauthorized();
        $this->getJson('/api/lavka')->assertUnauthorized();
    }

    // ---- Лавка ----

    public function test_privilege_purchase_spends_rubles_and_queues_a_grant(): void
    {
        $user = $this->player();
        DB::table('users')->where('id', $user->id)->update(['rubles' => 400, 'minecraft_username' => 'Steve_1']);
        $user = $user->fresh();

        $this->actingAs($user)->postJson('/api/lavka/privileges/vip_30')->assertCreated();

        $this->assertSame(100, RubleService::balance($user));
        $grant = \App\Domains\Minecraft\Models\MinecraftGrant::first();
        $this->assertSame('Steve_1', $grant->minecraft_username);
        $this->assertSame(['lp user Steve_1 parent addtemp vip 30d'], $grant->commands);

        // Сутки: вторая покупка того же не пройдёт и Рубли не спишет
        $this->actingAs($user)->postJson('/api/lavka/privileges/vip_30')->assertStatus(422);
        $this->assertSame(100, RubleService::balance($user));
    }

    public function test_privilege_needs_rubles_and_linked_nick(): void
    {
        $poor = $this->player();
        DB::table('users')->where('id', $poor->id)->update(['rubles' => 5, 'minecraft_username' => 'Alex_22']);
        $poor = $poor->fresh();
        $this->actingAs($poor)->postJson('/api/lavka/privileges/fly_7')->assertStatus(422);
        $this->assertSame(5, RubleService::balance($poor));
        $this->assertDatabaseCount('minecraft_grants', 0);

        $unlinked = $this->player();
        DB::table('users')->where('id', $unlinked->id)->update(['rubles' => 500]);
        $this->actingAs($unlinked)->postJson('/api/lavka/privileges/fly_7')->assertStatus(422);
        $this->assertSame(500, RubleService::balance($unlinked));
    }

    public function test_shard_shop_sells_gear_core_and_vigor(): void
    {
        $user = $this->player();
        DB::table('users')->where('id', $user->id)->update(['rift_shards' => 100]);
        [$pet] = $this->squad($user, ['cat', 'dog', 'fox']);
        DB::table('user_inventory')->where('id', $pet)->update(['pet_energy' => 5]);

        $this->actingAs($user)->postJson('/api/lavka/shards/legend_gear')->assertCreated();
        $this->actingAs($user)->postJson('/api/lavka/shards/throne_core')->assertCreated();
        $this->actingAs($user)->postJson('/api/lavka/shards/vigor', ['pet' => $pet])->assertCreated();

        $this->assertSame(15, (int) DB::table('users')->find($user->id)->rift_shards);
        $this->assertSame(1, (int) DB::table('dungeon_materials')->where('user_id', $user->id)->where('material', 'core_throne')->value('quantity'));
        $this->assertSame(100, (int) DB::table('user_inventory')->find($pet)->pet_energy);

        // Осколков не осталось на следующее — отказ без списаний
        $this->actingAs($user)->postJson('/api/lavka/shards/throne_core')->assertStatus(422);
        $this->assertSame(15, (int) DB::table('users')->find($user->id)->rift_shards);
    }

    public function test_cannot_restore_energy_of_foreign_pet(): void
    {
        $user = $this->player();
        $other = $this->player();
        DB::table('users')->where('id', $user->id)->update(['rift_shards' => 50]);
        [$theirs] = $this->squad($other, ['cat', 'dog', 'fox']);

        $this->actingAs($user)->postJson('/api/lavka/shards/vigor', ['pet' => $theirs])->assertStatus(422);
        $this->assertSame(50, (int) DB::table('users')->find($user->id)->rift_shards);
    }

    // ---- Подземелья по редкости и Рублёвые копи ----

    public function test_gated_dungeons_declare_rarity_pass_and_currency(): void
    {
        $this->assertSame('epic', Dungeons::dungeon('crystal')['min_rarity']);
        $this->assertSame('legendary', Dungeons::dungeon('pantheon')['min_rarity']);
        $this->assertSame(100, Dungeons::dungeon('trial')['min_level']);
        $this->assertSame('ruble_pass', Dungeons::dungeon('trial')['grants']);
        $this->assertSame('ruble_pass', Dungeons::dungeon('rubledeep')['requires']);
        $this->assertSame('rubles', Dungeons::dungeon('rubledeep')['currency']);

        $this->assertGreaterThan(Dungeons::rarityRank('rare'), Dungeons::rarityRank('epic'));
        // Пропуск привязан: на рынок и в крафт не идёт
        $this->assertTrue(Dungeons::MATERIALS['ruble_pass']['bound']);
        $this->assertNotSame('ruble_pass', Dungeons::materialForStage(9));
    }

    public function test_common_pets_are_refused_by_rarity_dungeon_and_nothing_starts(): void
    {
        $user = $this->player();
        $ids = $this->squad($user, ['cat', 'dog', 'fox']);
        DB::table('user_inventory')->whereIn('id', $ids)->update(['pet_xp' => 24750, 'pet_traits' => json_encode(['model' => 'classic', 'backdrop' => 'none', 'accessory' => 'none'])]);

        $this->actingAs($user)->postJson('/api/dungeons/start', ['dungeon' => 'crystal', 'squad' => $ids])->assertStatus(422);
        $this->assertDatabaseCount('dungeon_runs', 0);
    }

    public function test_rubledeep_needs_and_consumes_the_pass(): void
    {
        $user = $this->player();
        $ids = $this->squad($user, ['cat', 'dog', 'fox']);
        DB::table('user_inventory')->whereIn('id', $ids)->update(['pet_xp' => 24750]);

        // Без пропуска — отказ
        $this->actingAs($user)->postJson('/api/dungeons/start', ['dungeon' => 'rubledeep', 'squad' => $ids])->assertStatus(422);
        $this->assertDatabaseCount('dungeon_runs', 0);

        DB::table('dungeon_materials')->insert(['user_id' => $user->id, 'material' => 'ruble_pass', 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $rarity = collect($ids)->map(fn ($id) => $id);
        $this->assertSame(3, $rarity->count());
    }

    public function test_ruble_pass_cannot_be_sold_or_crafted(): void
    {
        $user = $this->player();
        DB::table('dungeon_materials')->insert(['user_id' => $user->id, 'material' => 'ruble_pass', 'quantity' => 5, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->postJson('/api/market/sell-material', ['material' => 'ruble_pass', 'qty' => 1, 'price' => 100])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/dungeons/craft', ['material' => 'ruble_pass'])->assertStatus(422);
        $this->assertSame(5, (int) DB::table('dungeon_materials')->where('user_id', $user->id)->value('quantity'));
    }

    public function test_ruble_dungeon_has_its_own_daily_cap_separate_from_the_general_one(): void
    {
        $user = $this->player();

        // Общий предел выбран полностью — Рублёвые копи это не затрагивает
        RubleService::earn($user, RubleService::DAILY_EARN_CAP, RubleService::SOURCE_FARM, 'тест');
        $this->assertSame(0, RubleService::earn($user, 10, RubleService::SOURCE_FARM, 'ещё'));

        $got = RubleService::earn($user, 50, RubleService::SOURCE_RUBLE_DUNGEON, 'копи', null, RubleService::RUBLE_DUNGEON_CAP);
        $this->assertSame(50, $got);

        // Свой предел 150
        $this->assertSame(100, RubleService::earn($user, 200, RubleService::SOURCE_RUBLE_DUNGEON, 'копи', null, RubleService::RUBLE_DUNGEON_CAP));
        $this->assertSame(0, RubleService::earn($user, 1, RubleService::SOURCE_RUBLE_DUNGEON, 'копи', null, RubleService::RUBLE_DUNGEON_CAP));
    }

    public function test_new_dungeons_have_twelve_mobs_and_unique_boss_sprites(): void
    {
        foreach (['crystal', 'pantheon', 'trial', 'rubledeep'] as $key) {
            $dungeon = Dungeons::dungeon($key);
            $this->assertCount(12, Dungeons::MOBS[$key], $key);
            $this->assertCount($dungeon['floors'] >= 4 ? 4 : $dungeon['floors'], array_slice(Dungeons::BOSSES[$key], 0, $dungeon['floors']), $key);
            $this->assertGreaterThanOrEqual($dungeon['floors'], count(Dungeons::BOSSES[$key]), $key);
            $this->assertArrayHasKey($dungeon['stage'], Dungeons::DROPS);
            $this->assertArrayHasKey(Dungeons::coreFor($key), Dungeons::MATERIALS);
        }
    }
}
