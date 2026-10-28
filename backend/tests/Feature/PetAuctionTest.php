<?php

namespace Tests\Feature;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Аукцион питомцев: ставки с таймером.
 */
class PetAuctionTest extends TestCase
{
    use RefreshDatabase;

    private function pet(User $owner): UserInventory
    {
        $item = ShopItem::firstOrCreate(['slug' => 'pet-fox'], [
            'name' => 'Лисёнок', 'type' => 'profile_pet', 'effect_value' => 'fox',
            'price' => 1000, 'is_active' => true, 'rarity' => 'rare',
        ]);

        return UserInventory::create(['user_id' => $owner->id, 'shop_item_id' => $item->id, 'quantity' => 1]);
    }

    public function test_full_auction_flow(): void
    {
        $seller = User::factory()->create(['apex_coins' => 0]);
        $alice = User::factory()->create(['apex_coins' => 1000]);
        $bob = User::factory()->create(['apex_coins' => 1000]);
        $row = $this->pet($seller);

        // Чужого питомца выставить нельзя
        $this->actingAs($alice)->postJson('/api/shop/auctions', ['inventory_id' => $row->id, 'start_price' => 100, 'hours' => 24])->assertStatus(422);

        $id = $this->actingAs($seller)->postJson('/api/shop/auctions', ['inventory_id' => $row->id, 'start_price' => 100, 'hours' => 24])
            ->assertCreated()
            ->assertJsonPath('min_bid', 100)
            ->json('id');

        // На аукционе — не продать, не надеть, на свой лот не поставить
        $this->actingAs($seller)->postJson("/api/shop/inventory/{$row->id}/sell")->assertStatus(422);
        $this->actingAs($seller)->postJson("/api/shop/pets/{$row->id}/equip")->assertStatus(422);
        $this->actingAs($seller)->postJson("/api/shop/auctions/{$id}/bid", ['amount' => 200])->assertStatus(422);

        $this->actingAs($alice)->postJson("/api/shop/auctions/{$id}/bid", ['amount' => 99])->assertStatus(422);
        $this->actingAs($alice)->postJson("/api/shop/auctions/{$id}/bid", ['amount' => 100])
            ->assertOk()
            ->assertJsonPath('leading', true)
            ->assertJsonPath('min_bid', 110);
        $this->assertSame(900, (int) $alice->fresh()->apex_coins);

        // Перебили — монеты вернулись
        $this->actingAs($bob)->postJson("/api/shop/auctions/{$id}/bid", ['amount' => 150])->assertOk();
        $this->assertSame(1000, (int) $alice->fresh()->apex_coins);
        $this->assertSame(850, (int) $bob->fresh()->apex_coins);

        // Со ставками снять можно только с неустойкой, а у продавца нет монет
        $this->actingAs($seller)->postJson("/api/shop/auctions/{$id}/cancel")->assertStatus(422);

        // Время вышло — питомец победителю, монеты продавцу
        $this->travel(25)->hours();
        $this->getJson('/api/auctions')->assertOk()->assertJsonCount(0, 'lots');

        $this->assertSame($bob->id, $row->fresh()->user_id);
        $this->assertSame(150, (int) $row->fresh()->paid_price);
        $this->assertSame(150, (int) $seller->fresh()->apex_coins);
        $this->assertSame(1, $bob->notifications()->count());
    }

    public function test_late_bid_extends_auction_and_unsold_lot_stays(): void
    {
        $seller = User::factory()->create();
        $bidder = User::factory()->create(['apex_coins' => 500]);
        $row = $this->pet($seller);

        $id = $this->actingAs($seller)->postJson('/api/shop/auctions', ['inventory_id' => $row->id, 'start_price' => 50, 'hours' => 12])->json('id');

        $this->travel(12 * 60 - 1)->minutes();
        $endsAt = $this->actingAs($bidder)->postJson("/api/shop/auctions/{$id}/bid", ['amount' => 50])->json('ends_at');
        // Ставка за минуту до конца — торги продлены на 2 минуты
        $this->assertTrue(now()->addSeconds(100)->lt(\Illuminate\Support\Carbon::parse($endsAt)));

        // Лот без ставок можно снять — питомец остаётся у продавца
        $other = $this->pet($seller);
        $lot = $this->actingAs($seller)->postJson('/api/shop/auctions', ['inventory_id' => $other->id, 'start_price' => 50, 'hours' => 12])->json('id');
        $this->actingAs($seller)->postJson("/api/shop/auctions/{$lot}/cancel")->assertOk();
        $this->assertSame($seller->id, $other->fresh()->user_id);
    }

