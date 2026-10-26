<?php

namespace App\Domains\Clan\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Клановая война: вызов клану (лидеру соперника) или призыв участнику
 * от руководства своего клана принять участие.
 */
class ClanWarNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $type,
        public string $message,
        public int $warId,
        public ?string $note = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return array_filter([
            'type' => $this->type,
            'message' => $this->message,
            'war_id' => $this->warId,
            'note' => $this->note,
        ], fn ($value) => $value !== null);
    }
}
