<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Оформление подсветки клана: цвет и эффект.
 *
 * Покупается как косметика и меняет только оформление уже активной
 * подсветки — срок продлевает отдельный предмет.
 */
class ClanHighlightStyleTest extends TestCase
{
    use RefreshDatabase;

    private function styleItem(string $kind, string $value, int $price = 500): ShopItem
    {
        return ShopItem::create([
            'slug' => "clan-highlight-{$kind}-{$value}",
            'name' => ucfirst($value),
            'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE,
            'effect_value' => $value,
            'price' => $price,
            'is_active' => true,
            'is_repeatable' => true,
            'sort_order' => 320,
            'metadata' => ['kind' => $kind],
        ]);
    }

    private function durationItem(): ShopItem
    {
        return ShopItem::create([
            'slug' => 'clan-highlight-30',
            'name' => 'Подсветка клана на 30 дней',
            'type' => ShopItem::TYPE_CLAN_HIGHLIGHT,
            'effect_value' => 'highlight',
            'price' => 1000,
            'is_active' => true,
            'is_repeatable' => true,
            'metadata' => ['days' => 30],
        ]);
    }

    /**
     * @return array{0: Clan, 1: User}
     */
    private function clanWithLeader(int $coins = 10000, bool $highlighted = false): array
    {
        $leader = User::factory()->create(['apex_coins' => $coins]);

        $clan = Clan::create([
            'name' => 'Клан со стилем',
            'tag' => 'STY',
            'leader_id' => $leader->id,
            'is_highlighted' => $highlighted,
            'highlight_until' => $highlighted ? now()->addDays(10) : null,
        ]);

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $leader->id,
            'role' => 'leader',
        ]);

        return [$clan, $leader];
    }

    /* ---------------------- Цвет ---------------------- */

    public function test_color_is_applied_to_clan(): void
    {
        [$clan, $leader] = $this->clanWithLeader(highlighted: true);
        $item = $this->styleItem('color', 'violet');

        $this->actingAs($leader)
            ->postJson("/api/shop/{$item->id}/purchase")
            ->assertCreated();

        $this->assertSame('violet', $clan->fresh()->highlight_color);
        $this->assertSame(9500, $leader->fresh()->apex_coins);
    }

    public function test_color_can_be_changed(): void
    {
        [$clan, $leader] = $this->clanWithLeader(highlighted: true);

        $crimson = $this->styleItem('color', 'crimson');
        $cyan = $this->styleItem('color', 'cyan');

        $this->actingAs($leader)->postJson("/api/shop/{$crimson->id}/purchase")->assertCreated();
        $this->assertSame('crimson', $clan->fresh()->highlight_color);

        $this->actingAs($leader)->postJson("/api/shop/{$cyan->id}/purchase")->assertCreated();
        $this->assertSame('cyan', $clan->fresh()->highlight_color);
    }

    /* ---------------------- Эффект ---------------------- */

    public function test_effect_is_applied_to_clan(): void
    {
        [$clan, $leader] = $this->clanWithLeader(highlighted: true);
        $item = $this->styleItem('effect', 'pulse', 1200);

        $this->actingAs($leader)
            ->postJson("/api/shop/{$item->id}/purchase")
            ->assertCreated();

        $this->assertSame('pulse', $clan->fresh()->highlight_effect);
    }

    public function test_effect_does_not_touch_color(): void
    {
        [$clan, $leader] = $this->clanWithLeader(highlighted: true);

        $color = $this->styleItem('color', 'emerald');
        $effect = $this->styleItem('effect', 'glow');

        $this->actingAs($leader)->postJson("/api/shop/{$color->id}/purchase")->assertCreated();
        $this->actingAs($leader)->postJson("/api/shop/{$effect->id}/purchase")->assertCreated();

        $fresh = $clan->fresh();
        $this->assertSame('emerald', $fresh->highlight_color);
        $this->assertSame('glow', $fresh->highlight_effect);
    }

    /* ---------------------- Ограничения ---------------------- */

    public function test_style_requires_active_highlight(): void
    {
        [$clan, $leader] = $this->clanWithLeader(highlighted: false);
        $item = $this->styleItem('color', 'violet');

        $this->actingAs($leader)
            ->postJson("/api/shop/{$item->id}/purchase")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сначала купите подсветку клана, потом её оформление.');

        $this->assertNull($clan->fresh()->highlight_color);
        $this->assertSame(10000, $leader->fresh()->apex_coins, 'Монеты не списаны');
    }

    public function test_expired_highlight_does_not_count_as_active(): void
    {
        [$clan, $leader] = $this->clanWithLeader(highlighted: true);

        // Срок истёк, но флаг ещё не сброшен
        $clan->update(['highlight_until' => now()->subDay()]);

        $item = $this->styleItem('color', 'rose');

        $this->actingAs($leader)
            ->postJson("/api/shop/{$item->id}/purchase")
            ->assertStatus(422);

        $this->assertNull($clan->fresh()->highlight_color);
    }

    public function test_officer_cannot_buy_style(): void
    {
        [$clan, $leader] = $this->clanWithLeader(highlighted: true);

        $officer = User::factory()->create(['apex_coins' => 5000]);

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $officer->id,
            'role' => 'officer',
        ]);

        $item = $this->styleItem('color', 'cyan');

        $this->actingAs($officer)
            ->postJson("/api/shop/{$item->id}/purchase")
            ->assertStatus(422);

        $this->assertNull($clan->fresh()->highlight_color);
    }

    public function test_unknown_color_is_rejected(): void
    {
        [$clan, $leader] = $this->clanWithLeader(highlighted: true);
        $item = $this->styleItem('color', 'neon-pink');

        $this->actingAs($leader)
            ->postJson("/api/shop/{$item->id}/purchase")
            ->assertStatus(422);

        $this->assertNull($clan->fresh()->highlight_color);
    }

    public function test_style_does_not_change_highlight_expiry(): void
    {
        [$clan, $leader] = $this->clanWithLeader(highlighted: true);

        $before = $clan->highlight_until;

        $item = $this->styleItem('color', 'violet');

        $this->actingAs($leader)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();

        $this->assertTrue(
            $before->equalTo($clan->fresh()->highlight_until),
            'Оформление не должно менять срок подсветки'
        );
    }

    /* ---------------------- Состав каталога ---------------------- */

    public function test_seeded_catalog_has_colors_and_effects(): void
    {
        $this->seed(\Database\Seeders\ShopItemSeeder::class);

        $styles = ShopItem::where('type', ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE)->get();

        $colors = $styles->filter(fn ($i) => ($i->metadata['kind'] ?? null) === 'color');
        $effects = $styles->filter(fn ($i) => ($i->metadata['kind'] ?? null) === 'effect');

        $this->assertGreaterThanOrEqual(5, $colors->count(), 'Должно быть несколько цветов');
        $this->assertGreaterThanOrEqual(4, $effects->count(), 'Должно быть несколько эффектов');

        // Каждый товар знает, что меняет
        foreach ($styles as $style) {
            $this->assertContains($style->metadata['kind'] ?? null, ['color', 'effect'], $style->slug);
        }
    }

    public function test_style_is_visible_in_shop_api(): void
    {
        $this->seed(\Database\Seeders\ShopItemSeeder::class);

        $user = User::factory()->create();

        $items = collect(
            $this->actingAs($user)->getJson('/api/shop')->assertOk()->json('items')
        );

        $styles = $items->where('type', 'clan_highlight_style');

        $this->assertGreaterThan(0, $styles->count());

        foreach ($styles as $style) {
            $this->assertArrayHasKey('effect_value', $style);
            $this->assertNotEmpty($style['effect_value']);
        }
    }
}
