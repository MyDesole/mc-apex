<?php

namespace App\Domains\Chat\Events;

use App\Domains\Chat\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Изменение в диалоге, которое собеседник должен увидеть сразу:
 * правка и удаление сообщения, реакции, закреп, состав и название беседы.
 *
 * Раньше об этом узнавали только после перезагрузки страницы: сокет
 * приносил лишь новые сообщения.
 *
 * kind: message.updated | message.deleted | reactions | pinned | conversation
 */
class ConversationChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<int>  $extraUserIds  кому ещё сообщить (например, исключённому из беседы)
     */
    public function __construct(
        public Conversation $conversation,
        public string $kind,
        public array $data = [],
        public array $extraUserIds = [],
    ) {
    }

    public function broadcastOn(): array
    {
        return $this->conversation
            ->participants()
            ->pluck('user_id')
            ->merge($this->extraUserIds)
            ->unique()
            ->map(fn ($userId) => new PrivateChannel('App.Models.User.' . $userId))
            ->values()
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'conversation.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'kind' => $this->kind,
            ...$this->data,
        ];
    }
}
