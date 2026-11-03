<?php

namespace Tests\Feature;

use App\Domains\Players\Services\PetService;
use App\Domains\Players\Support\Dungeons;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Прокачка питомцев не быстрее суточного предела; новые данжи.
 */
class PetXpCapTest extends TestCase
{
    use RefreshDatabase;

    private function pet(int $xp = 0): UserInventory
    {
        $owner = User::factory()->create();
        $item = ShopItem::firstOrCreate(['slug' => 'pet-slime'], [
            'name' => 'Питомец slime', 'type' => 'profile_pet', 'effect_value' => 'slime',
            'price' => 1000, 'is_active' => true, 'rarity' => 'rare',
        ]);
        $row = UserInventory::create(['user_id' => $owner->id, 'shop_item_id' => $item->id, 'quantity' => 1]);
        $row->forceFill(['pet_xp' => $xp])->save();

        return $row->fresh();
    }

    public function test_daily_cap_formula(): void
    {
        $this->assertSame(202, PetService::dailyXpCap(1));
        $this->assertSame(400, PetService::dailyXpCap(100));
    }

    public function test_xp_is_capped_per_day_and_resets_tomorrow(): void
    {
        $pet = $this->pet();

        // Лимит 1 уровня — 202; ещё 500 опыта сверху не засчитываются
        $this->assertSame(202, PetService::grantXp($pet->id, 700, 1));
        $this->assertSame(0, PetService::grantXp($pet->id, 50, 1));
        $this->assertSame(202, (int) $pet->fresh()->pet_xp);

        $this->travel(1)->days();
        $this->assertSame(100, PetService::grantXp($pet->id, 100, 1));
        $this->assertSame(302, (int) $pet->fresh()->pet_xp);
    }

    public function test_partial_grant_up_to_the_cap(): void
    {
        $pet = $this->pet();

        $this->assertSame(150, PetService::grantXp($pet->id, 150, 1));
        // Осталось 52 из 202
        $this->assertSame(52, PetService::grantXp($pet->id, 150, 1));
    }

    public function test_level_100_takes_months_at_the_cap(): void
    {
        // Опыта до 100 уровня — 24 750; даже на пределе 400 это месяцы, а не дни
        $days = 24750 / PetService::dailyXpCap(100);
        $this->assertGreaterThan(60, $days);
    }

    public function test_new_dungeons_exist_with_enemies_and_drops(): void
    {
        foreach (['abyss', 'lair', 'throne'] as $key) {
            $dungeon = Dungeons::dungeon($key);
            $this->assertNotNull($dungeon, $key);
            $this->assertGreaterThan(30, $dungeon['min_level']);
            $this->assertNotEmpty(Dungeons::MOBS[$key]);
            // Босс на каждый этаж
            $this->assertCount($dungeon['floors'], Dungeons::BOSSES[$key], $key);
            $this->assertArrayHasKey($dungeon['stage'], Dungeons::DROPS);
            $this->assertArrayHasKey($dungeon['stage'], Dungeons::COINS_PER_BOSS);
            $this->assertArrayHasKey($dungeon['stage'], Dungeons::WIPE_PENALTY);
            $this->assertArrayHasKey($dungeon['stage'], Dungeons::DROP_RARITY);
            $this->assertNotEmpty(Dungeons::gearForStage($dungeon['stage']));
            // Своё снаряжение и свой материал у каждой ступени
            $own = array_filter(Dungeons::GEAR, fn (array $g) => $g['stage'] === $dungeon['stage']);
            $this->assertGreaterThanOrEqual(6, count($own), $key);
            $this->assertNotNull(Dungeons::materialForStage($dungeon['stage']));
        }
    }

    public function test_boss_hp_grows_with_depth_between_dungeons(): void
    {
        $last = 0;
        foreach (['catacombs', 'sunken', 'forge', 'crypt', 'void', 'abyss', 'lair', 'throne'] as $key) {
            $bosses = Dungeons::BOSSES[$key];
            $this->assertGreaterThan($last, $bosses[0]['hp'], $key);
            $last = end($bosses)['hp'];
        }
    }

    public function test_all_dungeon_traits_are_defined(): void
    {
        foreach (Dungeons::MOBS + Dungeons::BOSSES as $list) {
            foreach ($list as $enemy) {
                $this->assertArrayHasKey($enemy['trait'], Dungeons::TRAITS, $enemy['name']);
            }
        }
    }
}
