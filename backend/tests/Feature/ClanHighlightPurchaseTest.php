<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\ShopItem;
use App\Models\User;
use App\Services\ShopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Подсветка клана как платная услуга.
 *
 * Раньше is_highlighted включался галочкой в настройках клана: бесплатно
 * и навсегда. Теперь это предмет магазина со сроком, который покупает
 * лидер и который действует на весь клан.
 */
class ClanHighlightPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private function highlightItem(array $attributes = []): ShopItem
    {
        return ShopItem::create(array_merge([
            'slug' => 'clan-highlight-30',
            'name' => 'Подсветка клана на 30 дней',
            'type' => ShopItem::TYPE_CLAN_HIGHLIGHT,
            'effect_value' => 'highlight',
            'price' => 1000,
            'is_active' => true,
            'is_repeatable' => true,
            'sort_order' => 300,
            'metadata' => ['days' => 30],
        ], $attributes));
    }

    /**
     * @return array{0: Clan, 1: User}
     */
    private function clanWithLeader(): array
    {
        $leader = User::factory()->create(['apex_coins' => 5000]);

        $clan = Clan::create([
            'name' => 'Клан для подсветки',
            'tag' => 'HGL',
            'leader_id' => $leader->id,
        ]);

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $leader->id,
            'role' => 'leader',
        ]);

        return [$clan, $leader];
    }

    /* ---------------------- Покупка ---------------------- */

    public function test_leader_purchase_highlights_clan(): void
    {
        [$clan, $leader] = $this->clanWithLeader();
        $item = $this->highlightItem();

        $response = $this->actingAs($leader)
            ->postJson("/api/shop/{$item->id}/purchase")
            ->assertCreated();

        $clan->refresh();

        $this->assertTrue($clan->is_highlighted);
        $this->assertNotNull($clan->highlight_until);
        $this->assertTrue($clan->highlight_until->isFuture());

        // Срок — примерно 30 дней
        $this->assertSame(
            30,
            (int) round(now()->diffInDays($clan->highlight_until))
        );

        // Монеты списаны
        $this->assertSame(4000, $leader->fresh()->apex_coins);

        // В ответе есть данные подсветки
        $this->assertSame($clan->id, $response->json('clan_highlight.clan_id'));
        $this->assertTrue($response->json('clan_highlight.is_highlighted'));
    }

    public function test_repeat_purchase_extends_highlight(): void
    {
        [$clan, $leader] = $this->clanWithLeader();
        $item = $this->highlightItem();

        $this->actingAs($leader)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $firstUntil = $clan->fresh()->highlight_until;

        $this->actingAs($leader)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $secondUntil = $clan->fresh()->highlight_until;

        $this->assertTrue(
            $secondUntil->greaterThan($firstUntil),
            'Повторная покупка должна продлевать подсветку'
        );

        $this->assertSame(
            60,
            (int) round(now()->diffInDays($secondUntil))
        );
    }

    public function test_days_come_from_item_metadata(): void
    {
        [$clan, $leader] = $this->clanWithLeader();
        $item = $this->highlightItem([
            'slug' => 'clan-highlight-7',
            'metadata' => ['days' => 7],
        ]);

        $this->actingAs($leader)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $this->assertSame(7, (int) round(now()->diffInDays($clan->fresh()->highlight_until)));
    }

    /* ---------------------- Ограничения ---------------------- */

    public function test_officer_cannot_buy_highlight(): void
    {
        [$clan, $leader] = $this->clanWithLeader();
        $item = $this->highlightItem();

        $officer = User::factory()->create(['apex_coins' => 5000]);

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $officer->id,
            'role' => 'officer',
        ]);

        $this->actingAs($officer)
            ->postJson("/api/shop/{$item->id}/purchase")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Подсветку клана может купить только лидер.');

        $this->assertFalse($clan->fresh()->is_highlighted);
        $this->assertSame(5000, $officer->fresh()->apex_coins, 'Монеты не списаны');
    }

    public function test_player_without_clan_cannot_buy_highlight(): void
    {
        $item = $this->highlightItem();
        $loner = User::factory()->create(['apex_coins' => 5000]);

        $this->actingAs($loner)
            ->postJson("/api/shop/{$item->id}/purchase")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Подсветка доступна только участникам клана.');

        $this->assertSame(5000, $loner->fresh()->apex_coins);
    }

    public function test_not_enough_coins_does_not_highlight(): void
    {
        [$clan, $leader] = $this->clanWithLeader();
        $leader->update(['apex_coins' => 10]);

        $item = $this->highlightItem();

        $this->actingAs($leader)
            ->postJson("/api/shop/{$item->id}/purchase")
            ->assertStatus(422);

        $this->assertFalse($clan->fresh()->is_highlighted);
    }

    /* ---------------------- Истечение срока ---------------------- */

    public function test_expired_highlight_is_removed(): void
    {
        [$clan, $leader] = $this->clanWithLeader();

        $clan->update([
            'is_highlighted' => true,
            'highlight_until' => now()->subDay(),
        ]);

        $removed = ShopService::expireClanHighlights();

        $this->assertSame(1, $removed);

        $clan->refresh();
        $this->assertFalse($clan->is_highlighted);
        $this->assertNull($clan->highlight_until);
    }

    public function test_active_highlight_is_kept(): void
    {
        [$clan] = $this->clanWithLeader();

        $clan->update([
            'is_highlighted' => true,
            'highlight_until' => now()->addDays(5),
        ]);

        $this->assertSame(0, ShopService::expireClanHighlights());
        $this->assertTrue($clan->fresh()->is_highlighted);
    }

    /* ---------------------- Настройки клана ---------------------- */

    public function test_leader_cannot_enable_highlight_via_clan_settings(): void
    {
        [$clan, $leader] = $this->clanWithLeader();

        // Галочка из настроек больше не работает: подсветка платная
        $this->actingAs($leader)
            ->putJson("/api/clans/{$clan->id}", [
                'name' => $clan->name,
                'is_highlighted' => true,
            ])
            ->assertOk();

        $this->assertFalse($clan->fresh()->is_highlighted);
    }
}
