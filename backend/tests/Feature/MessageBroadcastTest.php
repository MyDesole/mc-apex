<?php

namespace Tests\Feature;

use App\Domains\Chat\Events\MessageSent;
use App\Domains\Chat\Models\Conversation;
use App\Domains\Chat\Models\ConversationParticipant;
use App\Domains\Chat\Models\Message;
use App\Domains\Notifications\Events\NotificationCreated;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Мгновенная доставка сообщений.
 *
 * Событие о новом сообщении должно уходить в Reverb сразу, не через
 * очередь. Иначе рассылка ждёт воркера: если он не запущен, задачи
 * копятся в таблице jobs, и собеседник видит сообщение только после
 * обновления страницы. Именно так и было: в базе накопилось 348 задач.
 */
class MessageBroadcastTest extends TestCase
{
    use RefreshDatabase;

    /** Личный диалог двух игроков. */
    private function conversation(): array
    {
        $a = User::factory()->create(['username' => 'sender']);
        $b = User::factory()->create(['username' => 'receiver']);

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

    public function test_message_event_broadcasts_immediately(): void
    {
        [$a, , $conversation] = $this->conversation();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $a->id,
            'body' => 'привет',
        ]);

        $this->assertInstanceOf(
            ShouldBroadcastNow::class,
            new MessageSent($message),
            'рассылка сообщения должна идти сразу, а не через очередь',
        );
    }

    public function test_sending_message_does_not_queue_a_job(): void
    {
        [$a, , $conversation] = $this->conversation();

        Event::fake([MessageSent::class]);

        $this->actingAs($a)
            ->postJson("/api/chat/conversations/{$conversation->id}/messages", [
                'body' => 'сообщение без очереди',
            ])
            ->assertCreated();

        Event::assertDispatched(MessageSent::class);

        /*
         * Главная проверка: задача не должна попасть в таблицу jobs.
         * До правки сюда ложилась рассылка, и без воркера она оставалась
         * лежать — собеседник ничего не получал.
         */
        $this->assertSame(
            0,
            DB::table('jobs')->count(),
            'рассылка не должна ставиться в очередь',
        );
    }

    public function test_event_goes_to_other_participants(): void
    {
        [$a, $b, $conversation] = $this->conversation();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $a->id,
            'body' => 'привет',
        ]);

        $channels = array_map(
            fn ($channel) => (string) $channel,
            (new MessageSent($message))->broadcastOn(),
        );

        $this->assertContains(
            'private-App.Models.User.' . $b->id,
            $channels,
            'собеседник получает событие',
        );

        $this->assertNotContains(
            'private-App.Models.User.' . $a->id,
            $channels,
            'автор не получает своё же сообщение',
        );
    }

    public function test_notification_broadcasts_immediately(): void
    {
        $user = User::factory()->create();

        $event = new NotificationCreated($user->id, ['title' => 'Тест', 'body' => 'Текст']);

        $this->assertInstanceOf(
            ShouldBroadcastNow::class,
            $event,
            'уведомления тоже не должны ждать воркера',
        );
    }
}
