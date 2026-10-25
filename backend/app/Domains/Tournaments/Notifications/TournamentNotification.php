<?php

namespace App\Domains\Tournaments\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Уведомления участникам турнира: заявка одобрена/отклонена, победа.
 */
class TournamentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $type,
        public string $message,
        public int $tournamentId,
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
            'tournament_id' => $this->tournamentId,
        ];
    }
}
