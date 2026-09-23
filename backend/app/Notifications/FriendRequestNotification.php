<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FriendRequestNotification extends Notification
{
    use Queueable;

    public function __construct(public User $from) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'friend_request',
            'from_id' => $this->from->id,
            'from_username' => $this->from->username,
            'message' => "{$this->from->username} хочет добавить вас в друзья",
        ];
    }
}
