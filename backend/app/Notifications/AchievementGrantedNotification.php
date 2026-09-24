<?php

namespace App\Notifications;

use App\Models\Achievement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AchievementGrantedNotification extends Notification
{
    use Queueable;

    public function __construct(public Achievement $achievement)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'achievement_granted',
            'achievement_id' => $this->achievement->id,
            'code' => $this->achievement->code,
            'name' => $this->achievement->name,
            'icon' => $this->achievement->icon,
            'color' => $this->achievement->color,
            'points' => $this->achievement->points,
            'message' => "Вы получили ачивку {$this->achievement->icon} «{$this->achievement->name}»",
        ];
    }
}
