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
 * Собеседник печатает.
 *
 * Страница сообщает об этом не на каждую букву, а с промежутком, иначе
 * события забили бы канал. Получатели гасят надпись сами, если событий
 * давно не было.
 */
class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public User $user,
    ) {
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
            ->where('user_id', '!=', $this->user->id)
            ->pluck('user_id')
            ->each(function ($userId) use (&$channels) {
                $channels[] = new PrivateChannel('App.Models.User.' . $userId);
            });

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'user.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'user' => [
                'id' => $this->user->id,
                'username' => $this->user->username,
                'avatar_url' => $this->user->avatar_url,
            ],
        ];
    }
}
