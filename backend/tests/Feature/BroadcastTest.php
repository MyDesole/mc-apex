<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\NotificationCreated;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Вещание (broadcasting).
 *
 * В проекте нет отдельного BroadcastController: единственная поверхность —
 * системный роут /broadcasting/auth (routes/channels.php объявляет приватный
 * канал App.Models.User.{id}) и события-вещатели NotificationCreated/MessageSent.
 */
class BroadcastTest extends TestCase
{
    use RefreshDatabase;

    private function pair(): array
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $conversation = Conversation::create([
            'type' => 'direct',
            'last_message_at' => now(),
        ]);

        foreach ([$a, $b] as $user) {
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
            ]);
        }

        return [$a, $b, $conversation];
    }

    public function test_broadcasting_auth_with_null_driver_authorizes_everyone(): void
    {
        // Текущее поведение тестового окружения (BROADCAST_CONNECTION=null):
        // NullBroadcaster::auth() возвращает true, поэтому /broadcasting/auth
        // отдаёт 200 с пустым телом и гостю, и чужому каналу.
        // Реальная проверка канала App.Models.User.{id} выполняется только
        // при настоящем драйвере (reverb/pusher).
        $user = User::factory()->create();
        $other = User::factory()->create();

        $payload = [
            'channel_name' => "private-App.Models.User.{$user->id}",
            'socket_id' => '1234.5678',
        ];

        $guest = $this->postJson('/broadcasting/auth', $payload)->assertOk();
        $this->assertSame('', $guest->getContent());

        $this->actingAs($user)->postJson('/broadcasting/auth', $payload)->assertOk();

        $foreign = [
            'channel_name' => "private-App.Models.User.{$other->id}",
            'socket_id' => '1234.5678',
        ];

        $this->actingAs($user)->postJson('/broadcasting/auth', $foreign)->assertOk();
    }

    public function test_notification_created_event_broadcasts_on_own_private_channel(): void
    {
        $event = new NotificationCreated(42, ['title' => 'Привет']);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('private-App.Models.User.42', $channels[0]->name);
        $this->assertSame('notification.created', $event->broadcastAs());
        $this->assertSame(['title' => 'Привет'], $event->payload);
        $this->assertSame(42, $event->userId);
    }

    public function test_message_sent_event_targets_only_other_participants(): void
    {
        [$author, $recipient, $conversation] = $this->pair();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $author->id,
            'body' => 'Привет!',
        ]);

        $event = new MessageSent($message);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame("private-App.Models.User.{$recipient->id}", $channels[0]->name);
        $this->assertSame('message.sent', $event->broadcastAs());

        $payload = $event->broadcastWith();

        $this->assertSame('Привет!', $payload['message']['body']);
        $this->assertSame($conversation->id, $payload['message']['conversation_id']);
        $this->assertSame($author->id, $payload['message']['user']['id']);
        $this->assertSame($author->username, $payload['message']['user']['username']);
        $this->assertNull($payload['message']['reply_to']);
        $this->assertNull($payload['message']['forwarded_from']);
    }

    public function test_sending_chat_message_dispatches_broadcast_event(): void
    {
        Event::fake([MessageSent::class]);

        [$author, , $conversation] = $this->pair();

        $this->actingAs($author)
            ->postJson("/api/chat/conversations/{$conversation->id}/messages", ['body' => 'Эфир'])
            ->assertCreated();

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($author) {
            return $event->message->user_id === $author->id
                && $event->message->body === 'Эфир';
        });
    }

    public function test_notification_and_message_events_are_broadcastable(): void
    {
        $this->assertInstanceOf(
            \Illuminate\Contracts\Broadcasting\ShouldBroadcast::class,
            new NotificationCreated(1, [])
        );

        [$author, , $conversation] = $this->pair();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $author->id,
            'body' => 'x',
        ]);

        $this->assertInstanceOf(
            \Illuminate\Contracts\Broadcasting\ShouldBroadcast::class,
            new MessageSent($message)
        );
    }
}
