<?php

namespace Tests\Feature;

use App\Domains\Chat\Events\MessagesRead;
use App\Domains\Chat\Events\UserTyping;
use App\Domains\Chat\Models\Conversation;
use App\Domains\Chat\Services\ChatService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Живые события чата: прочтение и «печатает».
 *
 * Без них галочка прочтения и надпись о наборе текста появлялись только
 * после обновления страницы.
 */
class ChatLiveEventsTest extends TestCase
{
    use RefreshDatabase;

    private function direct(User $me, User $other): Conversation
    {
        return app(ChatService::class)->startDirect($me, $other->id);
    }

    public function test_reading_a_conversation_broadcasts_to_the_author(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();

        $conversation = $this->direct($author, $reader);

        app(ChatService::class)->send($conversation, $author, 'привет');

        Sanctum::actingAs($reader);

        Event::fake([MessagesRead::class]);

        $this->postJson("/api/chat/conversations/{$conversation->id}/read")->assertOk();

        Event::assertDispatched(MessagesRead::class, function (MessagesRead $event) use ($reader) {
            return $event->reader->id === $reader->id;
        });
    }

    public function test_reading_a_single_message_broadcasts(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();

        $conversation = $this->direct($author, $reader);
        $message = app(ChatService::class)->send($conversation, $author, 'проверка');

        Sanctum::actingAs($reader);

        Event::fake([MessagesRead::class]);

        $this->postJson("/api/chat/messages/{$message->id}/read")->assertOk();

        Event::assertDispatched(MessagesRead::class);
    }

    public function test_typing_broadcasts_to_the_other_participant(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $conversation = $this->direct($me, $other);

        Sanctum::actingAs($me);

        Event::fake([UserTyping::class]);

        $this->postJson("/api/chat/conversations/{$conversation->id}/typing")->assertOk();

        Event::assertDispatched(UserTyping::class, function (UserTyping $event) use ($me, $conversation) {
            return $event->user->id === $me->id
                && $event->conversation->id === $conversation->id;
        });
    }

    public function test_typing_event_goes_only_to_others(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $conversation = $this->direct($me, $other);

        $event = new UserTyping($conversation, $me);
        $channels = array_map(fn ($c) => $c->name, $event->broadcastOn());

        $this->assertContains('private-App.Models.User.' . $other->id, $channels);
        $this->assertNotContains(
            'private-App.Models.User.' . $me->id,
            $channels,
            'Автору печати событие не нужно'
        );
    }

    public function test_outsider_cannot_report_typing(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $outsider = User::factory()->create();

        $conversation = $this->direct($a, $b);

        Sanctum::actingAs($outsider);

        $this->postJson("/api/chat/conversations/{$conversation->id}/typing")->assertForbidden();
    }

    public function test_read_event_goes_to_every_other_participant(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();

        $conversation = $this->direct($a, $b);

        // Третий участник: проверяем, что событие уходит всем, кроме читающего
        \App\Domains\Chat\Models\ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $c->id,
        ]);

        $event = new MessagesRead($conversation, $a);
        $channels = array_map(fn ($ch) => $ch->name, $event->broadcastOn());

        $this->assertContains('private-App.Models.User.' . $b->id, $channels);
        $this->assertContains('private-App.Models.User.' . $c->id, $channels);
        $this->assertNotContains('private-App.Models.User.' . $a->id, $channels);
    }

    public function test_message_event_carries_readers_field(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();

        $conversation = $this->direct($author, $reader);
        $message = app(ChatService::class)->send($conversation, $author, 'состав полей');

        $payload = (new \App\Domains\Chat\Events\MessageSent($message))->broadcastWith();

        $this->assertArrayHasKey('reads', $payload['message']);
        $this->assertArrayHasKey('attachments', $payload['message']);
    }
}
