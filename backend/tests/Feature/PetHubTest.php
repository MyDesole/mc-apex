<?php

namespace Tests\Feature;

use App\Domains\Players\Services\PetArenaService;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Жизнь питомца: экспедиции, арена, трюки, питомцедекс, топ.
 */
class PetHubTest extends TestCase
{
    use RefreshDatabase;

    private function pet(User $owner, string $species = 'fox', int $xp = 0): UserInventory
    {
        $item = ShopItem::firstOrCreate(['slug' => "pet-{$species}"], [
            'name' => "Питомец {$species}", 'type' => 'profile_pet', 'effect_value' => $species,
            'price' => 1000, 'is_active' => true, 'rarity' => 'rare',
        ]);

        $row = UserInventory::create(['user_id' => $owner->id, 'shop_item_id' => $item->id, 'quantity' => 1]);
        $row->forceFill(['pet_xp' => $xp])->save();

        return $row->fresh();
    }

    public function test_expedition_pays_after_return_and_locks_pet(): void
    {
        $user = User::factory()->create(['apex_coins' => 0]);
        $row = $this->pet($user);

        // Пещеры — с 8 уровня
        $this->actingAs($user)->postJson("/api/pets/{$row->id}/expedition", ['zone' => 'caves'])->assertStatus(422);

        $trip = $this->actingAs($user)->postJson("/api/pets/{$row->id}/expedition", ['zone' => 'meadow'])
            ->assertCreated()
            ->assertJsonPath('reward', null)
            ->json('id');

        // Занятого питомца не выставить и не отправить ещё раз
        $this->actingAs($user)->postJson('/api/shop/auctions', ['inventory_id' => $row->id, 'start_price' => 50, 'hours' => 12])->assertStatus(422);
        $this->actingAs($user)->postJson("/api/pets/{$row->id}/expedition", ['zone' => 'meadow'])->assertStatus(422);
        $this->actingAs($user)->postJson("/api/pets/expeditions/{$trip}/claim")->assertStatus(422);

        $this->travel(61)->minutes();

        $coins = $this->actingAs($user)->postJson("/api/pets/expeditions/{$trip}/claim")
            ->assertOk()
            ->assertJsonPath('claimed', true)
            ->json('reward.coins');

        $this->assertGreaterThanOrEqual(120, $coins);
        $this->assertSame($coins, (int) $user->fresh()->apex_coins);
        $this->assertSame(3, (int) $row->fresh()->pet_xp);
        // Второй раз не забрать
        $this->actingAs($user)->postJson("/api/pets/expeditions/{$trip}/claim")->assertStatus(422);
    }

    public function test_arena_battle_until_the_end_changes_rating(): void
    {
        $user = User::factory()->create(['apex_coins' => 0]);
        $rival = User::factory()->create();
        // 30 уровень против первого — бой должен закончиться победой
        $mine = $this->pet($user, 'dragon', 2200);
        $foe = $this->pet($rival, 'frog');

        $this->actingAs($user)->getJson("/api/pets/{$mine->id}/opponents")
            ->assertOk()
            ->assertJsonPath('opponents.0.inventory_id', $foe->id);

        $battle = $this->actingAs($user)->postJson("/api/pets/{$mine->id}/battle", ['opponent' => $foe->id])
            ->assertCreated()
            ->assertJsonPath('me.element', 'fire')
            ->assertJsonPath('foe.element', 'nature')
            ->json('id');

        // Второй бой, пока идёт первый, — нельзя
        $this->actingAs($user)->postJson("/api/pets/{$mine->id}/battle", ['opponent' => $foe->id])->assertStatus(422);

        $status = 'active';
        for ($i = 0; $i < 40 && $status === 'active'; $i++) {
            $status = $this->actingAs($user)->postJson("/api/pets/battles/{$battle}/move", ['move' => 'attack'])->json('status');
        }

        $this->assertSame('won', $status);
        $this->assertGreaterThan(1000, $mine->fresh()->pet_rating);
        $this->assertLessThan(1000, $foe->fresh()->pet_rating);
        $this->assertSame(1, (int) $mine->fresh()->pet_wins);
        $this->assertGreaterThan(0, (int) $user->fresh()->apex_coins);
    }

    public function test_daily_fight_limit_and_forfeit(): void
    {
        $user = User::factory()->create();
        $mine = $this->pet($user);
        $foe = $this->pet(User::factory()->create(), 'cat');

        foreach (range(1, PetArenaService::DAILY_FIGHTS) as $i) {
            $id = $this->actingAs($user)->postJson("/api/pets/{$mine->id}/battle", ['opponent' => $foe->id])->assertCreated()->json('id');
            $this->actingAs($user)->postJson("/api/pets/battles/{$id}/forfeit")->assertOk()->assertJsonPath('status', 'lost');
        }

        $this->actingAs($user)->postJson("/api/pets/{$mine->id}/battle", ['opponent' => $foe->id])->assertStatus(422);
        $this->assertSame(PetArenaService::DAILY_FIGHTS, (int) $mine->fresh()->pet_losses);
    }

