<?php

namespace App\Domains\Bridge\Notifications;

use App\Domains\Bridge\Models\UserBridgeTechnique;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Тестер проверил вид бриджа: подтвердил или отклонил.
 */
class BridgeTechniqueReviewedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public UserBridgeTechnique $submission,
        public bool $confirmed,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = $this->submission->technique?->label ?? 'вид бриджа';

        return [
            'type' => 'bridge_technique_reviewed',
            'bridge_submission_id' => $this->submission->id,
            'technique' => $label,
            'confirmed' => $this->confirmed,
            'score' => $this->submission->score,
            'message' => $this->confirmed
                ? "Вид бриджа «{$label}» подтверждён: {$this->submission->score}/10"
                : "Вид бриджа «{$label}» отклонён",
        ];
    }
}
