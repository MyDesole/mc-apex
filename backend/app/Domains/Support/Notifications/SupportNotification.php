<?php

namespace App\Domains\Support\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Ответ поддержки игроку или новое сообщение для персонала. */
class SupportNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $type,
        public string $message,
        public int $ticketId,
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
            'ticket_id' => $this->ticketId,
        ];
    }
}
