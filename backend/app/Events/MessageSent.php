<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
    }

    /**
     * Событие летит в канал каждого участника диалога,
     * кроме автора (ему не нужно получать своё же сообщение).
     */
    public function broadcastOn(): array
    {
        $channels = [];

        $this->message->conversation
            ->participants()
            ->where('user_id', '!=', $this->message->user_id)
            ->pluck('user_id')
            ->each(function ($userId) use (&$channels) {
                $channels[] = new PrivateChannel('App.Models.User.' . $userId);
            });

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $m = $this->message->load([
            'user:id,username,avatar,tier,is_verified',
            'replyTo.user:id,username,avatar',
            'forwardedFrom',
        ]);

        return [
            'message' => [
                'id' => $m->id,
                'conversation_id' => $m->conversation_id,
                'body' => $m->body,
                'created_at' => $m->created_at->toIso8601String(),
                'user' => [
                    'id' => $m->user->id,
                    'username' => $m->user->username,
                    'avatar_url' => $m->user->avatar_url,
                    'tier' => $m->user->tier,
                    'is_verified' => $m->user->is_verified,
                ],
                'reply_to' => $m->replyTo ? [
                    'id' => $m->replyTo->id,
                    'body' => $m->replyTo->body,
                    'user' => [
                        'id' => $m->replyTo->user->id,
                        'username' => $m->replyTo->user->username,
                        'avatar_url' => $m->replyTo->user->avatar_url,
                    ],
                ] : null,
                'forwarded_from' => $m->forwardedFrom ? [
                    'id' => $m->forwardedFrom->id,
                    'username' => $m->forwardedFrom->username,
                    'avatar_url' => $m->forwardedFrom->avatar_url,
                ] : null,
            ],
        ];
    }
}
