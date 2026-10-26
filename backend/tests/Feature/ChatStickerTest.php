<?php

namespace Tests\Feature;

use App\Domains\Chat\Services\ChatService;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Стикеры в чате: отправить можно только из своего набора.
 */
class ChatStickerTest extends TestCase
{
    use RefreshDatabase;

    private ShopItem $pack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pack = ShopItem::create([
            'slug' => 'stickers-mobs', 'name' => 'Стикеры «Мобы»', 'type' => ShopItem::TYPE_STICKER_PACK,
            'effect_value' => 'mobs', 'rarity' => 'epic', 'price' => 100, 'is_active' => true,
            'metadata' => ['stickers' => ['creeper', 'bee']],
        ]);
    }

    private function conversation(User $me, User $other)
    {
        return app(ChatService::class)->startDirect($me, $other->id);
    }

    public function test_owner_sends_sticker_and_others_cannot(): void
    {
        $owner = User::factory()->create();
        $friend = User::factory()->create();
        UserInventory::create(['user_id' => $owner->id, 'shop_item_id' => $this->pack->id, 'quantity' => 1]);

        $conversation = $this->conversation($owner, $friend);

        Sanctum::actingAs($owner);
        $this->postJson("/api/chat/conversations/{$conversation->id}/messages", ['body' => '[sticker:mobs/creeper]'])
            ->assertCreated();

        // Стикера нет в наборе
        $this->postJson("/api/chat/conversations/{$conversation->id}/messages", ['body' => '[sticker:mobs/dragon]'])
            ->assertStatus(422);

        // Набора у собеседника нет
        Sanctum::actingAs($friend);
        $this->postJson("/api/chat/conversations/{$conversation->id}/messages", ['body' => '[sticker:mobs/bee]'])
            ->assertStatus(422);

        // Обычный текст по-прежнему уходит
        $this->postJson("/api/chat/conversations/{$conversation->id}/messages", ['body' => 'привет'])
            ->assertCreated();
    }

    public function test_sticker_packs_list_and_admin_gets_all(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/shop/stickers')->assertOk()->assertJsonCount(0, 'packs');

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $this->getJson('/api/shop/stickers')
            ->assertOk()
            ->assertJsonPath('packs.0.id', 'mobs')
            ->assertJsonPath('packs.0.stickers.0', 'creeper');
    }
}
