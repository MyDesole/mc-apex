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

    public function test_xp_has_no_daily_cap(): void
    {
        $pet = $this->pet();

        $this->assertSame(5000, PetService::grantXp($pet->id, 5000, 1));
        $this->assertSame(5000, PetService::grantXp($pet->id, 5000, 1));
        $this->assertSame(10000, (int) $pet->fresh()->pet_xp);
        $this->assertSame(0, PetService::grantXp($pet->id, 0, 1));
    }

    public function test_level_100_takes_about_two_months_of_active_play(): void
    {
        // Модель дня: 3 захода в подземелье лучшего доступного уровня, одна
        // экспедиция и пять боёв арены. Тот же расчёт, что в комментарии к кривой.
        $mult = fn (int $n) => max(1, intdiv($n, 10) - 1);
        $floors = function (int $n) {
            foreach ([[8, 2], [16, 3], [24, 3], [28, 4], [40, 4], [55, 5], [75, 5], [999, 6]] as [$limit, $f]) {
                if ($n < $limit) {
                    return $f;
                }
            }
        };
        $expedition = function (int $n) {
            $xp = 0;
            foreach ([1 => 3, 3 => 8, 8 => 15, 15 => 24, 30 => 40, 45 => 60, 60 => 90, 80 => 130] as $level => $value) {
                if ($n >= $level) {
                    $xp = $value;
                }
            }

            return $xp;
        };

        $days = 0.0;
        for ($n = 1; $n < 100; $n++) {
            $perDay = (3 * 18 * $floors($n) + $expedition($n) + 15) * $mult($n);
            $days += (PetService::xpFor($n + 1) - PetService::xpFor($n)) / $perDay;
        }

        $this->assertGreaterThan(50, $days);
        $this->assertLessThan(70, $days);
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
