<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VerifiedNotification extends Notification
{
    use Queueable;

    public function __construct(public ?string $reason = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'verified',
            'reason' => $this->reason,
            'message' => $this->reason
                ? "Вы верифицированы: {$this->reason}"
                : 'Ваш аккаунт верифицирован ✅',
        ];
    }
}
