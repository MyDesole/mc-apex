<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url') . '/reset-password';

        $url = $frontendUrl . '?' . http_build_query([
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

        return (new MailMessage)
            ->subject('Сброс пароля — APEX TIERS')
            ->greeting('Привет!')
            ->line('Ты запросил сброс пароля на APEX TIERS.')
            ->action('Сбросить пароль', $url)
            ->line('Ссылка действительна 60 минут.')
            ->line('Если ты не запрашивал сброс — просто проигнорируй это письмо.')
            ->salutation('С уважением, APEX TIERS');
    }
}
