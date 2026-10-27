<?php

namespace Tests\Feature;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Users\Models\User;
use Database\Seeders\ShopItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Магазин и инвентарь постранично: курсор без дублей и пропусков.
 */
class ShopPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_pages_cover_everything_once(): void
    {
        $this->seed(ShopItemSeeder::class);
        $user = User::factory()->create();

        $expected = ShopItem::active()->where('type', '!=', 'bundle')
            ->whereNotIn('type', ['clan_highlight', 'clan_highlight_style'])
            ->count();

        $first = $this->actingAs($user)->getJson('/api/shop?limit=25')->assertOk();
        $this->assertNotEmpty($first->json('type_counts'));
        $this->assertNotEmpty($first->json('bundles'));

        $ids = collect($first->json('items'))->pluck('id');
        $cursor = $first->json('next_cursor');

        while ($cursor) {
            $page = $this->actingAs($user)->getJson('/api/shop?limit=25&cursor=' . urlencode($cursor))->assertOk();
            $this->assertNull($page->json('bundles'));
            $ids = $ids->concat(collect($page->json('items'))->pluck('id'));
            $cursor = $page->json('next_cursor');
        }

        $this->assertSame($expected, $ids->count());
        $this->assertSame($ids->count(), $ids->unique()->count());

        // Вкладка фильтрует на сервере
        $pets = $this->getJson('/api/shop?limit=60&type=profile_pet')->assertOk()->json('items');
        $this->assertNotEmpty($pets);
        $this->assertSame(['profile_pet'], collect($pets)->pluck('type')->unique()->values()->all());

        // Битый курсор — с начала, без ошибки
        $this->getJson('/api/shop?limit=5&cursor=garbage')->assertOk()->assertJsonCount(5, 'items');
    }

    public function test_inventory_pages_with_tab_and_search(): void
    {
        $this->seed(ShopItemSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $first = $this->actingAs($admin)->getJson('/api/shop/inventory?limit=20')->assertOk();
        $total = $first->json('total');

        $this->assertGreaterThan(20, $total);
        $this->assertCount(20, $first->json('items'));
        $this->assertSame($total, $first->json('counts.all'));

        $second = $this->actingAs($admin)->getJson('/api/shop/inventory?limit=20&cursor=' . urlencode($first->json('next_cursor')))->assertOk();
        $this->assertEmpty(array_intersect(
            collect($first->json('items'))->pluck('shop_item_id')->all(),
            collect($second->json('items'))->pluck('shop_item_id')->all(),
        ));

        $pets = $this->actingAs($admin)->getJson('/api/shop/inventory?limit=60&tab=profile_pet')->assertOk()->json('items');
        $this->assertSame(['profile_pet'], collect($pets)->pluck('type')->unique()->values()->all());
    }
}