    public function test_tricks_unlock_by_level(): void
    {
        $user = User::factory()->create();
        $row = $this->pet($user);

        $this->actingAs($user)->postJson("/api/pets/{$row->id}/trick", ['trick' => 'spin'])->assertStatus(422);

        $row->forceFill(['pet_xp' => 15])->save(); // 3 уровень
        $this->actingAs($user)->postJson("/api/pets/{$row->id}/trick", ['trick' => 'spin'])->assertOk();
        $this->actingAs($user)->postJson("/api/pets/{$row->id}/trick", ['trick' => 'firework'])->assertStatus(422);

        $this->assertSame('spin', $row->fresh()->pet_trick);
    }

    public function test_dex_records_pets_and_pays_milestone_once(): void
    {
        $user = User::factory()->create(['apex_coins' => 0]);
        $this->pet($user, 'fox');
        $this->pet($user, 'cat');
        $gifted = $this->pet(User::factory()->create(), 'owl');

        // Подаренный (сменивший владельца) питомец тоже попадает в коллекцию
        $gifted->forceFill(['user_id' => $user->id])->save();

        $this->actingAs($user)->getJson('/api/pets/dex')
            ->assertOk()
            ->assertJsonPath('sections.species.owned', 3);

        $this->actingAs($user)->postJson('/api/pets/dex/species_6/claim')->assertStatus(422);
        $this->actingAs($user)->postJson('/api/pets/dex/species_3/claim')->assertOk();
        $this->actingAs($user)->postJson('/api/pets/dex/species_3/claim')->assertStatus(422);

        $this->assertSame(1000, (int) $user->fresh()->apex_coins);
    }

    public function test_owner_cares_for_pet_that_is_not_walking(): void
    {
        $user = User::factory()->create();
        $row = $this->pet($user, 'cat');

        $this->actingAs($user)->postJson("/api/players/{$user->id}/pet/feed", ['pet' => $row->id])
            ->assertOk()
            ->assertJsonPath('xp', 2);

        $this->actingAs($user)->getJson('/api/pets')
            ->assertJsonPath('pets.0.care.0', 'feed')
            ->assertJsonPath('pets.0.hungry', false);

        // Второй раз за день — нельзя
        $this->actingAs($user)->postJson("/api/players/{$user->id}/pet/feed", ['pet' => $row->id])->assertStatus(422);
    }

    public function test_hub_and_public_top(): void
    {
        $user = User::factory()->create();
        $row = $this->pet($user, 'fox', 50);

        $this->actingAs($user)->getJson('/api/pets')
            ->assertOk()
            ->assertJsonPath('pets.0.inventory_id', $row->id)
            ->assertJsonPath('pets.0.level', 5)
            ->assertJsonPath('pets.0.perks.0.unlocked', true);

        auth()->forgetGuards();
        $this->getJson('/api/pets/top?by=level')->assertOk()->assertJsonPath('pets.0.inventory_id', $row->id);
        $this->getJson('/api/pets/top?by=rare')->assertOk();
        $this->getJson('/api/pets/top?by=rating')->assertOk();
    }

    public function test_rarity_shares_follow_targets(): void
    {
        $thresholds = \App\Domains\Players\Support\PetTraits::rarityThresholds();
        $this->assertSame(['mythic', 'legendary', 'epic', 'rare'], array_keys($thresholds));
        $this->assertTrue($thresholds['mythic'] < $thresholds['legendary'] && $thresholds['epic'] < $thresholds['rare']);

        // Самое частое сочетание — обычное, самое редкое — мифическое
        $present = fn ($traits) => \App\Domains\Players\Support\PetTraits::present($traits)['rarity'];
        $this->assertSame('common', $present(['model' => 'classic', 'backdrop' => 'none', 'accessory' => 'none']));
        $this->assertSame('mythic', $present(['model' => 'aurora', 'backdrop' => 'rift', 'accessory' => 'wizard']));
    }

    public function test_season_pays_top_and_softens_rating(): void
    {
        $user = User::factory()->create(['apex_coins' => 0]);
        $row = $this->pet($user);
        $row->forceFill(['pet_rating' => 1400, 'pet_wins' => 10])->save();

        app(PetArenaService::class)->closeSeason();

        $this->assertSame(20000, (int) $user->fresh()->apex_coins);
        $this->assertSame(1200, (int) $row->fresh()->pet_rating);
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $user->id)->count());
    }
}
