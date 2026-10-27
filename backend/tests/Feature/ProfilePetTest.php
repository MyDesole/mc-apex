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

    public function test_several_copies_and_up_to_three_pets(): void
    {
        $fox = \App\Domains\Shop\Models\ShopItem::create([
            'slug' => 'pet-fox-multi', 'name' => 'Лисёнок', 'type' => 'profile_pet',
            'effect_value' => 'fox', 'price' => 10, 'rarity' => 'rare', 'is_active' => true,
        ]);
        $cat = \App\Domains\Shop\Models\ShopItem::create([
            'slug' => 'pet-cat-multi', 'name' => 'Котик', 'type' => 'profile_pet',
            'effect_value' => 'cat', 'price' => 10, 'rarity' => 'rare', 'is_active' => true,
        ]);

        $user = User::factory()->create(['apex_coins' => 1000]);

        // Три лисёнка и котик — четыре разных экземпляра
        foreach ([$fox, $fox, $fox, $cat] as $pet) {
            $this->actingAs($user)->postJson("/api/shop/{$pet->id}/purchase")->assertSuccessful();
        }

        $rows = \App\Domains\Shop\Models\UserInventory::where('user_id', $user->id)->orderBy('id')->get();
        $this->assertCount(4, $rows);
        $this->assertSame([1, 2, 3], $rows->where('shop_item_id', $fox->id)->pluck('pet_serial')->values()->all());

        // Надеваем все четыре по очереди — гуляют трое, первый ушёл
        foreach ($rows as $row) {
            $this->actingAs($user)->postJson("/api/shop/pets/{$row->id}/equip")->assertOk();
        }

        $pets = $user->fresh()->profile_pets;
        $this->assertCount(3, $pets);
        $this->assertSame([$rows[1]->id, $rows[2]->id, $rows[3]->id], array_column($pets, 'inventory_id'));
        $this->assertSame('fox', $user->fresh()->profile_pet);

        // Имя конкретному экземпляру
        $this->actingAs($user)->putJson('/api/players/me/pet-name', ['name' => 'Рыжик', 'inventory_id' => $rows[2]->id])->assertOk();
        $this->assertSame('Рыжик', collect($user->fresh()->profile_pets)->firstWhere('inventory_id', $rows[2]->id)['name']);

        // Снять — уходит из профиля
        $this->actingAs($user)->postJson("/api/shop/pets/{$rows[3]->id}/unequip")->assertOk();
        $this->assertCount(2, $user->fresh()->profile_pets);

        // Чужой экземпляр — нельзя
        $other = User::factory()->create();
        $this->actingAs($other)->postJson("/api/shop/pets/{$rows[1]->id}/equip")->assertNotFound();
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
