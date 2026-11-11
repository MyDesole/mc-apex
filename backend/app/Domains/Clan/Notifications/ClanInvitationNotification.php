<?php

namespace App\Domains\Clan\Notifications;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Игрока позвали в клан: уведомление на сайте и письмо (если почта указана).
 */
class ClanInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Clan $clan, public ClanInvitation $invitation, public string $inviterName)
    {
    }

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Вас приглашают в клан [{$this->clan->tag}] {$this->clan->name}")
            ->greeting("Привет, {$notifiable->username}!")
            ->line("{$this->inviterName} зовёт вас вступить в клан «{$this->clan->name}» [{$this->clan->tag}].");

        if ($this->invitation->message) {
            $mail->line('«' . $this->invitation->message . '»');
        }

        return $mail
            ->action('Посмотреть приглашение', url('/clans'))
            ->line('Приглашение действует ' . ClanInvitation::TTL_DAYS . ' дней. Вступление по приглашению бесплатное.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'clan_invitation',
            'clan_id' => $this->clan->id,
            'invitation_id' => $this->invitation->id,
            'message' => "{$this->inviterName} приглашает вас в клан [{$this->clan->tag}] {$this->clan->name}",
        ];
    }
}
