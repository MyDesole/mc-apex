<?php

namespace Tests\Feature;

use App\Domains\Players\Services\DungeonService;
use App\Domains\Players\Support\PetFarmTiers;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Services\RubleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Фаза 4: Рубли — заработок в данжах и на фермах, ускорение за Рубли, карта-мир.
 */
class RubleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (PetFarmTiers::SPECIES as $tier => $species) {
            foreach ($species as $name) {
                ShopItem::firstOrCreate(['slug' => "pet-{$name}"], [
                    'name' => "Питомец {$name}", 'type' => 'profile_pet', 'effect_value' => $name,
                    'price' => 1000, 'is_active' => true, 'rarity' => 'epic',
                ]);
            }
        }
    }

    private function pet(User $owner, string $species): UserInventory
    {
        $item = ShopItem::where('slug', "pet-{$species}")->firstOrFail();

        return UserInventory::create(['user_id' => $owner->id, 'shop_item_id' => $item->id, 'quantity' => 1])->fresh();
    }

    private function farmer(int $rubles = 0): User
    {
        $user = User::factory()->create(['apex_coins' => 100000]);
        DB::table('users')->where('id', $user->id)->update(['rubles' => $rubles]);
        DB::table('personal_farms')->insert(['user_id' => $user->id, 'plots' => 3, 'created_at' => now(), 'updated_at' => now()]);

        return $user->fresh();
    }

    public function test_earn_is_idempotent_and_logged(): void
    {
        $user = User::factory()->create();

        $this->assertSame(5, RubleService::earn($user, 5, RubleService::SOURCE_FARM, 'тест', 'k1'));
        $this->assertSame(0, RubleService::earn($user, 5, RubleService::SOURCE_FARM, 'тест', 'k1'));

        $this->assertSame(5, RubleService::balance($user));
        $this->assertDatabaseCount('ruble_transactions', 1);
    }

    public function test_earnings_have_no_daily_cap(): void
    {
        $user = User::factory()->create();

        $this->assertSame(500, RubleService::earn($user, 500, RubleService::SOURCE_RUBLE_DUNGEON, 'тест'));
        $this->assertSame(500, RubleService::earn($user, 500, RubleService::SOURCE_RUBLE_DUNGEON, 'тест'));
        $this->assertSame(1000, RubleService::balance($user));
    }

    public function test_spend_needs_enough_rubles_and_never_goes_negative(): void
    {
        $user = User::factory()->create();
        RubleService::earn($user, 3, RubleService::SOURCE_FARM, 'тест');

        $this->expectException(\RuntimeException::class);

        try {
            RubleService::spend($user, 4, 'тест');
        } finally {
            $this->assertSame(3, RubleService::balance($user));
        }
    }

    public function test_ordinary_dungeons_never_pay_rubles(): void
    {
        // Рубли падают только в Рублёвых копях: у остальных подземелий валюты нет
        foreach (\App\Domains\Players\Support\Dungeons::DUNGEONS as $key => $dungeon) {
            $this->assertSame($key === 'rubledeep', ($dungeon['currency'] ?? null) === 'rubles', $key);
        }

        $this->assertFalse(method_exists(DungeonService::class, 'grantRubles'));
    }

    public function test_speedup_plot_costs_rubles_per_hour(): void
    {
        $user = $this->farmer(100);
        $this->actingAs($user)->postJson('/api/farm/personal/plant', ['species' => 'panda'])->assertCreated();
        $plot = DB::table('farm_plots')->first();

        // Осталось 12 часов — 24 рубля
        $this->actingAs($user)->postJson("/api/farm/personal/plots/{$plot->id}/speedup")
            ->assertOk()->assertJsonPath('spent', 24)->assertJsonPath('rubles', 76);

        $this->actingAs($user)->postJson("/api/farm/personal/plots/{$plot->id}/harvest")->assertOk();
        // Второй раз ускорять нечего
        $this->actingAs($user)->postJson("/api/farm/personal/plots/{$plot->id}/speedup")->assertStatus(422);
    }

    public function test_speedup_without_rubles_is_refused_and_changes_nothing(): void
    {
        $user = $this->farmer(1);
        $this->actingAs($user)->postJson('/api/farm/personal/plant', ['species' => 'panda'])->assertCreated();
        $plot = DB::table('farm_plots')->first();

        $this->actingAs($user)->postJson("/api/farm/personal/plots/{$plot->id}/speedup")->assertStatus(422);
        $this->assertSame(1, RubleService::balance($user));
        $this->assertTrue(\Illuminate\Support\Carbon::parse(DB::table('farm_plots')->first()->ready_at)->isFuture());
    }

    public function test_cannot_speed_up_other_players_plot(): void
    {
        $owner = $this->farmer(50);
        $thief = $this->farmer(50);
        $this->actingAs($owner)->postJson('/api/farm/personal/plant', ['species' => 'panda'])->assertCreated();
        $plot = DB::table('farm_plots')->first();

        $this->actingAs($thief)->postJson("/api/farm/personal/plots/{$plot->id}/speedup")->assertStatus(422);
        $this->assertSame(50, RubleService::balance($thief));
    }

    public function test_speedup_mating_and_reward_for_claim(): void
    {
        $user = $this->farmer(20);
        $a = $this->pet($user, 'slime');
        $b = $this->pet($user, 'frog');

        $pair = $this->actingAs($user)->postJson('/api/farm/personal/mating', ['parent1' => $a->id, 'parent2' => $b->id])->json();

        // 24 часа × 2 = 48 — не хватает
        $this->actingAs($user)->postJson("/api/farm/personal/mating/{$pair['id']}/speedup")->assertStatus(422);

        DB::table('users')->where('id', $user->id)->update(['rubles' => 50]);
        $this->actingAs($user)->postJson("/api/farm/personal/mating/{$pair['id']}/speedup")->assertOk()->assertJsonPath('spent', 48);
        $this->actingAs($user)->postJson("/api/farm/personal/mating/{$pair['id']}/claim")->assertOk()->assertJsonMissingPath('rubles');
        // Рубли потрачены на ускорение: 50 − 48, награды за спаривание нет
        $this->assertSame(2, RubleService::balance($user));
    }

    public function test_cross_into_epic_and_legendary_pays_no_rubles(): void
    {
        $user = $this->farmer();
        $r1 = $this->pet($user, 'panda');
        $r2 = $this->pet($user, 'capybara');

        $this->actingAs($user)->postJson('/api/farm/personal/cross', ['parent1' => $r1->id, 'parent2' => $r2->id])
            ->assertCreated()->assertJsonPath('tier', 'epic')->assertJsonMissingPath('rubles');

        $e1 = $this->pet($user, 'robot');
        $e2 = $this->pet($user, 'ghost');
        $this->actingAs($user)->postJson('/api/farm/personal/cross', ['parent1' => $e1->id, 'parent2' => $e2->id])
            ->assertCreated()->assertJsonPath('tier', 'legendary')->assertJsonMissingPath('rubles');

        $this->assertSame(0, RubleService::balance($user));
    }

    public function test_world_state_has_balances_pets_and_badges(): void
    {
        $user = $this->farmer(7);
        $this->pet($user, 'cat');

        $this->actingAs($user)->getJson('/api/world')
            ->assertOk()
            ->assertJsonPath('rubles', 7)
            ->assertJsonPath('personal_unlocked', true)
            ->assertJsonCount(1, 'pets')
            ->assertJsonStructure(['coins', 'badges' => ['farm', 'personal', 'expeditions', 'dungeon']]);
    }

    public function test_world_requires_auth(): void
    {
        $this->getJson('/api/world')->assertUnauthorized();
    }

    public function test_rubles_cannot_be_gifted(): void
    {
        // Переводов нет: роутов с рублями в обмене и подарках не существует
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())->map(fn ($r) => $r->uri())->implode(' ');
        $this->assertStringNotContainsString('rubles/gift', $routes);
        $this->assertStringNotContainsString('rubles/transfer', $routes);
    }
}
