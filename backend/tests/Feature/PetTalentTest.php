<?php

namespace Tests\Feature;

use App\Domains\Players\Support\DungeonBattle;
use App\Domains\Players\Support\PetTalents;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Академия: очки за уровни, дерево, изучение, сброс и эффекты в бою.
 */
class PetTalentTest extends TestCase
{
    use RefreshDatabase;

    private function pet(User $owner, int $xp, string $species = 'slime'): UserInventory
    {
        $item = ShopItem::firstOrCreate(['slug' => "pet-{$species}"], [
            'name' => "Питомец {$species}", 'type' => 'profile_pet', 'effect_value' => $species,
            'price' => 1000, 'is_active' => true, 'rarity' => 'rare',
        ]);

        $row = UserInventory::create(['user_id' => $owner->id, 'shop_item_id' => $item->id, 'quantity' => 1]);
        $row->forceFill([
            'pet_xp' => $xp,
            'pet_traits' => ['model' => 'classic', 'backdrop' => 'none', 'accessory' => 'none'],
        ])->save();

        return $row->fresh();
    }

    private function member(array $fx): array
    {
        return [
            'inventory_id' => 1, 'name' => 'Тестик', 'species' => 'cat', 'element' => 'fire',
            'hp' => 100, 'max' => 100, 'atk' => 50, 'def' => 10, 'spd' => 10, 'gear' => [], 'talent_fx' => $fx,
        ];
    }

    private function enemy(): array
    {
        return [
            'name' => 'Манекен', 'sprite' => 'mob-rat', 'is_boss' => false, 'element' => 'beast', 'trait' => 'none',
            'trait_name' => '', 'trait_text' => '', 'hp' => 10_000_000, 'atk' => 1, 'def' => 0, 'spd' => 5,
        ];
    }

    public function test_tree_has_three_branches_of_ten_plus_an_element_path_and_does_not_fit_in_99_points(): void
    {
        $this->assertCount(4, PetTalents::BRANCHES);

        $all = 0;

        foreach (['fury', 'bastion', 'spirit'] as $branch) {
            $talents = array_filter(PetTalents::TALENTS, fn ($t) => $t['branch'] === $branch);
            $this->assertCount(10, $talents, $branch);
            $all += array_sum(array_column($talents, 'max'));
        }

        // Путь стихии: по три таланта на каждую из восьми стихий
        foreach (array_keys(\App\Domains\Players\Support\DungeonBattle::ELEMENTS) as $element) {
            $own = array_filter(PetTalents::TALENTS, fn ($t) => ($t['element'] ?? null) === $element);
            $this->assertCount(3, $own, $element);
            $this->assertSame([1, 2, 3], array_values(array_column($own, 'tier')));
        }

        $this->assertCount(24, array_filter(PetTalents::TALENTS, fn ($t) => $t['branch'] === 'element'));
        $this->assertCount(54, PetTalents::TALENTS);
        $this->assertGreaterThan(PetTalents::totalPoints(100), $all, 'всё древо не помещается в очки 100 уровня');

        foreach (PetTalents::TALENTS as $key => $talent) {
            foreach (array_keys($talent['effect']) as $effect) {
                $this->assertTrue(
                    in_array($effect, [...PetTalents::PERK_KEYS, ...PetTalents::MOD_KEYS, ...PetTalents::STAT_KEYS], true),
                    "{$key}: неизвестный эффект {$effect}",
                );
            }
        }
    }

    public function test_points_come_from_levels_and_effects_sum_by_rank(): void
    {
        $this->assertSame(0, PetTalents::totalPoints(1));
        $this->assertSame(99, PetTalents::totalPoints(100));

        $fx = PetTalents::effects(['w_might' => 3, 'g_hide' => 2, 'g_fort' => 1, 'nope' => 5, 'w_precision' => 0]);

        $this->assertSame(['atk_pct' => 6, 'hp_pct' => 8], $fx);

        // Ранг не выше максимума
        $this->assertSame(['atk_pct' => 10], PetTalents::effects(['w_might' => 99]));

        $stats = PetTalents::applyStats(['atk' => 100, 'def' => 100, 'hp' => 100, 'spd' => 10], ['atk_pct' => 10, 'hp_pct' => 20, 'spd' => 3]);
        $this->assertSame(['atk' => 110, 'def' => 100, 'hp' => 120, 'spd' => 13], $stats);
    }

    public function test_learn_spends_free_points_and_respects_gates_and_max(): void
    {
        $user = User::factory()->create(['apex_coins' => 0]);
        $pet = $this->pet($user, 950); // 20 уровень, 19 очков

        $this->actingAs($user)->getJson("/api/talents/{$pet->id}")
            ->assertOk()->assertJsonPath('points.total', 19)->assertJsonPath('points.free', 19);

        // Ярус 2 закрыт, пока в ветке нет 5 очков
        $this->actingAs($user)->postJson("/api/talents/{$pet->id}/learn", ['talent' => 'w_pierce'])->assertStatus(422);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->postJson("/api/talents/{$pet->id}/learn", ['talent' => 'w_might'])->assertOk();
        }

