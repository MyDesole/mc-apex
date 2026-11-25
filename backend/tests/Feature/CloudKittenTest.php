<?php

namespace Tests\Feature;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Users\Models\User;
use Database\Seeders\CloudKittenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloudKittenTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_cosmetics_are_paid_equippable_and_not_duplicated_by_reseeding(): void
    {
        $this->seed(CloudKittenSeeder::class);
        $user = User::factory()->create(['apex_coins' => 155000]);
        foreach (['cloud-kitten-frame', 'cloud-hug-entrance'] as $slug) {
            $item = ShopItem::where('slug', $slug)->firstOrFail();
            $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();
            $this->postJson("/api/shop/{$item->id}/equip")->assertOk();
            $this->postJson("/api/shop/{$item->id}/purchase")->assertUnprocessable();
        }
        $this->assertSame(0, (int) $user->fresh()->apex_coins);
        $this->getJson("/api/players/{$user->username}")->assertOk()
            ->assertJsonPath('user.avatar_frame', 'cloud_kitten')
            ->assertJsonPath('user.profile_entrance', 'cloud_hug');
        $item->update(['price' => 99000]);
        $this->seed(CloudKittenSeeder::class);
        $this->assertSame(99000, $item->fresh()->price);
        $this->assertSame(2, ShopItem::whereIn('slug', ['cloud-kitten-frame', 'cloud-hug-entrance'])->count());
    }
}
