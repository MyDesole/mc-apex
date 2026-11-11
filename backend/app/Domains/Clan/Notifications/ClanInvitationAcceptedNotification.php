<?php

namespace App\Domains\Clan\Notifications;

use App\Domains\Clan\Models\Clan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Руководству клана: приглашённый игрок принял приглашение. */
class ClanInvitationAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(public Clan $clan, public string $username)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'clan_invitation_accepted',
            'clan_id' => $this->clan->id,
            'message' => "{$this->username} принял(а) приглашение и вступил(а) в клан [{$this->clan->tag}]",
        ];
    }
}
