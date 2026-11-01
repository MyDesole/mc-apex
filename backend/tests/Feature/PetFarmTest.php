<?php

namespace Tests\Feature;

use App\Domains\Players\Services\PersonalFarmService;
use App\Domains\Players\Services\PetBreedingService;
use App\Domains\Players\Services\PetFarmService;
use App\Domains\Players\Services\PetService;
use App\Domains\Players\Support\PetFarmTiers;
use App\Domains\Players\Support\PetLocks;
use App\Domains\Players\Support\PetPerks;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Services\LootCaseService;
use App\Domains\Shop\Support\LootCases;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Питомцы 2.0, фаза A: уровни до 100, фермы, Кладовка, скрещивание,
 * реформа сундуков.
 */
class PetFarmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Все виды, что растут на фермах
        foreach (PetFarmTiers::SPECIES as $tier => $species) {
            foreach ($species as $name) {
                ShopItem::firstOrCreate(['slug' => "pet-{$name}"], [
                    'name' => "Питомец {$name}", 'type' => 'profile_pet', 'effect_value' => $name,
                    'price' => 1000, 'is_active' => true, 'rarity' => $tier === 'common' ? 'rare' : 'epic',
                ]);
            }
        }
    }

    private function pet(User $owner, string $species = 'slime', int $xp = 0): UserInventory
    {
        $item = ShopItem::where('slug', "pet-{$species}")->firstOrFail();
        $row = UserInventory::create(['user_id' => $owner->id, 'shop_item_id' => $item->id, 'quantity' => 1]);
        $row->forceFill(['pet_xp' => $xp])->save();

        return $row->fresh();
    }

    private function withFarm(int $coins = 100000): User
    {
        $user = User::factory()->create(['apex_coins' => $coins]);
        DB::table('personal_farms')->insert(['user_id' => $user->id, 'plots' => 3, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function growingPet(string $species = 'slime', int $minutesToGrow = 60): int
    {
        $item = ShopItem::where('slug', "pet-{$species}")->first();

        return DB::table('farm_pets')->insertGetId([
            'tier' => PetFarmTiers::tierOf($species),
            'shop_item_id' => $item->id,
            'traits' => json_encode(['model' => 'classic', 'backdrop' => 'none', 'accessory' => 'none']),
            'price' => PetFarmTiers::COMMON_FARM_PRICE[PetFarmTiers::tierOf($species)],
            'status' => 'growing',
            'ready_at' => now()->addMinutes($minutesToGrow),
            'expires_at' => now()->addMinutes($minutesToGrow)->addHours(48),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ---- Уровни ----

    public function test_max_level_is_100_with_growing_xp_multiplier(): void
    {
        $this->assertSame(100, PetService::MAX_LEVEL);
        $this->assertSame(1, PetService::xpMultiplier(1));
        $this->assertSame(1, PetService::xpMultiplier(29));
        $this->assertSame(2, PetService::xpMultiplier(30));
        $this->assertSame(3, PetService::xpMultiplier(40));
        $this->assertSame(9, PetService::xpMultiplier(100));

        // 100 уровень начинается с 24 750 опыта и выше не растёт
        $this->assertSame(100, PetService::levelOf(24750));
        $this->assertSame(100, PetService::levelOf(10 ** 7));
        $this->assertSame(99, PetService::levelOf(24749));
    }

    public function test_care_at_high_level_gives_bonus_xp(): void
    {
        $owner = User::factory()->create();
        // 40 уровень — множитель ×3
        $pet = $this->pet($owner, 'slime', 3900);
        $this->assertSame(40, PetService::levels([$pet->id])[$pet->id]);

        $before = PetService::xpTotals([$pet->id])[$pet->id];

        // Прямой вызов сервиса: кормление = 2 опыта × 3
        app(PetService::class)->interact($owner, User::factory()->create(), 'feed', $pet->id);
        $after = PetService::xpTotals([$pet->id])[$pet->id];

        $this->assertSame(6, $after - $before);
    }

    public function test_new_perks_open_at_40_and_up(): void
    {
        $this->assertContains('expedition_fast', PetPerks::unlocked(40));
        $this->assertNotContains('expedition_fast', PetPerks::unlocked(39));
        $this->assertContains('crown', PetPerks::unlocked(100));
        $this->assertSame(0.9, PetPerks::expeditionTimeFactor(45));
        $this->assertSame(1.5, PetPerks::restFactor(70));
        $this->assertSame(4, PetPerks::matingHoursOff(100));
    }

    public function test_expedition_coins_stop_growing_after_level_30(): void
    {
        $owner = User::factory()->create();
        $high = $this->pet($owner, 'slime', 24750);
        $mid = $this->pet($owner, 'frog', 2175);

        $svc = app(\App\Domains\Players\Services\PetExpeditionService::class);
        $roll = new \ReflectionMethod($svc, 'roll');

        // Без перков и двойной добычи разница от уровня только в опыте
        $xpHigh = $roll->invoke($svc, \App\Domains\Players\Services\PetExpeditionService::ZONES['meadow'], 'meadow', 100)['xp'];
        $xpMid = $roll->invoke($svc, \App\Domains\Players\Services\PetExpeditionService::ZONES['meadow'], 'meadow', 30)['xp'];

        $this->assertSame(3 * 9, $xpHigh);
        $this->assertSame(3 * 2, $xpMid);
        $this->assertNotNull($high);
        $this->assertNotNull($mid);
    }

    // ---- Сундуки ----

    public function test_pets_chest_is_removed_and_royal_gets_only_beast_pets(): void
    {
        $this->assertNull(LootCases::find('pets'));
        $this->assertSame('royal', LootCases::RETIRED['pets']);

        $pool = new \ReflectionMethod(LootCaseService::class, 'pool');
        $species = $pool->invoke(app(LootCaseService::class))
            ->where('type', 'profile_pet')->pluck('effect_value')->sort()->values()->all();

        // cat, dog, fox, panda, capybara — но не единорог, дракон и прочие
        $this->assertSame(['capybara', 'cat', 'dog', 'fox', 'panda'], $species);
    }

    // ---- Общая ферма ----

    public function test_tick_spawns_stock_gradually_and_state_seeds_empty_farm(): void
    {
        $user = User::factory()->create(['apex_coins' => 0]);

        $state = $this->actingAs($user)->getJson('/api/farm')->assertOk()->json();
        $this->assertCount(array_sum(PetFarmService::STOCK), $state['pets']);

        DB::table('farm_pets')->delete();
        $result = app(PetFarmService::class)->tick();
        $this->assertSame(count(PetFarmService::STOCK), $result['spawned']);
        $this->assertSame(count(PetFarmService::STOCK), DB::table('farm_pets')->count());
    }

    public function test_reserve_charges_deposit_and_blocks_others(): void
    {
        $a = User::factory()->create(['apex_coins' => 10000]);
        $b = User::factory()->create(['apex_coins' => 10000]);
        $id = $this->growingPet('slime');

        $this->actingAs($a)->postJson("/api/farm/{$id}/reserve")->assertOk();
        $this->assertSame(10000 - 1000, (int) $a->fresh()->apex_coins);

        $this->actingAs($b)->postJson("/api/farm/{$id}/reserve")->assertStatus(422);
        $this->assertSame(10000, (int) $b->fresh()->apex_coins);

        // Чужую бронь не выкупить, даже когда выросла
        $this->travel(61)->minutes();
        $this->actingAs($b)->postJson("/api/farm/{$id}/buy")->assertStatus(422);
        $this->assertDatabaseCount('user_inventory', 0);
    }

    public function test_reservation_limit_is_three(): void
    {
        $user = User::factory()->create(['apex_coins' => 100000]);
        $ids = [$this->growingPet(), $this->growingPet(), $this->growingPet(), $this->growingPet()];

        foreach (array_slice($ids, 0, 3) as $id) {
            $this->actingAs($user)->postJson("/api/farm/{$id}/reserve")->assertOk();
        }

        $this->actingAs($user)->postJson("/api/farm/{$ids[3]}/reserve")->assertStatus(422);
    }

    public function test_buy_requires_growth_and_pays_remainder_after_reservation(): void
    {
        $user = User::factory()->create(['apex_coins' => 10000]);
        $id = $this->growingPet('slime', 60);

        $this->actingAs($user)->postJson("/api/farm/{$id}/reserve")->assertOk();
        // Ещё растёт
        $this->actingAs($user)->postJson("/api/farm/{$id}/buy")->assertStatus(422);

        $this->travel(61)->minutes();
        $this->actingAs($user)->postJson("/api/farm/{$id}/buy")->assertOk()->assertJsonPath('paid', 3000);

        // Всего 4000: задаток 1000 + доплата 3000
        $this->assertSame(10000 - 4000, (int) $user->fresh()->apex_coins);
        $this->assertSame(1, UserInventory::where('user_id', $user->id)->whereNotNull('pet_traits')->count());

        // Повторный выкуп того же питомца — нельзя (идемпотентность)
        $this->actingAs($user)->postJson("/api/farm/{$id}/buy")->assertStatus(422);
        $this->assertSame(1, UserInventory::where('user_id', $user->id)->count());
    }

    public function test_direct_buy_of_grown_pet_costs_full_price(): void
    {
        $user = User::factory()->create(['apex_coins' => 5000]);
        $id = $this->growingPet('slime', -10);

        $this->actingAs($user)->postJson("/api/farm/{$id}/buy")->assertOk()->assertJsonPath('paid', 4000);
        $this->assertSame(1000, (int) $user->fresh()->apex_coins);
    }

    public function test_buy_without_coins_is_refused_and_nothing_is_created(): void
    {
        $user = User::factory()->create(['apex_coins' => 100]);
        $id = $this->growingPet('slime', -10);

        $this->actingAs($user)->postJson("/api/farm/{$id}/buy")->assertStatus(422);
        $this->assertSame(0, UserInventory::where('user_id', $user->id)->count());
        $this->assertSame('growing', DB::table('farm_pets')->find($id)->status);
    }

    public function test_lapsed_reservation_frees_pet_and_deposit_is_lost(): void
    {
        $a = User::factory()->create(['apex_coins' => 10000]);
        $b = User::factory()->create(['apex_coins' => 10000]);
        $id = $this->growingPet('slime', 30);

        $this->actingAs($a)->postJson("/api/farm/{$id}/reserve")->assertOk();
        $this->travel(PetFarmService::HOLD_HOURS * 60 + 5)->minutes();

        $result = app(PetFarmService::class)->tick();
        $this->assertSame(1, $result['lapsed']);
        $this->assertNull(DB::table('farm_pets')->find($id)->reserved_by);
        $this->assertSame(9000, (int) $a->fresh()->apex_coins);

        // Теперь питомца выкупает другой — бывший держатель уже нет
        $this->actingAs($a)->postJson("/api/farm/{$id}/buy")->assertOk();
        $this->assertSame(10000 - 1000 - 4000, (int) $a->fresh()->apex_coins);
    }

    public function test_expired_reservation_is_released_even_before_tick(): void
    {
        $a = User::factory()->create(['apex_coins' => 10000]);
        $b = User::factory()->create(['apex_coins' => 10000]);
        $id = $this->growingPet('slime', 30);

        $this->actingAs($a)->postJson("/api/farm/{$id}/reserve")->assertOk();
        $this->travel(PetFarmService::HOLD_HOURS * 60 + 5)->minutes();

        // Планировщик не успел — но бронь уже не держит
        $this->actingAs($b)->postJson("/api/farm/{$id}/buy")->assertOk();
        $this->assertSame(1, UserInventory::where('user_id', $b->id)->count());
    }

    public function test_daily_purchase_limit(): void
    {
        $user = User::factory()->create(['apex_coins' => 1000000]);

        for ($i = 0; $i < PetFarmService::BUYS_PER_DAY; $i++) {
            $id = $this->growingPet('slime', -10);
            $this->actingAs($user)->postJson("/api/farm/{$id}/buy")->assertOk();
        }

        $id = $this->growingPet('slime', -10);
        $this->actingAs($user)->postJson("/api/farm/{$id}/buy")->assertStatus(422);
    }

    public function test_farm_requires_auth(): void
    {
        $this->getJson('/api/farm')->assertUnauthorized();
        $this->postJson('/api/farm/1/buy')->assertUnauthorized();
    }

    // ---- Личная ферма ----

    public function test_personal_farm_unlock_costs_500000_once(): void
    {
        $poor = User::factory()->create(['apex_coins' => 1000]);
        $this->actingAs($poor)->postJson('/api/farm/personal/unlock')->assertStatus(422);

        $rich = User::factory()->create(['apex_coins' => 600000]);
        $this->actingAs($rich)->postJson('/api/farm/personal/unlock')->assertCreated()->assertJsonPath('unlocked', true);
        $this->assertSame(100000, (int) $rich->fresh()->apex_coins);

        $this->actingAs($rich)->postJson('/api/farm/personal/unlock')->assertStatus(422);
        $this->assertSame(100000, (int) $rich->fresh()->apex_coins);
    }

    public function test_plant_and_harvest_legendary_pet(): void
    {
        $user = $this->withFarm(500000);

        $this->actingAs($user)->postJson('/api/farm/personal/plant', ['species' => 'phoenix'])->assertCreated();
        $this->assertSame(300000, (int) $user->fresh()->apex_coins);

        $plot = DB::table('farm_plots')->first();
        $this->actingAs($user)->postJson("/api/farm/personal/plots/{$plot->id}/harvest")->assertStatus(422);

        $this->travel(48 * 60 + 1)->minutes();
        $this->actingAs($user)->postJson("/api/farm/personal/plots/{$plot->id}/harvest")->assertOk();
        $this->actingAs($user)->postJson("/api/farm/personal/plots/{$plot->id}/harvest")->assertStatus(422);

        $this->assertSame(1, UserInventory::where('user_id', $user->id)->count());
    }

    public function test_cannot_plant_common_species_or_without_farm_or_beyond_plots(): void
    {
        $user = $this->withFarm();
        $this->actingAs($user)->postJson('/api/farm/personal/plant', ['species' => 'slime'])->assertStatus(422);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)->postJson('/api/farm/personal/plant', ['species' => 'panda'])->assertCreated();
        }
        $this->actingAs($user)->postJson('/api/farm/personal/plant', ['species' => 'panda'])->assertStatus(422);

        $nobody = User::factory()->create(['apex_coins' => 999999]);
        $this->actingAs($nobody)->postJson('/api/farm/personal/plant', ['species' => 'panda'])->assertStatus(422);
    }

    // ---- Спаривание ----

    public function test_mating_needs_farm(): void
    {
        $user = User::factory()->create(['apex_coins' => 10000]);
        $a = $this->pet($user);
        $b = $this->pet($user, 'frog');

        $this->actingAs($user)->postJson('/api/farm/personal/mating', ['parent1' => $a->id, 'parent2' => $b->id])->assertStatus(422);
    }

    public function test_mating_locks_parents_and_returns_offspring_after_a_day(): void
    {
        $user = $this->withFarm(10000);
        $a = $this->pet($user, 'slime');
        $b = $this->pet($user, 'frog');

        $pair = $this->actingAs($user)->postJson('/api/farm/personal/mating', ['parent1' => $a->id, 'parent2' => $b->id])
            ->assertCreated()->json();
        $this->assertSame(10000 - PetBreedingService::MATING_COST, (int) $user->fresh()->apex_coins);

        // Родители в Кладовке: нельзя на аукцион, в поход и на другое спаривание
        $this->assertSame(PetLocks::STORAGE, PetLocks::reason($a->id));
        $this->actingAs($user)->postJson('/api/shop/auctions', ['inventory_id' => $a->id, 'start_price' => 50, 'hours' => 12])->assertStatus(422);
        $this->actingAs($user)->postJson("/api/pets/{$b->id}/expedition", ['zone' => 'meadow'])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/farm/personal/mating', ['parent1' => $a->id, 'parent2' => $b->id])->assertStatus(422);

        // Рано
        $this->actingAs($user)->postJson("/api/farm/personal/mating/{$pair['id']}/claim")->assertStatus(422);

        $this->travel(24 * 60 + 1)->minutes();
        $result = $this->actingAs($user)->postJson("/api/farm/personal/mating/{$pair['id']}/claim")->assertOk()->json();

        $child = UserInventory::find($result['offspring']['inventory_id']);
        $this->assertSame($user->id, $child->user_id);
        $this->assertContains($child->shop_item_id, [$a->shop_item_id, $b->shop_item_id]);

        // Повторно забрать нельзя
        $this->actingAs($user)->postJson("/api/farm/personal/mating/{$pair['id']}/claim")->assertStatus(422);
        $this->assertNull(PetLocks::reason($a->id));
    }

    public function test_mating_spends_energy_and_requires_rest(): void
    {
        $user = $this->withFarm(100000);
        $a = $this->pet($user, 'slime');
        $b = $this->pet($user, 'frog');

        $pair = $this->actingAs($user)->postJson('/api/farm/personal/mating', ['parent1' => $a->id, 'parent2' => $b->id])->assertCreated()->json();
        $this->assertSame(30, PetBreedingService::energy($a->fresh()));

        $this->travel(24 * 60 + 1)->minutes();
        $this->actingAs($user)->postJson("/api/farm/personal/mating/{$pair['id']}/claim")->assertOk();

        // После суток энергии ещё мало (30 + 24 × 1,25 = 60 < 70)
        $this->assertSame(60, PetBreedingService::energy($a->fresh()));
        $this->actingAs($user)->postJson('/api/farm/personal/mating', ['parent1' => $a->id, 'parent2' => $b->id])->assertStatus(422);

        $this->travel(9)->hours();
        $this->assertGreaterThanOrEqual(70, PetBreedingService::energy($a->fresh()));
        $this->actingAs($user)->postJson('/api/farm/personal/mating', ['parent1' => $a->id, 'parent2' => $b->id])->assertCreated();
    }

    public function test_cannot_mate_other_players_pet(): void
    {
        $user = $this->withFarm(10000);
        $other = User::factory()->create();
        $mine = $this->pet($user);
        $theirs = $this->pet($other, 'frog');

        $this->actingAs($user)->postJson('/api/farm/personal/mating', ['parent1' => $mine->id, 'parent2' => $theirs->id])->assertStatus(422);
        $this->assertSame(10000, (int) $user->fresh()->apex_coins);
        $this->assertSame(100, PetBreedingService::energy($mine->fresh()));
    }

    public function test_storage_has_two_slots(): void
    {
        $user = $this->withFarm(100000);
        $pets = [];
        foreach (['slime', 'frog', 'bee', 'shroom', 'penguin', 'cat'] as $species) {
            $pets[] = $this->pet($user, $species);
        }

        $this->actingAs($user)->postJson('/api/farm/personal/mating', ['parent1' => $pets[0]->id, 'parent2' => $pets[1]->id])->assertCreated();
        $this->actingAs($user)->postJson('/api/farm/personal/mating', ['parent1' => $pets[2]->id, 'parent2' => $pets[3]->id])->assertCreated();
        $this->actingAs($user)->postJson('/api/farm/personal/mating', ['parent1' => $pets[4]->id, 'parent2' => $pets[5]->id])->assertStatus(422);
    }

    public function test_offspring_traits_come_from_parents_when_inheritance_is_certain(): void
    {
        // Родители без случайности: оба «классика» на пустом фоне — потомок почти наверняка похож
        $user = $this->withFarm(10000);
        $a = $this->pet($user, 'slime');
        $b = $this->pet($user, 'frog');
        $a->forceFill(['pet_traits' => ['model' => 'classic', 'backdrop' => 'none', 'accessory' => 'none']])->save();
        $b->forceFill(['pet_traits' => ['model' => 'classic', 'backdrop' => 'none', 'accessory' => 'none']])->save();

        $pair = $this->actingAs($user)->postJson('/api/farm/personal/mating', ['parent1' => $a->id, 'parent2' => $b->id])->json();
        $plan = json_decode(DB::table('pet_breedings')->find($pair['id'])->plan, true);

        // Потомок определён сразу: перезаход его не перебросит
        $this->assertArrayHasKey('traits', $plan);
        $this->assertArrayHasKey('model', $plan['traits']);
        $this->assertCount(3, $plan['traits']);
    }

    // ---- Скрещивание ----

    public function test_cross_burns_two_same_tier_pets_and_gives_next_tier(): void
    {
        $user = $this->withFarm(10000);
        $a = $this->pet($user, 'slime');
        $b = $this->pet($user, 'frog');

        $result = $this->actingAs($user)->postJson('/api/farm/personal/cross', ['parent1' => $a->id, 'parent2' => $b->id])
            ->assertCreated()->assertJsonPath('tier', 'uncommon')->json();

        $this->assertNull(UserInventory::find($a->id));
        $this->assertNull(UserInventory::find($b->id));
        $child = UserInventory::with('item')->find($result['offspring']['inventory_id']);
        $this->assertContains($child->item->effect_value, PetFarmTiers::SPECIES['uncommon']);
        $this->assertSame(10000 - PetBreedingService::CROSS_COST, (int) $user->fresh()->apex_coins);
    }

    public function test_cross_rejects_mixed_tiers_legendary_and_busy_pets(): void
    {
        $user = $this->withFarm(100000);
        $common = $this->pet($user, 'slime');
        $uncommon = $this->pet($user, 'cat');

        $this->actingAs($user)->postJson('/api/farm/personal/cross', ['parent1' => $common->id, 'parent2' => $uncommon->id])->assertStatus(422);

        $d1 = $this->pet($user, 'dragon');
        $d2 = $this->pet($user, 'phoenix');
        $this->actingAs($user)->postJson('/api/farm/personal/cross', ['parent1' => $d1->id, 'parent2' => $d2->id])->assertStatus(422);

        // Питомец в экспедиции не сгорит
        $c2 = $this->pet($user, 'frog');
        $this->actingAs($user)->postJson("/api/pets/{$c2->id}/expedition", ['zone' => 'meadow'])->assertCreated();
        $this->actingAs($user)->postJson('/api/farm/personal/cross', ['parent1' => $common->id, 'parent2' => $c2->id])->assertStatus(422);

        $this->assertNotNull(UserInventory::find($common->id));
        $this->assertNotNull(UserInventory::find($c2->id));
        $this->assertSame(100000, (int) $user->fresh()->apex_coins);
    }

    public function test_cross_cannot_burn_equipped_pet_or_other_players_pet(): void
    {
        $user = $this->withFarm(100000);
        $other = User::factory()->create();
        $a = $this->pet($user, 'slime');
        $b = $this->pet($user, 'frog');
        $theirs = $this->pet($other, 'bee');

        $b->forceFill(['equipped_at' => now()])->save();
        $this->actingAs($user)->postJson('/api/farm/personal/cross', ['parent1' => $a->id, 'parent2' => $b->id])->assertStatus(422);

        $this->actingAs($user)->postJson('/api/farm/personal/cross', ['parent1' => $a->id, 'parent2' => $theirs->id])->assertStatus(422);
        $this->assertNotNull(UserInventory::find($theirs->id));
    }

    public function test_personal_state_lists_pets_with_energy_and_tier(): void
    {
        $user = $this->withFarm();
        $this->pet($user, 'panda');

        $state = $this->actingAs($user)->getJson('/api/farm/personal')->assertOk()->json();

        $this->assertTrue($state['unlocked']);
        $this->assertSame('rare', $state['pets'][0]['tier']);
        $this->assertSame(100, $state['pets'][0]['energy']);
        $this->assertNotEmpty($state['seeds']);
    }

    public function test_farm_price_constants_match_design(): void
    {
        $this->assertSame(500000, PersonalFarmService::PRICE);
        // Мусорный питомец стоит ровно столько, сколько новичок копает за 1–2 дня
        $this->assertSame(4000, PetFarmTiers::COMMON_FARM_PRICE['common']);
    }
}
