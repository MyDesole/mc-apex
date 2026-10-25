<?php

namespace App\Domains\Forum\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Ответ на форуме: в теме игрока или на его ответ.
 */
class ForumReplyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $type,
        public string $message,
        public int $topicId,
        public int $replyId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'topic_id' => $this->topicId,
            'reply_id' => $this->replyId,
        ];
    }
}
