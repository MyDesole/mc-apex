<?php

namespace App\Domains\Clan\Notifications;

use App\Domains\Clan\Models\Clan;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClanApplicationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Clan $clan,
        public User $applicant,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'clan_application',
            'clan_id' => $this->clan->id,
            'clan_name' => $this->clan->name,
            'clan_tag' => $this->clan->tag,
            'applicant_id' => $this->applicant->id,
            'applicant_username' => $this->applicant->username,
            'message' => "{$this->applicant->username} подал заявку в клан [{$this->clan->tag}] {$this->clan->name}",
        ];
    }
}
