<?php

namespace App\Notifications;

use App\Models\Clan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClanApplicationAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(public Clan $clan) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'clan_application_accepted',
            'clan_id' => $this->clan->id,
            'clan_name' => $this->clan->name,
            'clan_tag' => $this->clan->tag,
            'message' => "Ваша заявка в клан [{$this->clan->tag}] {$this->clan->name} принята!",
        ];
    }
}
