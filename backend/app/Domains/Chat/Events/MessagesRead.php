<?php

namespace App\Domains\Chat\Events;

use App\Domains\Chat\Models\Conversation;
use App\Domains\Users\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Собеседник прочитал сообщения диалога.
 *
 * Нужно, чтобы галочка прочтения появлялась сразу, а не после
 * обновления страницы.
 *
 * Событие уходит всем остальным участникам диалога, а не только авторам
 * уже прочитанных сообщений: иначе в диалоге без сообщений от собеседника
 * событие не приходило вовсе. Страница сама решает, какие сообщения
 * отметить, — она сравнивает автора и время прочтения.
 */
class MessagesRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public User $reader,
    ) {
    }

    /** Собирает событие по диалогу. */
    public static function forConversation(Conversation $conversation, User $reader): self
    {
        return new self($conversation, $reader);
    }

    /**
     * Остальным участникам диалога: каждому в его личный канал.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        $this->conversation
            ->participants()
            ->where('user_id', '!=', $this->reader->id)
            ->pluck('user_id')
            ->each(function ($userId) use (&$channels) {
                $channels[] = new PrivateChannel('App.Models.User.' . $userId);
            });

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'messages.read';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'reader' => [
                'id' => $this->reader->id,
                'username' => $this->reader->username,
                'avatar_url' => $this->reader->avatar_url,
            ],
            'read_at' => now()->toIso8601String(),
        ];
    }
}
