<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanInventory;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClanTreasuryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Clan, 1: User, 2: User} */
    private function clan(): array
    {
        $leader = User::factory()->create(['apex_coins' => 100]);
        $member = User::factory()->create(['apex_coins' => 1000]);

        $clan = Clan::create([
            'name' => 'Казна', 'tag' => 'BANK', 'leader_id' => $leader->id,
            'is_highlighted' => true, 'highlight_until' => now()->addDays(5),
        ]);

        ClanMember::create(['clan_id' => $clan->id, 'user_id' => $leader->id, 'role' => 'leader']);
        ClanMember::create(['clan_id' => $clan->id, 'user_id' => $member->id, 'role' => 'member']);

        return [$clan, $leader, $member];
    }

    private function style(): ShopItem
    {
        return ShopItem::create([
            'slug' => 'clan-color-violet', 'name' => 'Фиолетовая подсветка', 'type' => 'clan_highlight_style',
            'price' => 500, 'effect_value' => 'violet', 'metadata' => ['kind' => 'color'], 'is_active' => true,
        ]);
    }

    public function test_member_donates_and_leader_pays_from_treasury_into_clan_inventory(): void
    {
        [$clan, $leader, $member] = $this->clan();
        $item = $this->style();

        $this->actingAs($member)->postJson('/api/my-clan/treasury/donate', ['amount' => 600])
            ->assertOk()
            ->assertJsonPath('balance', 600)
            ->assertJsonPath('donors.0.total', 600);

        $this->assertSame(600, (int) $clan->fresh()->treasury);

        // У лидера всего 100 монет — платит из казны
        $this->actingAs($leader)->postJson("/api/shop/{$item->id}/purchase", ['pay_from' => 'treasury'])
            ->assertCreated()
            ->assertJsonPath('clan_highlight.treasury', 100);

        $this->assertSame(100, (int) $leader->fresh()->apex_coins);
        $this->assertSame('violet', $clan->fresh()->highlight_color);
        $this->assertTrue(ClanInventory::where('clan_id', $clan->id)->where('shop_item_id', $item->id)->whereNotNull('equipped_at')->exists());
        $this->assertDatabaseMissing('user_inventory', ['user_id' => $leader->id, 'shop_item_id' => $item->id]);

        $this->actingAs($member)->getJson('/api/my-clan/treasury')
            ->assertOk()
            ->assertJsonPath('history.0.amount', -500);
    }

    public function test_member_cannot_buy_clan_item_and_gets_clear_reason(): void
    {
        [, , $member] = $this->clan();
        $item = $this->style();

        $this->actingAs($member)->postJson("/api/shop/{$item->id}/purchase")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Недостаточно прав: клановые предметы покупает только лидер клана. Попросите лидера или скиньтесь в казну клана.');
    }

    public function test_treasury_cannot_go_negative_and_donation_needs_coins(): void
    {
        [$clan, $leader, $member] = $this->clan();
        $item = $this->style();

        $this->actingAs($leader)->postJson("/api/shop/{$item->id}/purchase", ['pay_from' => 'treasury'])
            ->assertStatus(422);

        $this->actingAs($member)->postJson('/api/my-clan/treasury/donate', ['amount' => 5000])
            ->assertStatus(422);

        $this->assertSame(0, (int) $clan->fresh()->treasury);
        $this->assertSame(1000, (int) $member->fresh()->apex_coins);
    }
}
