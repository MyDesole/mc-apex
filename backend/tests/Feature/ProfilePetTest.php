<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Питомец профиля: действия раз в день, опыт, настроение, имя.
 */
class ProfilePetTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_pet_feed_and_play_once_a_day(): void
    {
        $owner = User::factory()->create();
        $owner->forceFill(['profile_pet' => 'fox'])->save();
        $guest = User::factory()->create();

        // Голодный, пока никто не кормил
        $this->getJson("/api/players/{$owner->id}/pet")->assertOk()->assertJsonPath('mood', 'hungry');

        $this->actingAs($guest)->postJson("/api/players/{$owner->id}/pet/pet")->assertOk();
        $this->actingAs($guest)->postJson("/api/players/{$owner->id}/pet/pet")->assertStatus(422);

        $stats = $this->actingAs($guest)->postJson("/api/players/{$owner->id}/pet/feed")
            ->assertOk()
            ->json();

        // 1 за ласку + 2 за еду; накормлен — теперь радостный
        $this->assertSame(3, $stats['xp']);
        $this->assertSame('happy', $stats['mood']);
        $this->assertEqualsCanonicalizing(['pet', 'feed'], $stats['done_today']);
        $this->assertSame($guest->id, $stats['friends'][0]->id ?? $stats['friends'][0]['id']);

        $this->actingAs($guest)->postJson("/api/players/{$owner->id}/pet/dance")->assertNotFound();
    }

    public function test_each_pet_is_minted_with_traits_and_serial(): void
    {
        $pet = \App\Domains\Shop\Models\ShopItem::create([
            'slug' => 'pet-fox-test', 'name' => 'Лисёнок', 'type' => 'profile_pet',
            'effect_value' => 'fox', 'price' => 10, 'rarity' => 'rare', 'is_active' => true,
        ]);

        $first = User::factory()->create(['apex_coins' => 100]);
        $second = User::factory()->create(['apex_coins' => 100]);

        $this->actingAs($first)->postJson("/api/shop/{$pet->id}/purchase")->assertSuccessful();
        $this->actingAs($second)->postJson("/api/shop/{$pet->id}/purchase")->assertSuccessful();

        $rows = \App\Domains\Shop\Models\UserInventory::where('shop_item_id', $pet->id)->orderBy('id')->get();
        $this->assertSame([1, 2], $rows->pluck('pet_serial')->all());
        $this->assertArrayHasKey('model', $rows[0]->pet_traits);

        // Надели — черты экземпляра переехали в профиль
        $this->actingAs($first)->postJson("/api/shop/{$pet->id}/equip")->assertOk();
        $this->assertSame($rows[0]->pet_traits['model'], $first->fresh()->pet_traits['model']);

        $traits = $this->getJson("/api/players/{$first->id}/pet")->assertOk()->json('traits');
        $this->assertSame(1, $traits['serial']);
        $this->assertNotEmpty($traits['model']['name']);
        $this->assertGreaterThan(0, $traits['accessory']['chance']);
        $this->assertContains($traits['rarity'], ['common', 'rare', 'epic', 'legendary', 'mythic']);

        // В инвентаре видно черты своего экземпляра
        $inventory = $this->actingAs($first)->getJson('/api/shop/inventory')->json('items');
        $this->assertSame(1, collect($inventory)->firstWhere('shop_item_id', $pet->id)['pet']['serial']);
    }

    public function test_pet_without_pet_and_rename(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();

        $this->actingAs($guest)->postJson("/api/players/{$owner->id}/pet/pet")->assertStatus(422);

        $this->actingAs($owner)->putJson('/api/players/me/pet-name', ['name' => '<b>Барсик</b>'])
            ->assertOk()
            ->assertJsonPath('name', 'Барсик');
    }
}
