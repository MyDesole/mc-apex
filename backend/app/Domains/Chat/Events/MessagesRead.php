<?php

namespace App\Domains\Chat\Events;

use App\Domains\Chat\Models\Conversation;
use App\Domains\Chat\Models\Message;
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
 * обновления страницы. Событие уходит авторам прочитанных сообщений.
 */
class MessagesRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, int>  $authorIds  кому сообщаем о прочтении
     */
    public function __construct(
        public Conversation $conversation,
        public User $reader,
        public array $authorIds = [],
    ) {
    }

    /** Собирает событие по диалогу: кто прочитал и чьи сообщения. */
    public static function forConversation(Conversation $conversation, User $reader): self
    {
        $authorIds = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $reader->id)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return new self($conversation, $reader, $authorIds);
    }

    /**
     * Авторам прочитанных сообщений: каждому в его личный канал.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            fn (int $userId) => new PrivateChannel('App.Models.User.' . $userId),
            $this->authorIds,
        );
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
