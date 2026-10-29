<?php

namespace Tests\Feature;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Путеводитель: 10 000 по первым шагам, порядок шагов, проверки по базе
 * и недельный замок на подарки и аукцион.
 */
class GuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_guide_pays_ten_thousand_for_the_first_steps_once(): void
    {
        $user = User::factory()->create(['apex_coins' => 0]);

        $this->actingAs($user)->getJson('/api/guide')
            ->assertOk()
            ->assertJsonPath('started', false)
            ->assertJsonPath('total', 10000);

        $this->actingAs($user)->postJson('/api/guide/start')
            ->assertOk()
            ->assertJsonPath('current', 'rating')
            ->assertJsonPath('balance', 6000);

        // Второй старт ничего не даёт
        $this->actingAs($user)->postJson('/api/guide/start')->assertOk();
        $this->assertSame(6000, (int) $user->fresh()->apex_coins);

        // Шаги по порядку: перепрыгнуть нельзя
        $this->actingAs($user)->postJson('/api/guide/steps/test/complete')->assertStatus(422);

        $this->actingAs($user)->postJson('/api/guide/steps/rating/complete')->assertOk()->assertJsonPath('balance', 8000);
        $this->actingAs($user)->postJson('/api/guide/steps/rating/complete')->assertStatus(422);
        $this->actingAs($user)->postJson('/api/guide/steps/test/complete')
            ->assertOk()
            ->assertJsonPath('balance', 10000)
            ->assertJsonPath('earned', 10000)
            ->assertJsonPath('current', 'profile');
    }

    public function test_steps_checked_by_database_need_the_real_action(): void
    {
        $user = User::factory()->create(['apex_coins' => 0]);
        $this->actingAs($user)->postJson('/api/guide/start');

        foreach (['rating', 'test', 'profile', 'clans', 'tournaments', 'shop'] as $step) {
            $this->actingAs($user)->postJson("/api/guide/steps/{$step}/complete")->assertOk();
        }

        // Питомца ещё нет
        $this->actingAs($user)->postJson('/api/guide/steps/pet/complete')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сначала открой сундук питомцев.');

        $item = ShopItem::create(['slug' => 'pet-fox', 'name' => 'Лисёнок', 'type' => 'profile_pet', 'effect_value' => 'fox', 'price' => 1000, 'is_active' => true, 'rarity' => 'rare']);
        $row = UserInventory::create(['user_id' => $user->id, 'shop_item_id' => $item->id, 'quantity' => 1]);
        $row->forceFill(['pet_traits' => ['color' => 'base']])->save();

        // Как только питомец есть, шаг засчитывается сам
        $this->actingAs($user)->getJson('/api/guide')
            ->assertOk()
            ->assertJsonPath('current', 'pets')
            ->assertJsonPath('steps.7.id', 'pet')
            ->assertJsonPath('steps.7.status', 'done');
    }

    public function test_skip_moves_on_without_reward_and_the_guide_finishes(): void
    {
        $user = User::factory()->create(['apex_coins' => 0]);
        $this->actingAs($user)->postJson('/api/guide/start');

        $this->actingAs($user)->postJson('/api/guide/steps/rating/skip')->assertOk()->assertJsonPath('current', 'test');
        $this->assertSame(6000, (int) $user->fresh()->apex_coins);

        foreach (['test', 'profile', 'clans', 'tournaments', 'shop', 'pet', 'pets', 'expedition', 'arena', 'auction', 'tops', 'miner'] as $step) {
            $this->actingAs($user)->postJson("/api/guide/steps/{$step}/skip")->assertOk();
        }

        $this->actingAs($user)->getJson('/api/guide')
            ->assertJsonPath('finished', true)
            ->assertJsonPath('current', null);

        $this->actingAs($user)->postJson('/api/guide/hide', ['hidden' => true])->assertOk()->assertJsonPath('hidden', true);
    }

    public function test_guide_coins_cannot_be_gifted_or_traded_for_a_week(): void
    {
        $user = User::factory()->create(['apex_coins' => 0]);
        $friend = User::factory()->create();
        DB::table('friendships')->insert([
            ['user_id' => $user->id, 'friend_id' => $friend->id, 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($user)->postJson('/api/guide/start');

        $this->expectGiftBlocked($user, $friend);

        $this->actingAs($user)->getJson('/api/guide')->assertJsonPath('trade_lock_until', fn ($v) => $v !== null);

        $this->travel(8)->days();
        $this->actingAs($user)->getJson('/api/guide')->assertJsonPath('trade_lock_until', null);
    }

    private function expectGiftBlocked(User $from, User $to): void
    {
        try {
            \App\Domains\Wallet\Services\CoinService::gift($from, $to, 100);
            $this->fail('Подарок должен быть запрещён.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Дарить монеты', $e->getMessage());
        }
    }

    public function test_mascot_is_the_rarest_pet(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/guide')->assertOk()->assertJsonPath('mascot', null);

        $item = ShopItem::create(['slug' => 'pet-dragon', 'name' => 'Дракон', 'type' => 'profile_pet', 'effect_value' => 'dragon', 'price' => 1000, 'is_active' => true, 'rarity' => 'rare']);
        $row = UserInventory::create(['user_id' => User::factory()->create(['username' => 'owner_x'])->id, 'shop_item_id' => $item->id, 'quantity' => 1]);
        $row->forceFill(['pet_traits' => ['color' => 'base'], 'pet_name' => 'Искра'])->save();
        \App\Domains\Players\Services\PetHubService::forgetTop();

        $this->actingAs($user)->getJson('/api/guide')
            ->assertOk()
            ->assertJsonPath('mascot.species', 'dragon')
            ->assertJsonPath('mascot.name', 'Искра')
            ->assertJsonPath('mascot.owner', 'owner_x');
    }
}
