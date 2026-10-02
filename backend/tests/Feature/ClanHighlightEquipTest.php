<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Users\Models\User;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Clan\Support\ClanHighlight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Применение купленного оформления подсветки клана.
 *
 * Покупка кладёт предмет в инвентарь, а применить его нужно отдельно —
 * как косметику профиля. Лидер выбирает цвет и эффект из купленного
 * и может сменить их в любой момент.
 */
class ClanHighlightEquipTest extends TestCase
{
    use RefreshDatabase;

    private function styleItem(string $kind, string $value): ShopItem
    {
        return ShopItem::create([
            'slug' => "clan-highlight-{$kind}-{$value}",
            'name' => ucfirst($value),
            'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE,
            'effect_value' => $value,
            'price' => 500,
            'is_active' => true,
            'is_repeatable' => true,
            'metadata' => ['kind' => $kind],
        ]);
    }

    private function own(User $user, ShopItem $item): void
    {
        UserInventory::create([
            'user_id' => $user->id,
            'shop_item_id' => $item->id,
            'quantity' => 1,
            'acquired_at' => now(),
        ]);
    }

    /**
     * @return array{0: Clan, 1: User}
     */
    private function clanWithLeader(bool $highlighted = true): array
    {
        $leader = User::factory()->create(['apex_coins' => 10000]);

        $clan = Clan::create([
            'name' => 'Клан стиля',
            'tag' => 'STL',
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

    /* ---------------------- Применение ---------------------- */

    public function test_owned_color_can_be_equipped(): void
    {
        [$clan, $leader] = $this->clanWithLeader();
        $color = $this->styleItem('color', 'violet');

        $this->own($leader, $color);

        $this->actingAs($leader)
            ->postJson("/api/shop/{$color->id}/equip")
            ->assertOk();

        $this->assertSame('violet', $clan->fresh()->highlight_color);
    }

    public function test_owned_effect_can_be_equipped(): void
    {
        [$clan, $leader] = $this->clanWithLeader();
        $effect = $this->styleItem('effect', 'aurora');

        $this->own($leader, $effect);

        $this->actingAs($leader)
            ->postJson("/api/shop/{$effect->id}/equip")
            ->assertOk();

        $this->assertSame('aurora', $clan->fresh()->highlight_effect);
    }

    public function test_equipping_another_color_replaces_previous(): void
    {
        [$clan, $leader] = $this->clanWithLeader();

        $violet = $this->styleItem('color', 'violet');
        $rose = $this->styleItem('color', 'rose');

        $this->own($leader, $violet);
        $this->own($leader, $rose);

        $this->actingAs($leader)->postJson("/api/shop/{$violet->id}/equip")->assertOk();
        $this->assertSame('violet', $clan->fresh()->highlight_color);

        $this->actingAs($leader)->postJson("/api/shop/{$rose->id}/equip")->assertOk();
        $this->assertSame('rose', $clan->fresh()->highlight_color);
    }

    public function test_effect_and_color_are_independent(): void
    {
        [$clan, $leader] = $this->clanWithLeader();

        $color = $this->styleItem('color', 'emerald');
        $effect = $this->styleItem('effect', 'fire');

        $this->own($leader, $color);
        $this->own($leader, $effect);

        $this->actingAs($leader)->postJson("/api/shop/{$color->id}/equip")->assertOk();
        $this->actingAs($leader)->postJson("/api/shop/{$effect->id}/equip")->assertOk();

        $fresh = $clan->fresh();
        $this->assertSame('emerald', $fresh->highlight_color);
        $this->assertSame('fire', $fresh->highlight_effect);
    }

    /* ---------------------- Ограничения ---------------------- */

    public function test_cannot_equip_not_owned_item(): void
    {
        [$clan, $leader] = $this->clanWithLeader();
        $color = $this->styleItem('color', 'cyan');

        // Не покупали
        $this->actingAs($leader)
            ->postJson("/api/shop/{$color->id}/equip")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сначала нужно купить этот предмет.');

        $this->assertNull($clan->fresh()->highlight_color);
    }

    public function test_cannot_equip_without_active_highlight(): void
    {
        [$clan, $leader] = $this->clanWithLeader(highlighted: false);
        $color = $this->styleItem('color', 'violet');

        $this->own($leader, $color);

        $this->actingAs($leader)
            ->postJson("/api/shop/{$color->id}/equip")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сначала купите подсветку клана, потом её оформление.');
    }

    public function test_officer_cannot_equip_style(): void
    {
        [$clan] = $this->clanWithLeader();

        $officer = User::factory()->create();
        $color = $this->styleItem('color', 'rose');

        $this->own($officer, $color);

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $officer->id,
            'role' => 'officer',
        ]);

        $this->actingAs($officer)
            ->postJson("/api/shop/{$color->id}/equip")
            ->assertStatus(422);

        $this->assertNull($clan->fresh()->highlight_color);
    }

    /* ---------------------- Снятие ---------------------- */

    public function test_unequip_returns_defaults(): void
    {
        [$clan, $leader] = $this->clanWithLeader();

        $color = $this->styleItem('color', 'violet');
        $this->own($leader, $color);

        $this->actingAs($leader)->postJson("/api/shop/{$color->id}/equip")->assertOk();
        $this->assertSame('violet', $clan->fresh()->highlight_color);

        $this->actingAs($leader)->postJson("/api/shop/{$color->id}/unequip")->assertOk();

        $this->assertSame(ClanHighlight::DEFAULT_COLOR, $clan->fresh()->highlight_color);
    }

    public function test_unequip_effect_returns_default(): void
    {
        [$clan, $leader] = $this->clanWithLeader();

        $effect = $this->styleItem('effect', 'aurora');
        $this->own($leader, $effect);

        $this->actingAs($leader)->postJson("/api/shop/{$effect->id}/equip")->assertOk();
        $this->actingAs($leader)->postJson("/api/shop/{$effect->id}/unequip")->assertOk();

        $this->assertSame(ClanHighlight::DEFAULT_EFFECT, $clan->fresh()->highlight_effect);
    }

    /* ---------------------- Инвентарь ---------------------- */

    public function test_inventory_marks_equipped_style(): void
    {
        [$clan, $leader] = $this->clanWithLeader();

        $color = $this->styleItem('color', 'violet');
        $other = $this->styleItem('color', 'rose');

        $this->own($leader, $color);
        $this->own($leader, $other);

        $this->actingAs($leader)->postJson("/api/shop/{$color->id}/equip")->assertOk();

        // Инвентарь приходит под ключом items
        $inventory = collect(
            $this->actingAs($leader)->getJson('/api/shop/inventory')->assertOk()->json('items')
        );

        $equipped = $inventory->firstWhere('shop_item_id', $color->id);
        $notEquipped = $inventory->firstWhere('shop_item_id', $other->id);

        $this->assertTrue((bool) $equipped['equipped'], 'Надетое оформление помечено');
        $this->assertFalse((bool) $notEquipped['equipped'], 'Остальное не помечено');
    }
}
