<?php

namespace Tests\Feature;

use App\Domains\Players\Support\DungeonBattle;
use App\Domains\Players\Support\Dungeons;
use Tests\TestCase;

/**
 * Свойства вещей и комплекты: справочники согласованы, перки работают в бою.
 */
class GearPerkTest extends TestCase
{
    private function member(array $slugs, int $hp = 100, int $max = 100): array
    {
        return [
            'inventory_id' => 1, 'name' => 'Тестик', 'species' => 'cat', 'element' => 'beast',
            'hp' => $hp, 'max' => $max, 'atk' => 50, 'def' => 10, 'spd' => 10,
            'gear' => array_map(fn ($slug) => ['slug' => $slug], $slugs),
        ];
    }

    private function enemy(int $hp, int $atk, int $spd = 5): array
    {
        return [
            'name' => 'Манекен', 'sprite' => 'mob-rat', 'is_boss' => false, 'element' => 'beast', 'trait' => 'none',
            'trait_name' => '', 'trait_text' => '', 'hp' => $hp, 'atk' => $atk, 'def' => 0, 'spd' => $spd,
        ];
    }

    public function test_catalog_is_big_and_consistent(): void
    {
        $this->assertGreaterThanOrEqual(300, count(Dungeons::GEAR));

        foreach (Dungeons::GEAR as $slug => $gear) {
            foreach (['perk', 'perk2'] as $field) {
                if (isset($gear[$field])) {
                    $this->assertArrayHasKey($gear[$field][0], Dungeons::PERKS, $slug);
                    $this->assertGreaterThan(0, $gear[$field][1], $slug);
                }
            }

            if (isset($gear['set'])) {
                $this->assertContains($slug, Dungeons::SETS[$gear['set']]['pieces'], $slug);
            }
        }

        foreach (Dungeons::SETS as $key => $set) {
            $this->assertCount(3, $set['pieces'], $key);
            $this->assertSame(['weapon', 'armor', 'trinket'], array_map(fn ($s) => Dungeons::GEAR[$s]['slot'], $set['pieces']), $key);
            $this->assertArrayHasKey($set['perk'][0], Dungeons::PERKS, $key);
        }
    }

    public function test_relics_have_two_perks_and_never_drop_from_normal_pools(): void
    {
        $relics = array_filter(Dungeons::GEAR, fn ($g) => ! empty($g['relic']));

        $this->assertGreaterThanOrEqual(12, count($relics));

        foreach ($relics as $slug => $relic) {
            $this->assertArrayHasKey('perk2', $relic, $slug);
            $this->assertNotSame($relic['perk'][0], $relic['perk2'][0], $slug);
        }

        // Реликвия есть у каждой ступени, а обычный дроп и крафт их не берут
        foreach (range(1, 9) as $stage) {
            $this->assertNotEmpty(array_filter($relics, fn ($r) => $r['stage'] === $stage), "реликвия ступени {$stage}");
            $this->assertEmpty(array_filter(Dungeons::gearForStage($stage), fn ($g) => ! empty($g['relic'])));
        }

        // Босс с гарантированной реликвией
        $this->assertTrue(Dungeons::RELIC_CHANCE > 0 && Dungeons::RELIC_CHANCE <= 15);
    }

    public function test_rage_perk_adds_rage_per_hit(): void
    {
        $battle = new DungeonBattle();
        $state = $battle->begin([$this->member([])], $this->enemy(10_000_000, 1));
        $state['units'][0]['perks'] = ['rage' => 20];

        $state = $battle->resolveRound($state, [1 => 'attack']);

        $this->assertGreaterThanOrEqual(28, $state['units'][0]['rage']);
    }

    public function test_set_bonus_grows_with_pieces(): void
    {
        $pieces = Dungeons::SETS['grave']['pieces'];

        $this->assertSame(0, Dungeons::setStatPercent([$pieces[0]]));
        $this->assertSame(8, Dungeons::setStatPercent([$pieces[0], $pieces[1]]));
        $this->assertSame(20, Dungeons::setStatPercent($pieces));

        // Свойство комплекта — только за три вещи
        $two = Dungeons::perksFor([$pieces[0], $pieces[1]]);
        $three = Dungeons::perksFor($pieces);

        $this->assertGreaterThan($two['thorns'] ?? 0, $three['thorns']);
    }

    public function test_perk_text_substitutes_value(): void
    {
        $this->assertSame('Лечит на 8% нанесённого урона', Dungeons::perkText('vamp', 8));
    }

    public function test_phoenix_revives_once(): void
    {
        $battle = new DungeonBattle();
        $state = $battle->begin([$this->member(['gear-y1-star-bone'], 10, 100)], $this->enemy(10_000_000, 10_000, 99));

        $state = $battle->resolveRound($state, [1 => 'attack']);

        $this->assertSame('active', $state['status']);
        $this->assertTrue($state['units'][0]['revived']);
        $this->assertGreaterThan(0, $state['units'][0]['hp']);

        // Второй раз уже не воскреснет
        $state = $battle->resolveRound($state, [1 => 'attack']);
        $this->assertSame('lost', $state['status']);
    }

    public function test_regen_heals_between_rounds(): void
    {
        $battle = new DungeonBattle();
        $state = $battle->begin([$this->member([], 50, 100)], $this->enemy(10_000_000, 1));
        $state['units'][0]['perks'] = ['regen' => 10];

        $state = $battle->resolveRound($state, [1 => 'attack']);

        $this->assertGreaterThan(50, $state['units'][0]['hp']);
    }

    public function test_vampirism_heals_by_damage_dealt(): void
    {
        $battle = new DungeonBattle();
        $state = $battle->begin([$this->member([], 10, 1000)], $this->enemy(10_000_000, 1));
        $state['units'][0]['perks'] = ['vamp' => 100];

        $state = $battle->resolveRound($state, [1 => 'attack']);

        $this->assertGreaterThan(10, $state['units'][0]['hp']);
    }

    public function test_gear_without_perks_changes_nothing(): void
    {
        $battle = new DungeonBattle();
        $state = $battle->begin([$this->member(['gear-fang'])], $this->enemy(1000, 1));

        $this->assertSame([], $state['units'][0]['perks']);
    }
}