        // Пятый ранг — максимум
        $this->actingAs($user)->postJson("/api/talents/{$pet->id}/learn", ['talent' => 'w_might'])->assertStatus(422);

        // Ярус открылся
        $this->actingAs($user)->postJson("/api/talents/{$pet->id}/learn", ['talent' => 'w_pierce'])
            ->assertOk()
            ->assertJsonPath('points.free', 13)
            ->assertJsonPath('effects.atk_pct', 10)
            ->assertJsonPath('effects.pierce', 4);

        $this->actingAs($user)->postJson("/api/talents/{$pet->id}/learn", ['talent' => 'nope'])->assertStatus(422);
    }

    public function test_element_path_belongs_to_the_pets_element(): void
    {
        $user = User::factory()->create();
        $pet = $this->pet($user, 950, 'dragon'); // огонь

        $tree = $this->actingAs($user)->getJson("/api/talents/{$pet->id}")->assertOk();
        $element = collect($tree->json('branches'))->firstWhere('key', 'element');

        $this->assertSame('Путь стихии: Огонь', $element['name']);
        $this->assertSame(['e_fire_1', 'e_fire_2', 'e_fire_3'], array_column($element['talents'], 'key'));

        $this->actingAs($user)->postJson("/api/talents/{$pet->id}/learn", ['talent' => 'e_fire_1'])
            ->assertOk()->assertJsonPath('effects.atk_pct', 3);

        // Таланты чужой стихии выучить нельзя
        $this->actingAs($user)->postJson("/api/talents/{$pet->id}/learn", ['talent' => 'e_water_1'])->assertStatus(422);
    }

    public function test_no_points_without_levels(): void
    {
        $user = User::factory()->create();
        $pet = $this->pet($user, 0);

        $this->actingAs($user)->postJson("/api/talents/{$pet->id}/learn", ['talent' => 'w_might'])->assertStatus(422);
    }

    public function test_cannot_touch_someone_elses_pet(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $pet = $this->pet($owner, 950);

        $this->actingAs($other)->getJson("/api/talents/{$pet->id}")->assertStatus(422);
        $this->actingAs($other)->postJson("/api/talents/{$pet->id}/learn", ['talent' => 'w_might'])->assertStatus(422);
        $this->actingAs($other)->postJson("/api/talents/{$pet->id}/respec")->assertStatus(422);
    }

    public function test_respec_costs_coins_and_returns_points(): void
    {
        $user = User::factory()->create(['apex_coins' => PetTalents::RESPEC_COST]);
        $pet = $this->pet($user, 950);

        $this->actingAs($user)->postJson("/api/talents/{$pet->id}/respec")->assertStatus(422);

        $this->actingAs($user)->postJson("/api/talents/{$pet->id}/learn", ['talent' => 'g_hide'])->assertOk();
        $this->actingAs($user)->postJson("/api/talents/{$pet->id}/respec")
            ->assertOk()
            ->assertJsonPath('points.free', 19)
            ->assertJsonPath('effects', []);

        $this->assertSame(0, (int) $user->fresh()->apex_coins);
        $this->assertSame(0, DB::table('pet_talents')->where('inventory_id', $pet->id)->count());

        // Без монет второй сброс не пройдёт
        $this->actingAs($user)->postJson("/api/talents/{$pet->id}/learn", ['talent' => 'g_hide'])->assertOk();
        $this->actingAs($user)->postJson("/api/talents/{$pet->id}/respec")->assertStatus(422);
    }

    public function test_roster_shows_free_points(): void
    {
        $user = User::factory()->create();
        $this->pet($user, 950);

        $this->actingAs($user)->getJson('/api/talents')
            ->assertOk()->assertJsonPath('pets.0.free', 19)->assertJsonPath('pets.0.level', 20);
    }

    public function test_talent_perks_and_mods_reach_the_battle(): void
    {
        $battle = new DungeonBattle();
        $state = $battle->begin([$this->member(['crit' => 10, 'vamp' => 6, 'skill_power' => 20, 'sig' => 24, 'atk_pct' => 5])], $this->enemy());

        $this->assertSame(10, $state['units'][0]['perks']['crit']);
        $this->assertSame(6, $state['units'][0]['perks']['vamp']);
        $this->assertSame(['skill_power' => 20, 'sig' => 24], $state['units'][0]['mods']);
    }

    public function test_skill_cooldown_talent_makes_skill_ready_sooner(): void
    {
        $battle = new DungeonBattle();

        $plain = $battle->resolveRound($battle->begin([$this->member([])], $this->enemy()), [1 => 'skill']);
        $quick = $battle->resolveRound($battle->begin([$this->member(['skill_cd' => 1])], $this->enemy()), [1 => 'skill']);

        $this->assertSame($plain['units'][0]['cd'] - 1, $quick['units'][0]['cd']);
    }
}
