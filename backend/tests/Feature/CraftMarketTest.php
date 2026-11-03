<?php

namespace Tests\Feature;

use App\Domains\Players\Services\MarketService;
use App\Domains\Players\Services\PetExpeditionService;
use App\Domains\Players\Services\PetGearService;
use App\Domains\Players\Support\Dungeons;
use App\Domains\Players\Support\PetTraits;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use Database\Seeders\DungeonGearSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Кузница (четыре рецепта), ядра боссов, рынок игроков, новые зоны.
 */
class CraftMarketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DungeonGearSeeder::class);
    }

    private function player(int $coins = 100000): User
    {
        return User::factory()->create(['apex_coins' => $coins, 'created_at' => now()->subDays(30)]);
    }

    private function mats(User $user, array $mats): void
    {
        foreach ($mats as $material => $quantity) {
            DB::table('dungeon_materials')->updateOrInsert(
                ['user_id' => $user->id, 'material' => $material],
                ['quantity' => $quantity, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    private function qty(User $user, string $material): int
    {
        return (int) DB::table('dungeon_materials')->where('user_id', $user->id)->where('material', $material)->value('quantity');
    }

    private function gear(User $user, string $slug, string $rarity = 'common'): int
    {
        return app(PetGearService::class)->grant($user, $slug, $rarity);
    }

    // ---- Редкость и зоны ----

    public function test_pet_traits_expose_one_in_n(): void
    {
        $traits = PetTraits::present(PetTraits::roll(1), 1);

        $this->assertIsInt($traits['one_in']);
        $this->assertGreaterThanOrEqual(1, $traits['one_in']);
    }

    public function test_high_level_zones_exist_and_scale(): void
    {
        $zones = PetExpeditionService::ZONES;

        foreach (['ruins' => 30, 'depths' => 45, 'dragons' => 60, 'edge' => 80] as $key => $level) {
            $this->assertSame($level, $zones[$key]['min_level'], $key);
            $this->assertArrayHasKey($key, (new \ReflectionClass(PetExpeditionService::class))->getReflectionConstant('STORIES')->getValue());
        }

        $this->assertGreaterThan($zones['nether']['coins'][1], $zones['edge']['coins'][1]);
    }

    // ---- Ядра боссов ----

    public function test_every_dungeon_has_its_own_core(): void
    {
        foreach (array_keys(Dungeons::DUNGEONS) as $key) {
            $core = Dungeons::coreFor($key);
            $this->assertArrayHasKey($core, Dungeons::MATERIALS, $key);
            $this->assertTrue(Dungeons::MATERIALS[$core]['core']);
        }

        // Обычный материал ступени — не ядро
        $this->assertSame('claw', Dungeons::materialForStage(1));
    }

    // ---- Ковка на ядре ----

    public function test_forge_on_core_gives_chosen_slot_with_epic_or_better(): void
    {
        $user = $this->player();
        $this->mats($user, ['core_forge' => 1, 'magma' => 3]);

        $result = $this->actingAs($user)->postJson('/api/dungeons/craft/forge', ['core' => 'core_forge', 'slot' => 'armor'])
            ->assertCreated()->json();

        $this->assertSame('armor', $result['crafted']['slot']);
        $this->assertContains($result['crafted']['rarity'], ['epic', 'legendary']);
        $this->assertSame(3, Dungeons::GEAR[$result['crafted']['slug']]['stage']);
        $this->assertSame(0, $this->qty($user, 'core_forge'));
        $this->assertSame(0, $this->qty($user, 'magma'));
    }

    public function test_forge_without_enough_materials_changes_nothing(): void
    {
        $user = $this->player();
        $this->mats($user, ['core_forge' => 1, 'magma' => 2]);

        $this->actingAs($user)->postJson('/api/dungeons/craft/forge', ['core' => 'core_forge', 'slot' => 'weapon'])->assertStatus(422);

        $this->assertSame(1, $this->qty($user, 'core_forge'));
        $this->assertSame(2, $this->qty($user, 'magma'));
    }

    public function test_basic_craft_rejects_cores_and_forge_rejects_plain_material(): void
    {
        $user = $this->player();
        $this->mats($user, ['core_forge' => 5, 'claw' => 5]);

        $this->actingAs($user)->postJson('/api/dungeons/craft', ['material' => 'core_forge'])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/dungeons/craft/forge', ['core' => 'claw', 'slot' => 'weapon'])->assertStatus(422);
    }

    // ---- Усиление ----

    public function test_upgrade_burns_two_items_and_raises_rarity(): void
    {
        $user = $this->player();
        $a = $this->gear($user, 'gear-fang');
        $b = $this->gear($user, 'gear-boneblade');

        $result = $this->actingAs($user)->postJson('/api/dungeons/craft/upgrade', ['first' => $a, 'second' => $b])
            ->assertCreated()->json();

        $this->assertSame('rare', $result['crafted']['rarity']);
        $this->assertSame('weapon', $result['crafted']['slot']);
        $this->assertNull(UserInventory::find($a));
        $this->assertNull(UserInventory::find($b));
        // Ступень 1 — 1500 монет
        $this->assertSame(100000 - 1500, (int) $user->fresh()->apex_coins);
    }

    public function test_upgrade_needs_same_slot_and_rarity_and_free_items(): void
    {
        $user = $this->player();
        $weapon = $this->gear($user, 'gear-fang');
        $armor = $this->gear($user, 'gear-rags');
        $rare = $this->gear($user, 'gear-boneblade', 'rare');

        $this->actingAs($user)->postJson('/api/dungeons/craft/upgrade', ['first' => $weapon, 'second' => $armor])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/dungeons/craft/upgrade', ['first' => $weapon, 'second' => $rare])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/dungeons/craft/upgrade', ['first' => $weapon, 'second' => $weapon])->assertStatus(422);
        $this->assertSame(100000, (int) $user->fresh()->apex_coins);
        $this->assertNotNull(UserInventory::find($weapon));
    }

    public function test_cannot_upgrade_or_dismantle_other_players_gear(): void
    {
        $user = $this->player();
        $other = $this->player();
        $mine = $this->gear($user, 'gear-fang');
        $theirs = $this->gear($other, 'gear-boneblade');

        $this->actingAs($user)->postJson('/api/dungeons/craft/upgrade', ['first' => $mine, 'second' => $theirs])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/dungeons/craft/dismantle', ['item' => $theirs])->assertStatus(422);
        $this->assertNotNull(UserInventory::find($theirs));
    }

    // ---- Переплавка ----

    public function test_dismantle_returns_stage_materials_by_rarity(): void
    {
        $user = $this->player();
        $item = $this->gear($user, 'gear-fang', 'epic');

        $this->actingAs($user)->postJson('/api/dungeons/craft/dismantle', ['item' => $item])
            ->assertCreated()->assertJsonPath('material', 'claw')->assertJsonPath('count', 3);

        $this->assertNull(UserInventory::find($item));
        $this->assertSame(3, $this->qty($user, 'claw'));

        // Повторная переплавка того же предмета невозможна
        $this->actingAs($user)->postJson('/api/dungeons/craft/dismantle', ['item' => $item])->assertStatus(422);
    }

    // ---- Рынок ----

    public function test_sell_and_buy_material_moves_goods_coins_and_fee(): void
    {
        $seller = $this->player(0);
        $buyer = $this->player(5000);
        $this->mats($seller, ['claw' => 10]);

        $id = $this->actingAs($seller)->postJson('/api/market/sell-material', ['material' => 'claw', 'qty' => 4, 'price' => 1000])
            ->assertCreated()->json('id');

        // Товар в эскроу
        $this->assertSame(6, $this->qty($seller, 'claw'));

        $this->actingAs($buyer)->postJson("/api/market/{$id}/buy")->assertOk();

        $this->assertSame(4, $this->qty($buyer, 'claw'));
        $this->assertSame(4000, (int) $buyer->fresh()->apex_coins);
        // 5% комиссии остаётся у рынка
        $this->assertSame(950, (int) $seller->fresh()->apex_coins);

        // Лот продан: второй раз не купить
        $this->actingAs($buyer)->postJson("/api/market/{$id}/buy")->assertStatus(422);
        $this->assertSame(4, $this->qty($buyer, 'claw'));
    }

    public function test_sell_gear_takes_item_out_of_inventory_and_buyer_gets_same_stats(): void
    {
        $seller = $this->player(0);
        $buyer = $this->player(10000);
        $item = $this->gear($seller, 'gear-fang', 'epic');
        $stats = UserInventory::find($item)->gear_stats;

        $id = $this->actingAs($seller)->postJson('/api/market/sell-gear', ['item' => $item, 'price' => 500])->assertCreated()->json('id');
        $this->assertNull(UserInventory::find($item));

        $this->actingAs($buyer)->postJson("/api/market/{$id}/buy")->assertOk();

        $row = UserInventory::where('user_id', $buyer->id)->first();
        $this->assertSame($stats['rarity'], $row->gear_stats['rarity']);
        $this->assertSame($stats['atk'], $row->gear_stats['atk']);
    }

    public function test_cannot_buy_own_listing_or_without_coins(): void
    {
        $seller = $this->player(10);
        $poor = $this->player(5);
        $this->mats($seller, ['claw' => 3]);

        $id = $this->actingAs($seller)->postJson('/api/market/sell-material', ['material' => 'claw', 'qty' => 1, 'price' => 100])->json('id');

        $this->actingAs($seller)->postJson("/api/market/{$id}/buy")->assertStatus(422);
        $this->actingAs($poor)->postJson("/api/market/{$id}/buy")->assertStatus(422);

        $this->assertSame(0, $this->qty($poor, 'claw'));
        $this->assertSame(5, (int) $poor->fresh()->apex_coins);
    }

    public function test_cancel_returns_goods_and_only_seller_can_cancel(): void
    {
        $seller = $this->player();
        $other = $this->player();
        $this->mats($seller, ['claw' => 5]);

        $id = $this->actingAs($seller)->postJson('/api/market/sell-material', ['material' => 'claw', 'qty' => 5, 'price' => 100])->json('id');
        $this->assertSame(0, $this->qty($seller, 'claw'));

        $this->actingAs($other)->postJson("/api/market/{$id}/cancel")->assertStatus(422);
        $this->actingAs($seller)->postJson("/api/market/{$id}/cancel")->assertOk();

        $this->assertSame(5, $this->qty($seller, 'claw'));
        $this->actingAs($seller)->postJson("/api/market/{$id}/cancel")->assertStatus(422);
    }

    public function test_new_accounts_cannot_sell_and_limits_apply(): void
    {
        $fresh = User::factory()->create(['created_at' => now()]);
        $this->mats($fresh, ['claw' => 5]);
        $this->actingAs($fresh)->postJson('/api/market/sell-material', ['material' => 'claw', 'qty' => 1, 'price' => 50])->assertStatus(422);

        $user = $this->player();
        $this->mats($user, ['claw' => 50]);
        $this->actingAs($user)->postJson('/api/market/sell-material', ['material' => 'claw', 'qty' => 100, 'price' => 50])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/market/sell-material', ['material' => 'claw', 'qty' => 99, 'price' => 50])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/market/sell-material', ['material' => 'claw', 'qty' => 1, 'price' => 5000000])->assertStatus(422);

        for ($i = 0; $i < MarketService::MAX_ACTIVE; $i++) {
            $this->actingAs($user)->postJson('/api/market/sell-material', ['material' => 'claw', 'qty' => 1, 'price' => 10])->assertCreated();
        }
        $this->actingAs($user)->postJson('/api/market/sell-material', ['material' => 'claw', 'qty' => 1, 'price' => 10])->assertStatus(422);
    }

    public function test_expired_listings_return_goods_to_seller(): void
    {
        $seller = $this->player();
        $this->mats($seller, ['claw' => 5]);
        $this->actingAs($seller)->postJson('/api/market/sell-material', ['material' => 'claw', 'qty' => 5, 'price' => 100]);

        $this->travel(MarketService::HOURS + 1)->hours();
        $this->assertSame(1, app(MarketService::class)->expireDue());
        $this->assertSame(5, $this->qty($seller, 'claw'));
        // Повторный прогон ничего не вернёт второй раз
        $this->assertSame(0, app(MarketService::class)->expireDue());
        $this->assertSame(5, $this->qty($seller, 'claw'));
    }

    public function test_market_listing_shows_to_others_and_requires_auth(): void
    {
        $seller = $this->player();
        $viewer = $this->player();
        $this->mats($seller, ['claw' => 2]);
        $this->actingAs($seller)->postJson('/api/market/sell-material', ['material' => 'claw', 'qty' => 2, 'price' => 80]);

        $this->actingAs($viewer)->getJson('/api/market')
            ->assertOk()->assertJsonCount(1, 'listings')->assertJsonCount(0, 'mine')->assertJsonPath('listings.0.unit', 40);
        $this->actingAs($seller)->getJson('/api/market')->assertJsonCount(0, 'listings')->assertJsonCount(1, 'mine');
    }

    public function test_market_requires_auth(): void
    {
        $this->getJson('/api/market')->assertUnauthorized();
    }
}
