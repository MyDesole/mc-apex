<?php

namespace Tests\Feature;

use App\Domains\Chat\Events\ConversationChanged;
use App\Domains\Chat\Events\MessageSent;
use App\Domains\Chat\Models\Conversation;
use App\Domains\Chat\Models\Message;
use App\Domains\Chat\Models\MessageAttachment;
use App\Domains\Chat\Services\ChatService;
use App\Domains\Clan\Models\Clan;
use App\Domains\Friends\Models\Friendship;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Мессенджер: исправленные дыры, живые правки, беседы, подарки,
 * голосовые и карточки ссылок.
 */
class ChatMessengerTest extends TestCase
{
    use RefreshDatabase;

    private function chat(): ChatService
    {
        return app(ChatService::class);
    }

    private function befriend(User $a, User $b): void
    {
        Friendship::create(['user_id' => $a->id, 'friend_id' => $b->id, 'status' => 'accepted']);
    }

    /** @return array{0: User, 1: User, 2: Conversation} */
    private function direct(): array
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        return [$a, $b, $this->chat()->startDirect($a, $b->id)];
    }

    /* ----------------------------- Дыры ----------------------------- */

    public function test_cannot_attach_someone_elses_upload(): void
    {
        Storage::fake('public');
        [$a, $b, $conversation] = $this->direct();

        $foreign = $this->chat()->uploadAttachment($b, UploadedFile::fake()->image('b.png'));

        Sanctum::actingAs($a);

        // Только чужое вложение — сообщение пустое
        $this->postJson("/api/chat/conversations/{$conversation->id}/messages", ['attachments' => [$foreign->id]])
            ->assertStatus(422);

        $this->postJson("/api/chat/conversations/{$conversation->id}/messages", ['body' => 'hi', 'attachments' => [$foreign->id]])
            ->assertCreated()
            ->assertJsonCount(0, 'message.attachments');

        $this->assertNull($foreign->fresh()->message_id);
    }

    public function test_forwarded_flag_does_not_bypass_paid_stickers(): void
    {
        ShopItem::create([
            'slug' => 'stickers-mobs', 'name' => 'Стикеры «Мобы»', 'type' => ShopItem::TYPE_STICKER_PACK,
            'effect_value' => 'mobs', 'rarity' => 'epic', 'price' => 100, 'is_active' => true,
            'metadata' => ['stickers' => ['creeper']],
        ]);
        [$a, $b, $conversation] = $this->direct();

        Sanctum::actingAs($a);

        $this->postJson("/api/chat/conversations/{$conversation->id}/messages", [
            'body' => '[sticker:mobs/creeper]',
            'forwarded_from_user_id' => $b->id,
        ])->assertStatus(422);

        // И правка текста в метку стикера тоже не проходит
        $message = $this->chat()->send($conversation, $a, 'обычный текст');

        $this->putJson("/api/chat/messages/{$message->id}", ['body' => '[sticker:mobs/creeper]'])
            ->assertStatus(422);

        $this->assertSame('обычный текст', $message->fresh()->body);
    }

    public function test_deleting_old_message_does_not_bump_conversation(): void
    {
        [$a, , $conversation] = $this->direct();

        $old = $this->chat()->send($conversation, $a, 'старое');
        $this->travel(2)->hours();
        $fresh = $this->chat()->send($conversation, $a, 'новое');
        $this->travel(3)->hours();

        Sanctum::actingAs($a);
        $this->deleteJson("/api/chat/messages/{$old->id}")->assertOk();

        $this->assertSame(
            $fresh->fresh()->created_at->toDateTimeString(),
            $conversation->fresh()->last_message_at->toDateTimeString(),
        );
    }

    public function test_after_id_returns_gap_from_the_start(): void
    {
        [$a, $b, $conversation] = $this->direct();

        $first = $this->chat()->send($conversation, $a, '0');

        for ($i = 1; $i <= 8; $i++) {
            $this->chat()->send($conversation, $b, (string) $i);
        }

        Sanctum::actingAs($a);

        $page = $this->getJson("/api/chat/conversations/{$conversation->id}?after_id={$first->id}&limit=3&mark_read=0")
            ->assertOk();

        // Сначала 1, 2, 3 — а не последние три, иначе середина теряется
        $this->assertSame(['1', '2', '3'], collect($page->json('messages'))->pluck('body')->all());
        $this->assertTrue($page->json('has_more'));
    }

    public function test_history_reports_first_unread_before_marking(): void
    {
        [$a, $b, $conversation] = $this->direct();

        $this->chat()->send($conversation, $a, 'моё');
        $unread = $this->chat()->send($conversation, $b, 'новое 1');
        $this->chat()->send($conversation, $b, 'новое 2');

        Sanctum::actingAs($a);

        $this->getJson("/api/chat/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('first_unread_id', $unread->id);

        // Второй раз всё уже прочитано
        $this->getJson("/api/chat/conversations/{$conversation->id}")
            ->assertJsonPath('first_unread_id', null);
    }

    public function test_empty_direct_is_hidden_from_list(): void
    {
        [$a, $b, $conversation] = $this->direct();

        Sanctum::actingAs($b);
        $this->getJson('/api/chat/conversations')->assertJsonCount(0, 'conversations');

        $this->chat()->send($conversation, $a, 'привет');
        $this->getJson('/api/chat/conversations')->assertJsonCount(1, 'conversations');
    }

    public function test_message_search_treats_percent_literally(): void
    {
        [$a, , $conversation] = $this->direct();

        $this->chat()->send($conversation, $a, 'скидка 50% на всё');
        $this->chat()->send($conversation, $a, 'просто 50 монет');

        Sanctum::actingAs($a);

        $this->getJson("/api/chat/conversations/{$conversation->id}/search?q=" . urlencode('50%'))
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    /* ----------------------------- Живые изменения ----------------------------- */

    public function test_edit_delete_reaction_and_pin_are_broadcast(): void
    {
        [$a, $b, $conversation] = $this->direct();
        $message = $this->chat()->send($conversation, $a, 'было');

        Event::fake([ConversationChanged::class]);
        Sanctum::actingAs($a);

        $this->putJson("/api/chat/messages/{$message->id}", ['body' => 'стало'])->assertOk();
        $this->postJson("/api/chat/messages/{$message->id}/reactions", ['emoji' => '🔥'])
            ->assertOk()
            ->assertJsonPath('reactions.0.user_ids', [$a->id]);
        $this->postJson("/api/chat/conversations/{$conversation->id}/pin", ['message_id' => $message->id])->assertOk();
        $this->deleteJson("/api/chat/messages/{$message->id}")->assertOk();

        $kinds = [];
        Event::assertDispatched(ConversationChanged::class, function (ConversationChanged $event) use (&$kinds, $b) {
            $kinds[] = $event->kind;
            $channels = collect($event->broadcastOn())->map->name->all();
            $this->assertContains('private-App.Models.User.' . $b->id, $channels);

            return true;
        });

        $this->assertSame(['message.updated', 'reactions', 'pinned', 'message.deleted'], $kinds);
    }

    /* ----------------------------- Беседы ----------------------------- */

    public function test_group_lifecycle(): void
    {
        $owner = User::factory()->create();
        $friend = User::factory()->create();
        $second = User::factory()->create();
        $stranger = User::factory()->create();
        $this->befriend($owner, $friend);
        $this->befriend($second, $owner);

        Event::fake([MessageSent::class, ConversationChanged::class]);
        Sanctum::actingAs($owner);

        // Незнакомого не позвать
        $this->postJson('/api/chat/groups', ['title' => 'Пати', 'user_ids' => [$friend->id, $stranger->id]])
            ->assertStatus(422);

        $id = $this->postJson('/api/chat/groups', ['title' => '  Пати  на  кв ', 'user_ids' => [$friend->id]])
            ->assertCreated()
            ->assertJsonPath('conversation.type', 'group')
            ->assertJsonPath('conversation.title', 'Пати на кв')
            ->json('conversation.id');

        $this->postJson("/api/chat/groups/{$id}/members", ['user_ids' => [$second->id]])->assertOk();
        $this->assertSame(3, Conversation::find($id)->participants()->count());

        // Служебные отметки в ленте
        $this->assertSame(2, Message::where('conversation_id', $id)->where('kind', 'system')->count());

        // Участник не может исключать и переименовывать
        Sanctum::actingAs($friend);
        $this->deleteJson("/api/chat/groups/{$id}/members/{$second->id}")->assertStatus(422);
        $this->putJson("/api/chat/groups/{$id}", ['title' => 'Моя'])->assertStatus(422);
        $this->postJson("/api/chat/conversations/{$id}/messages", ['body' => 'всем привет'])->assertCreated();

        // Создатель исключает — исключённый больше не видит беседу
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/chat/groups/{$id}/members/{$second->id}")->assertOk();
        $this->putJson("/api/chat/groups/{$id}", ['title' => 'Новое имя'])->assertOk()
            ->assertJsonPath('conversation.title', 'Новое имя');

        Sanctum::actingAs($second);
        $this->getJson("/api/chat/conversations/{$id}")->assertForbidden();

        // Создатель вышел — беседа у друга; ушёл последний — беседы нет
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/chat/groups/{$id}/members/{$owner->id}")->assertOk();
        $this->assertSame($friend->id, Conversation::find($id)->owner_id);

        Sanctum::actingAs($friend);
        $this->deleteJson("/api/chat/groups/{$id}/members/{$friend->id}")->assertOk()->assertJsonPath('conversation', null);
        $this->assertNull(Conversation::find($id));
    }

    /* ----------------------------- Подарок ----------------------------- */

    public function test_gift_from_dialog_moves_coins_and_leaves_parcel(): void
    {
        $a = User::factory()->create(['apex_coins' => 5000]);
        $b = User::factory()->create(['apex_coins' => 0]);
        $this->befriend($a, $b);
        $conversation = $this->chat()->startDirect($a, $b->id);

        Sanctum::actingAs($a);

        $response = $this->postJson("/api/chat/conversations/{$conversation->id}/gift", ['amount' => 1000, 'note' => 'на кейс'])
            ->assertCreated()
            ->assertJsonPath('message.kind', 'gift')
            ->assertJsonPath('message.body', 'на кейс')
            ->assertJsonPath('message.meta.amount', 1000)
            ->assertJsonPath('balance', 4000);

        $this->assertSame($response->json('message.meta.received'), $b->fresh()->apex_coins);

        // Подарок нельзя ни переслать, ни изменить
        $id = $response->json('message.id');
        $this->putJson("/api/chat/messages/{$id}", ['body' => 'другое'])->assertStatus(422);
    }

    public function test_gift_requires_friendship_and_keeps_coins(): void
    {
        $a = User::factory()->create(['apex_coins' => 5000]);
        $b = User::factory()->create();
        $conversation = $this->chat()->startDirect($a, $b->id);

        Sanctum::actingAs($a);

        $this->postJson("/api/chat/conversations/{$conversation->id}/gift", ['amount' => 1000])->assertStatus(422);

        $this->assertSame(5000, $a->fresh()->apex_coins);
        $this->assertSame(0, Message::where('conversation_id', $conversation->id)->count());
    }

    /* ----------------------------- Голосовые ----------------------------- */

    public function test_voice_message_is_uploaded_and_sent(): void
    {
        Storage::fake('public');
        [$a, , $conversation] = $this->direct();

        Sanctum::actingAs($a);

        $file = UploadedFile::fake()->createWithContent('voice.webm', "\x1A\x45\xDF\xA3" . str_repeat("\0", 64))
            ->mimeType('audio/webm');

        $id = $this->post('/api/chat/voice', ['file' => $file, 'duration' => 7], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('attachment.is_voice', true)
            ->assertJsonPath('attachment.duration', 7)
            ->json('attachment.id');

        $this->postJson("/api/chat/conversations/{$conversation->id}/messages", ['attachments' => [$id]])
            ->assertCreated()
            ->assertJsonPath('message.attachments.0.is_voice', true)
            ->assertJsonPath('message.attachments.0.duration', 7);

        // Не аудио — отказ
        $this->post('/api/chat/voice', ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), 'duration' => 3], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    /* ----------------------------- Карточки ----------------------------- */

    public function test_site_links_become_cards(): void
    {
        [$a, $b, $conversation] = $this->direct();
        $clan = Clan::create(['name' => 'Небо', 'tag' => 'SKY', 'leader_id' => $b->id]);
        $host = parse_url(config('app.url'), PHP_URL_HOST);

        Sanctum::actingAs($a);

        $this->postJson("/api/chat/conversations/{$conversation->id}/messages", [
            'body' => "глянь http://{$host}/players/{$b->id} и /clans/{$clan->id}, а это чужое https://evil.example/players/{$a->id}",
        ])
            ->assertCreated()
            ->assertJsonCount(2, 'message.cards')
            ->assertJsonPath('message.cards.0.type', 'player')
            ->assertJsonPath('message.cards.0.username', $b->username)
            ->assertJsonPath('message.cards.0.url', "/players/{$b->id}")
            ->assertJsonPath('message.cards.1.type', 'clan')
            ->assertJsonPath('message.cards.1.tag', 'SKY');

        // В истории карточки тоже есть
        $this->getJson("/api/chat/conversations/{$conversation->id}")
            ->assertJsonCount(2, 'messages.0.cards');
    }
}
