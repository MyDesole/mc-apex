<?php

namespace App\Domains\Chat\Events;

use App\Domains\Chat\Models\Message;
use App\Domains\Chat\Resources\MessageResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
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

    /**
     * Состав полей тот же, что у ответа API.
     *
     * Раньше массив собирался вручную и расходился с ресурсом: не было
     * списка reads и вложений, поэтому галочка прочтения на сообщении,
     * пришедшем по сокету, не вставала.
     */
    public function broadcastWith(): array
    {
        $m = $this->message->load(self::RELATIONS);

        $payload = (new MessageResource($m))->resolve();

        /*
         * Читателей у только что отправленного сообщения нет, но поле
         * должно быть: страница ждёт список, а не отсутствие ключа.
         */
        $payload['reads'] = $payload['reads'] ?? [];

        return ['message' => $payload];
    }

    /** Связи, нужные для полного состава полей. */
    private const RELATIONS = [
        'user:id,username,avatar,tier,is_verified',
        'replyTo.user:id,username,avatar',
        'forwardedFrom',
        'attachments',
        'reads',
    ];
}
