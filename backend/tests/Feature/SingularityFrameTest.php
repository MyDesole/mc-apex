<?php

namespace Tests\Feature;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Users\Models\User;
use Database\Seeders\RiftEntranceSeeder;
use Database\Seeders\SingularityFrameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingularityFrameTest extends TestCase
{
    use RefreshDatabase;

    public function test_frame_is_paid_equippable_and_seed_does_not_reset_admin_changes(): void
    {
        $this->seed(SingularityFrameSeeder::class);
        $item = ShopItem::where('slug', 'singularity-frame')->firstOrFail();
        $user = User::factory()->create(['apex_coins' => 90000]);
        $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();
        $this->assertSame(5000, (int) $user->fresh()->apex_coins);
        $this->postJson("/api/shop/{$item->id}/equip")->assertOk();
        $this->assertSame('singularity', $user->fresh()->avatar_frame);
        $this->postJson("/api/shop/{$item->id}/purchase")->assertUnprocessable();
        $this->assertSame(5000, (int) $user->fresh()->apex_coins);
        $item->update(['price' => 95000]);
        $this->seed(SingularityFrameSeeder::class);
        $this->assertSame(1, ShopItem::where('slug', 'singularity-frame')->count());
        $this->assertSame(95000, $item->fresh()->price);
    }

    public function test_rift_entrance_is_purchased_and_visible_on_public_profile(): void
    {
        $this->seed(RiftEntranceSeeder::class);
        $item = ShopItem::where('slug', 'rift-entrance')->firstOrFail();
        $user = User::factory()->create(['apex_coins' => 120000]);
        $this->actingAs($user)->postJson("/api/shop/{$item->id}/purchase")->assertCreated();
        $this->postJson("/api/shop/{$item->id}/equip")->assertOk();
        $this->assertSame(0, (int) $user->fresh()->apex_coins);
        $this->assertSame('rift', $user->fresh()->profile_entrance);
        $this->getJson("/api/players/{$user->username}")->assertOk()->assertJsonPath('user.profile_entrance', 'rift');
    }
}
