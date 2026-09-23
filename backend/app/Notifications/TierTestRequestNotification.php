<?php

namespace App\Notifications;

use App\Models\TierTest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TierTestRequestNotification extends Notification
{
    use Queueable;

    public function __construct(public TierTest $tierTest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->tierTest->loadMissing('user:id,username');

        return [
            'type' => 'tier_test_request',
            'tier_test_id' => $this->tierTest->id,
            'user_id' => $this->tierTest->user_id,
            'username' => $this->tierTest->user->username,
            'mode' => $this->tierTest->mode,
            'message' => "{$this->tierTest->user->username} записался к вам на тир-тест",
        ];
    }
}
