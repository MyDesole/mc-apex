<?php

namespace App\Domains\Moderation\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Уведомления модерации: предупреждение нарушителю и ответ на жалобу.
 */
class ModerationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $type,
        public string $message,
        public array $extra = [],
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
            ...$this->extra,
        ];
    }
}
