<?php

namespace Tests\Feature;

use App\Domains\Shop\Models\CaseOpening;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Services\LootCaseService;
use App\Domains\Shop\Support\LootCases;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Кейсы: бесплатный раз в день, платные за ApexCoin, повторы, гарантия.
 */
class LootCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (LootCases::RARITIES as $i => $rarity) {
            ShopItem::create([
                'slug' => "frame_{$rarity}",
                'name' => "Рамка {$rarity}",
                'type' => ShopItem::TYPE_AVATAR_FRAME,
                'rarity' => $rarity,
                'price' => 100 * ($i + 1),
                'is_active' => true,
            ]);
        }
    }

    public function test_guest_sees_cases(): void
    {
        $this->getJson('/api/cases')
            ->assertOk()
            ->assertJsonCount(3, 'cases')
            ->assertJsonPath('cases.0.slug', 'daily')
            ->assertJsonPath('cases.0.price', 0);
    }

    public function test_free_case_opens_once_per_day(): void
    {
        $user = User::factory()->create(['apex_coins' => 0]);

        $response = $this->actingAs($user)->postJson('/api/shop/cases/daily/open')->assertOk();

        $this->assertCount(LootCaseService::REEL_LENGTH, $response->json('reel'));
        $this->assertSame($response->json('prize'), $response->json('reel.' . LootCaseService::REEL_WIN_INDEX));
        $response->assertJsonPath('case.available', false);

        $this->actingAs($user)->postJson('/api/shop/cases/daily/open')->assertStatus(422);
        $this->actingAs($user)->getJson('/api/cases/status')->assertJsonPath('daily_available', false);

        // Завтра — снова можно
        $this->travel(1)->days();
        $this->actingAs($user)->postJson('/api/shop/cases/daily/open')->assertOk();
    }

    public function test_paid_case_needs_coins_and_charges_price(): void
    {
        $poor = User::factory()->create(['apex_coins' => 10]);
        $this->actingAs($poor)->postJson('/api/shop/cases/seeker/open')->assertStatus(422);

        $rich = User::factory()->create(['apex_coins' => 1000]);
        $response = $this->actingAs($rich)->postJson('/api/shop/cases/seeker/open')->assertOk();

        $this->assertSame(1000 - 750 + $response->json('prize.coins'), (int) $rich->fresh()->apex_coins);
    }

    public function test_item_prize_lands_in_inventory_and_duplicate_pays_coins(): void
    {
        // Королевский: без пустоты и обычных — предмет почти наверняка
        $user = User::factory()->create(['apex_coins' => 100000]);

        $item = null;
        for ($i = 0; $i < 20 && ! $item; $i++) {
            $item = $this->actingAs($user)->postJson('/api/shop/cases/royal/open')->json('prize.item');
        }

        $this->assertNotNull($item);
        $this->assertDatabaseHas('user_inventory', ['user_id' => $user->id, 'shop_item_id' => $item['id']]);

        // Каждый предмет лежит в инвентаре в одном экземпляре: повторы стали монетами
        $this->assertSame(0, UserInventory::where('user_id', $user->id)->where('quantity', '>', 1)->count());
        $duplicates = CaseOpening::where('user_id', $user->id)->where('duplicate', true)->get();
        foreach ($duplicates as $opening) {
            $this->assertGreaterThan(0, $opening->coins);
        }
    }

    public function test_pity_guarantees_epic_after_streak(): void
    {
        $user = User::factory()->create(['apex_coins' => 10000]);

        for ($i = 0; $i < LootCases::PITY_AFTER - 1; $i++) {
            CaseOpening::create(['user_id' => $user->id, 'case_slug' => 'seeker', 'outcome' => 'common', 'price' => 750]);
        }

        $this->actingAs($user)->getJson('/api/cases')->assertJsonPath('cases.1.pity_left', 1);

        $response = $this->actingAs($user)->postJson('/api/shop/cases/seeker/open')->assertOk();

        // Эпик мог выпасть и сам — тогда гарантия не понадобилась
        $this->assertContains($response->json('prize.outcome'), ['epic', 'legendary']);
        $response->assertJsonPath('case.pity_left', LootCases::PITY_AFTER);
    }

    public function test_unknown_case(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/shop/cases/mystery/open')->assertStatus(422);
    }
}
