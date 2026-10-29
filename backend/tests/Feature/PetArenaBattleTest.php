<?php

namespace Tests\Feature;

use App\Domains\Players\Support\PetBattleEngine;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Бой на арене: арены, ярость и ультимейты, контратака, статусы и
 * замысел компьютера, который видно, но не раскрывают.
 */
class PetArenaBattleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $battle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['apex_coins' => 0]);
        $mine = $this->pet($this->user, 'dragon', 200);
        $foe = $this->pet(User::factory()->create(), 'frog', 200);

        $this->battle = $this->actingAs($this->user)
            ->postJson("/api/pets/{$mine->id}/battle", ['opponent' => $foe->id])
            ->assertCreated()
            ->json('id');
    }

    private function pet(User $owner, string $species, int $xp): UserInventory
    {
        $item = ShopItem::firstOrCreate(['slug' => "pet-{$species}"], [
            'name' => "Питомец {$species}", 'type' => 'profile_pet', 'effect_value' => $species,
            'price' => 1000, 'is_active' => true, 'rarity' => 'rare',
        ]);

        $row = UserInventory::create(['user_id' => $owner->id, 'shop_item_id' => $item->id, 'quantity' => 1]);
        $row->forceFill(['pet_xp' => $xp])->save();

        return $row->fresh();
    }

    /** Подправить состояние боя: поля бойцов и замысел компьютера. */
    private function patchState(array $me = [], array $foe = [], ?string $plan = null): void
    {
        $state = json_decode(DB::table('pet_battles')->where('id', $this->battle)->value('state'), true);
        $state['me'] = array_replace($state['me'], $me);
        $state['foe'] = array_replace($state['foe'], $foe);
        $state['plan'] = $plan ?? $state['plan'];

        DB::table('pet_battles')->where('id', $this->battle)->update(['state' => json_encode($state)]);
    }

    private function move(string $move)
    {
        return $this->actingAs($this->user)->postJson("/api/pets/battles/{$this->battle}/move", ['move' => $move]);
    }

    private function logText($response): string
    {
        return collect($response->json('log'))->pluck('text')->implode("\n");
    }

    public function test_battle_starts_on_an_arena_with_rage_and_boosted_health(): void
    {
        $battle = $this->actingAs($this->user)->getJson("/api/pets/battles/{$this->battle}")
            ->assertOk()
            ->assertJsonPath('me.rage', 0)
            ->assertJsonPath('rage_max', PetBattleEngine::RAGE_MAX)
            ->assertJsonPath('ultimates.fire.name', 'Метеор')
            ->assertJsonPath('foe.temper', 'Терпеливый')
            ->json();

        $this->assertArrayHasKey($battle['arena']['key'], PetBattleEngine::ARENAS);
        $this->assertStringContainsString($battle['arena']['name'], $battle['log'][0]['text']);
        // Здоровье на арене больше, чем в карточке: (60 + 6×уровень + 8×редкость) × 1,6
        $options = array_map(fn ($r) => (int) round((60 + 6 * $battle['me']['level'] + 8 * $r) * PetBattleEngine::HP_FACTOR), range(0, 4));
        $this->assertContains($battle['me']['max'], $options);
        $this->assertSame($battle['me']['max'], $battle['me']['hp']);
        // Замысел компьютера наружу не уходит
        $this->assertArrayNotHasKey('plan', $battle);
    }

    public function test_ultimate_needs_full_rage_and_resets_it(): void
    {
        $this->move('ultimate')->assertStatus(422);

        $this->patchState(me: ['rage' => 100], plan: 'attack');

        $response = $this->move('ultimate')->assertOk();

        $this->assertStringContainsString('«Метеор»', $this->logText($response));
        $this->assertSame(1, $response->json('me.stats.ults'));
        $this->assertLessThan(100, $response->json('me.rage'));
    }

    public function test_counter_catches_an_attack_and_returns_damage(): void
    {
        $this->patchState(plan: 'attack');

        $response = $this->move('counter')->assertOk();

        $this->assertStringContainsString('парирует и возвращает', $this->logText($response));
        // Контратака перезаряжается
        $this->assertGreaterThan(0, $response->json('me.ccd'));
        $this->move('counter')->assertStatus(422);
    }

    public function test_counter_against_defence_catches_nothing(): void
    {
        $this->patchState(plan: 'defend');

        $this->assertStringContainsString('ловит только воздух', $this->logText($this->move('counter')->assertOk()));
    }

    public function test_stunned_pet_skips_its_turn(): void
    {
        // Даже недоступный приём не мешает: оглушённый просто пропускает ход
        $this->patchState(me: ['fx' => ['stun' => 1]], plan: 'defend');

        $response = $this->move('ultimate')->assertOk();

        $this->assertStringContainsString('оглушён и пропускает ход', $this->logText($response));
        $this->assertSame([], $response->json('me.fx'));
    }

    public function test_burn_ticks_at_the_end_of_round(): void
    {
        $this->patchState(foe: ['fx' => ['burn' => 2]], plan: 'defend');

        $response = $this->move('defend')->assertOk();

        $this->assertStringContainsString('горит', $this->logText($response));
        $this->assertSame(1, $response->json('foe.fx.burn'));
    }

    public function test_foe_shows_what_it_prepares(): void
    {
        $this->patchState(plan: 'skill');

        $this->actingAs($this->user)->getJson("/api/pets/battles/{$this->battle}")
            ->assertOk()
            ->assertJsonPath('tell.kind', 'skill')
            ->assertJsonPath('tell.text', fn ($text) => str_contains($text, '«Шипы»'));
    }

    public function test_battle_started_before_update_still_plays(): void
    {
        // Старое состояние: ни арены, ни ярости, ни замысла
        $this->patchState();
        $state = json_decode(DB::table('pet_battles')->where('id', $this->battle)->value('state'), true);
        foreach (['me', 'foe'] as $side) {
            unset($state[$side]['rage'], $state[$side]['ccd'], $state[$side]['streak'], $state[$side]['fx'], $state[$side]['stats']);
        }
        unset($state['arena'], $state['plan'], $state['last_moves']);
        DB::table('pet_battles')->where('id', $this->battle)->update(['state' => json_encode($state)]);

        $this->move('attack')->assertOk()->assertJsonPath('arena.key', 'colosseum')->assertJsonPath('round', 1);
    }
}