    public function test_withdraw_with_bids_costs_penalty_and_refunds_leader(): void
    {
        $seller = User::factory()->create(['apex_coins' => 100]);
        $bidder = User::factory()->create(['apex_coins' => 1000]);
        $row = $this->pet($seller);

        $id = $this->actingAs($seller)->postJson('/api/shop/auctions', ['inventory_id' => $row->id, 'start_price' => 200, 'hours' => 24])->json('id');
        $this->actingAs($bidder)->postJson("/api/shop/auctions/{$id}/bid", ['amount' => 300])->assertJsonPath('cancel_penalty', 30);

        $this->actingAs($seller)->postJson("/api/shop/auctions/{$id}/cancel")->assertOk()->assertJsonPath('penalty', 30);

        $this->assertSame(70, (int) $seller->fresh()->apex_coins);
        $this->assertSame(1000, (int) $bidder->fresh()->apex_coins);
        $this->assertSame($seller->id, $row->fresh()->user_id);
        $this->assertSame(1, $bidder->notifications()->count());
    }

    public function test_seller_can_have_at_most_three_lots(): void
    {
        $seller = User::factory()->create();

        foreach (range(1, 3) as $i) {
            $this->actingAs($seller)->postJson('/api/shop/auctions', ['inventory_id' => $this->pet($seller)->id, 'start_price' => 50, 'hours' => 12])->assertCreated();
        }

        $this->actingAs($seller)->postJson('/api/shop/auctions', ['inventory_id' => $this->pet($seller)->id, 'start_price' => 50, 'hours' => 12])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'не больше 3'));
    }

    public function test_pet_keeps_level_after_sale(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create(['apex_coins' => 500]);
        $row = $this->pet($seller);

        // 30 опыта — 4 уровень (0, 5, 15, 30)
        foreach (range(1, 15) as $day) {
            \Illuminate\Support\Facades\DB::table('pet_interactions')->insert([
                'owner_id' => $seller->id, 'inventory_id' => $row->id, 'actor_id' => $seller->id,
                'action' => 'feed', 'day' => now()->subDays($day)->toDateString(), 'created_at' => now()->subDays($day),
            ]);
        }

        $id = $this->actingAs($seller)->postJson('/api/shop/auctions', ['inventory_id' => $row->id, 'start_price' => 50, 'hours' => 12])
            ->assertJsonPath('level', 4)
            ->json('id');
        $this->actingAs($buyer)->postJson("/api/shop/auctions/{$id}/bid", ['amount' => 50]);
        $this->travel(13)->hours();
        $this->getJson('/api/auctions');

        $this->assertSame($buyer->id, $row->fresh()->user_id);
        $this->assertSame([$row->id => 4], \App\Domains\Players\Services\PetService::levels([$row->id]));
    }

    public function test_lot_chat(): void
    {
        $seller = User::factory()->create();
        $player = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderator']);
        $id = $this->actingAs($seller)->postJson('/api/shop/auctions', ['inventory_id' => $this->pet($seller)->id, 'start_price' => 50, 'hours' => 12])->json('id');

        $this->postJson("/api/shop/auctions/{$id}/messages", ['body' => 'привет'])->assertCreated();
        $msg = $this->actingAs($player)->postJson("/api/shop/auctions/{$id}/messages", ['body' => '<b>Сколько</b> уровень?'])
            ->assertCreated()
            ->assertJsonPath('body', 'Сколько уровень?')
            ->json('id');

        // Антиспам: не чаще раза в 3 секунды
        $this->actingAs($player)->postJson("/api/shop/auctions/{$id}/messages", ['body' => 'ещё'])->assertStatus(422);

        // Гостям читать можно
        auth()->forgetGuards();
        $this->getJson("/api/auctions/{$id}/messages")->assertOk()->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.0.is_seller', true);

        // Чужое не удалить, модератор — может
        $this->actingAs($seller)->deleteJson("/api/shop/auctions/{$id}/messages/{$msg}")->assertStatus(422);
        $this->actingAs($moderator)->deleteJson("/api/shop/auctions/{$id}/messages/{$msg}")->assertOk();
        $this->getJson("/api/auctions/{$id}/messages")->assertJsonCount(1, 'messages');
    }
}
