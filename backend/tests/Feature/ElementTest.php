<?php

namespace Tests\Feature;

use App\Domains\Players\Services\PetArenaService;
use App\Domains\Players\Support\DungeonBattle;
use App\Domains\Players\Support\PetBattleEngine;
use App\Domains\Players\Support\PetFarmTiers;
use App\Domains\Players\Support\PetNames;
use App\Domains\Players\Support\PetTalents;
use Tests\TestCase;

/**
 * Восемь стихий: у каждой есть умения в обоих боевых движках, а у каждого
 * вида питомца — стихия, имя и ступень.
 */
class ElementTest extends TestCase
{
    public function test_every_element_has_abilities_in_both_engines(): void
    {
        $this->assertCount(8, DungeonBattle::ELEMENTS);

        foreach (array_keys(DungeonBattle::ELEMENTS) as $element) {
            $this->assertArrayHasKey($element, DungeonBattle::ABILITIES, $element);
            $this->assertArrayHasKey($element, PetBattleEngine::SKILLS, $element);
            $this->assertArrayHasKey($element, PetBattleEngine::ULTIMATES, $element);
            $this->assertArrayHasKey($element, PetTalents::SIGNATURE, $element);
        }
    }

    public function test_every_species_has_an_element_a_name_and_a_tier(): void
    {
        $mapped = array_merge(...array_values(PetArenaService::ELEMENTS));

        foreach (PetFarmTiers::SPECIES as $tier => $species) {
            foreach ($species as $name) {
                $this->assertContains($name, $mapped, "{$name}: нет стихии");
                $this->assertArrayHasKey($name, PetNames::SPECIES, "{$name}: нет имени");
                $this->assertSame($tier, PetFarmTiers::tierOf($name));
                $this->assertArrayHasKey(PetArenaService::element($name), DungeonBattle::ELEMENTS);
            }
        }

        $this->assertSame('wind', PetArenaService::element('griffin'));
        $this->assertSame('earth', PetArenaService::element('golem'));
        $this->assertSame('light', PetArenaService::element('fairy'));
    }

    public function test_new_species_are_sold_and_drawn(): void
    {
        $seeder = (string) file_get_contents(database_path('seeders/ShopItemSeeder.php'));
        $sprites = (string) file_get_contents(base_path('../frontend/src/data/shop/newPetSprites.js'));

        foreach (['hawk', 'cloud', 'turtle', 'mole', 'bat', 'golem', 'fairy', 'griffin'] as $species) {
            $this->assertStringContainsString("'pet-{$species}'", $seeder, $species);
            $this->assertStringContainsString("    {$species}: {", $sprites, $species);
        }
    }

    public function test_new_elements_form_a_triangle_in_the_dungeon_engine(): void
    {
        $multiplier = new \ReflectionMethod(DungeonBattle::class, 'elementMultiplier');
        $battle = new DungeonBattle();

        // ветер > земля > огонь > ветер
        $this->assertSame(1.25, $multiplier->invoke($battle, 'wind', 'earth'));
        $this->assertSame(1.25, $multiplier->invoke($battle, 'earth', 'fire'));
        $this->assertSame(1.25, $multiplier->invoke($battle, 'fire', 'wind'));
        $this->assertSame(0.8, $multiplier->invoke($battle, 'earth', 'wind'));
        // прежние связи на месте
        $this->assertSame(1.25, $multiplier->invoke($battle, 'fire', 'nature'));
        $this->assertSame(1.25, $multiplier->invoke($battle, 'nature', 'earth'));
        $this->assertSame(1.0, $multiplier->invoke($battle, 'light', 'wind'));
    }
}
