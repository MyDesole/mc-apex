<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Награды «Самых уважаемых» за неделю и ключи сундуков.
 */
class RespectWeeklyRewardsTest extends TestCase
{
    use RefreshDatabase;

    private function respects(User $owner, int $count, $at): void
    {
        for ($i = 0; $i < $count; $i++) {
            DB::table('profile_respects')->insert([
                'owner_id' => $owner->id,
                'user_id' => User::factory()->create()->id,
                'day' => $at->toDateString(),
                'created_at' => $at,
            ]);
        }
    }

    public function test_top_three_get_coins_and_case_keys_once(): void
    {
        $lastWeek = now()->subWeek()->startOfWeek()->addDays(2);

        $first = User::factory()->create(['apex_coins' => 0]);
        $second = User::factory()->create(['apex_coins' => 0]);
        $third = User::factory()->create(['apex_coins' => 0]);
        $fourth = User::factory()->create(['apex_coins' => 0]);

        $this->respects($first, 5, $lastWeek);
        $this->respects($second, 3, $lastWeek);
        $this->respects($third, 2, $lastWeek);
        $this->respects($fourth, 1, $lastWeek);
        // Эта неделя в прошлую не считается
        $this->respects($fourth, 10, now());

        $this->artisan('respects:weekly-rewards')->assertSuccessful();
        // Повторный запуск ничего не выдаёт
        $this->artisan('respects:weekly-rewards')->assertSuccessful();

        $this->assertSame(50000, (int) $first->fresh()->apex_coins);
        $this->assertSame(30000, (int) $second->fresh()->apex_coins);
        $this->assertSame(10000, (int) $third->fresh()->apex_coins);
        $this->assertSame(0, (int) $fourth->fresh()->apex_coins);

        $this->assertSame(1, (int) DB::table('loot_case_keys')->where('user_id', $first->id)->where('case_slug', 'royal')->value('count'));
        $this->assertSame(1, (int) DB::table('loot_case_keys')->where('user_id', $third->id)->where('case_slug', 'seeker')->value('count'));
        $this->assertSame(1, $first->notifications()->count());
    }

    public function test_pet_case_always_gives_a_minted_pet(): void
    {
        foreach (['cat', 'fox', 'dragon'] as $i => $pet) {
            \App\Domains\Shop\Models\ShopItem::create([
                'slug' => "pet-{$pet}", 'name' => "Питомец {$pet}", 'type' => 'profile_pet',
                'effect_value' => $pet, 'price' => 500, 'is_active' => true,
                'rarity' => ['common', 'rare', 'legendary'][$i],
            ]);
        }
        // И обычная косметика — в сундук питомцев она попадать не должна
        \App\Domains\Shop\Models\ShopItem::create(['slug' => 'frame-x', 'name' => 'Рамка', 'type' => 'avatar_frame', 'effect_value' => 'gold', 'price' => 10, 'is_active' => true, 'rarity' => 'common']);

        $price = \App\Domains\Shop\Support\LootCases::find('pets')['price'];
        $user = User::factory()->create(['apex_coins' => 4 * $price + 500]);

        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($user)->postJson('/api/shop/cases/pets/open')
                ->assertOk()
                ->assertJsonPath('prize.item.type', 'profile_pet');
        }

        $pets = \App\Domains\Shop\Models\UserInventory::where('user_id', $user->id)->get();
        $this->assertCount(4, $pets);
        $this->assertTrue($pets->every(fn ($row) => $row->pet_traits && $row->pet_serial));
        $this->assertSame(500, (int) $user->fresh()->apex_coins);
    }

    public function test_trait_luck_makes_rare_traits_more_likely(): void
    {
        // Редкие окрасы — с весом меньше 100 из всех вариантов
        $rare = array_keys(array_filter(\App\Domains\Players\Support\PetTraits::TRAITS['model'], fn ($v) => $v[1] < 100));
        $count = fn (float $luck) => collect(range(1, 6000))
            ->filter(fn () => in_array(\App\Domains\Players\Support\PetTraits::roll(null, $luck)['model'], $rare, true))
            ->count();

        // С удачей редкий окрас выпадает заметно чаще
        $this->assertGreaterThan((int) ($count(1.0) * 1.4), $count(1.8));
    }

    public function test_case_key_opens_paid_case_for_free(): void
    {
        $user = User::factory()->create(['apex_coins' => 0]);
        DB::table('loot_case_keys')->insert(['user_id' => $user->id, 'case_slug' => 'royal', 'count' => 1]);

        $this->actingAs($user)->getJson('/api/cases')
            ->assertOk()
            ->assertJsonPath('cases.2.keys', 1);

        $this->actingAs($user)->postJson('/api/shop/cases/royal/open')
            ->assertOk()
            ->assertJsonPath('used_key', true);

        $this->assertSame(0, (int) DB::table('loot_case_keys')->where('user_id', $user->id)->value('count'));

        // Ключей больше нет, монет тоже — открыть нельзя
        $this->actingAs($user)->postJson('/api/shop/cases/royal/open')->assertStatus(422);
    }
}
