<?php

namespace Tests\Feature;

use App\Domains\Players\Services\LavkaService;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use Database\Seeders\DungeonGearSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Лавка: новые вещи за Осколки Разлома и за ApexCoin. */
class LavkaItemsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DungeonGearSeeder::class);
    }

    private function player(int $coins = 0, int $shards = 0): User
    {
        $user = User::factory()->create(['apex_coins' => $coins]);
        DB::table('users')->where('id', $user->id)->update(['rift_shards' => $shards]);

        return $user->fresh();
    }

    private function pet(User $owner, int $xp = 950): UserInventory
    {
        $item = ShopItem::firstOrCreate(['slug' => 'pet-slime'], [
            'name' => 'Питомец slime', 'type' => 'profile_pet', 'effect_value' => 'slime',
            'price' => 1000, 'is_active' => true, 'rarity' => 'rare',
        ]);

        $row = UserInventory::create(['user_id' => $owner->id, 'shop_item_id' => $item->id, 'quantity' => 1]);
        $row->forceFill(['pet_xp' => $xp, 'pet_traits' => ['model' => 'classic', 'backdrop' => 'none', 'accessory' => 'none']])->save();

        return $row->fresh();
    }

    private function gearCount(User $user): int
    {
        return UserInventory::where('user_id', $user->id)->whereHas('item', fn ($q) => $q->where('type', ShopItem::TYPE_PET_GEAR))->count();
    }

    public function test_lavka_has_no_apexcoin_shelf_and_more_shard_items(): void
    {
        $state = $this->actingAs($this->player())->getJson('/api/lavka')->assertOk();

        $this->assertGreaterThanOrEqual(9, count($state->json('shard_items')));
        $this->assertSame(count(LavkaService::SHARDS), count($state->json('shard_items')));
        $this->assertNull($state->json('coin_items'), 'за ApexCoin в Лавке ничего не продаётся');

        $this->actingAs($this->player(1_000_000))->postJson('/api/lavka/coins/xp_potion')->assertStatus(404);
    }

    public function test_privilege_assortment_is_pvp_not_survival(): void
    {
        $items = LavkaService::PRIVILEGES;

        $this->assertCount(17, $items);
        $this->assertArrayNotHasKey('kit_slot', $items, 'слоты наборов в дуэлях и так бесплатны');
        $this->assertArrayNotHasKey('kit_layout', $items);
        $this->assertSame(['Статусы', 'Бой', 'Эффекты', 'Чат', 'Лобби'], array_values(array_unique(array_column($items, 'group'))));

        foreach ($items as $key => $item) {
            $this->assertGreaterThan(0, $item['price'], $key);

            foreach ($item['commands'] as $command) {
                $this->assertStringStartsWith('lp user {player} ', $command, $key);
                // Сервер про PvP: ни домов, ни верстака, ни эндер-сундука
                $this->assertDoesNotMatchRegularExpression('/sethome|workbench|enderchest|repair|feed|warp/i', $command, $key);
            }
        }
    }

    public function test_permanent_privilege_is_bought_once_and_marked_owned(): void
    {
        $user = $this->player();
        DB::table('users')->where('id', $user->id)->update(['rubles' => 500, 'minecraft_username' => 'Steve_1']);

        $this->actingAs($user->fresh())->postJson('/api/lavka/privileges/kill_flame')->assertCreated();
        $this->assertSame(500 - 160, (int) DB::table('users')->find($user->id)->rubles);

        $state = $this->actingAs($user->fresh())->getJson('/api/lavka');
        $flame = collect($state->json('privileges'))->firstWhere('key', 'kill_flame');
        $this->assertTrue($flame['owned']);
        $this->assertTrue($flame['permanent']);

        // Через сутки купить постоянное снова всё равно нельзя
        $this->travel(2)->days();
        $this->actingAs($user->fresh())->postJson('/api/lavka/privileges/kill_flame')->assertStatus(422);
        $this->assertSame(500 - 160, (int) DB::table('users')->find($user->id)->rubles);

        // Временное продлевать можно
        $this->actingAs($user->fresh())->postJson('/api/lavka/privileges/vip_7')->assertCreated();
        $this->travel(2)->days();
        $this->actingAs($user->fresh())->postJson('/api/lavka/privileges/vip_7')->assertCreated();
    }

    public function test_shard_items_xp_tome_and_talent_scroll(): void
    {
        $user = $this->player(0, 100);
        $pet = $this->pet($user);

        $this->actingAs($user)->postJson('/api/lavka/shards/xp_tome', ['pet' => $pet->id])->assertCreated();
        $this->assertSame(950 + 4000, (int) $pet->fresh()->pet_xp);

        // Свиток забвения без изученных талантов не тратится
        $this->actingAs($user->fresh())->postJson('/api/lavka/shards/respec_scroll', ['pet' => $pet->id])->assertStatus(422);
        $this->assertSame(80, (int) DB::table('users')->find($user->id)->rift_shards);

        DB::table('pet_talents')->insert(['inventory_id' => $pet->id, 'talent' => 'w_might', 'rank' => 2, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user->fresh())->postJson('/api/lavka/shards/respec_scroll', ['pet' => $pet->id])->assertCreated();
        $this->assertSame(0, DB::table('pet_talents')->where('inventory_id', $pet->id)->count());
        $this->assertSame(65, (int) DB::table('users')->find($user->id)->rift_shards);
    }

    public function test_shard_crates_grant_gear_materials_and_relics(): void
    {
        $user = $this->player(0, 400);

        $this->actingAs($user)->postJson('/api/lavka/shards/epic_crate')->assertCreated();
        $this->actingAs($user->fresh())->postJson('/api/lavka/shards/relic_chest')->assertCreated();
        $this->actingAs($user->fresh())->postJson('/api/lavka/shards/material_set')->assertCreated();
        $this->actingAs($user->fresh())->postJson('/api/lavka/shards/pantheon_core')->assertCreated();

        $this->assertSame(2, $this->gearCount($user));
        $this->assertSame(1, (int) DB::table('dungeon_materials')->where('user_id', $user->id)->where('material', 'core_pantheon')->value('quantity'));
        $this->assertSame(400 - 18 - 90 - 12 - 40, (int) DB::table('users')->find($user->id)->rift_shards);

        $relic = UserInventory::where('user_id', $user->id)->whereHas('item', fn ($q) => $q->where('type', ShopItem::TYPE_PET_GEAR)->where('slug', 'like', 'gear-%'))
            ->get()->first(fn ($row) => ! empty(\App\Domains\Players\Support\Dungeons::GEAR[$row->item->slug]['relic']));

        $this->assertNotNull($relic, 'в сундуке реликвий лежала реликвия');
    }

    public function test_shards_are_required(): void
    {
        $user = $this->player(0, 5);

        $this->actingAs($user)->postJson('/api/lavka/shards/relic_chest')->assertStatus(422);
        $this->assertSame(0, $this->gearCount($user));
    }
}
