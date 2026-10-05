<?php

namespace App\Domains\Bridge\Notifications;

use App\Domains\Bridge\Models\UserBridgeTechnique;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Новая заявка на вид бриджа: уведомляем бридж-тестеров и админов.
 *
 * Без этого заявку нечем найти: раздела проверки нет в общем меню, туда
 * попадают из уведомления.
 */
class BridgeSubmissionNotification extends Notification
{
    use Queueable;

    public function __construct(public UserBridgeTechnique $submission)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->submission->loadMissing('technique', 'user');

        $label = $this->submission->technique?->label ?? 'вид бриджа';
        $username = $this->submission->user?->username ?? 'Игрок';

        return [
            'type' => 'bridge_submission',
            'bridge_submission_id' => $this->submission->id,
            'technique' => $label,
            'username' => $username,
            'message' => "{$username} прислал видео на вид «{$label}»",
        ];
    }
}
