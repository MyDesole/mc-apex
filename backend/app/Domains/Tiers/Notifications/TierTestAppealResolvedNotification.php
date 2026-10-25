<?php

namespace App\Domains\Tiers\Notifications;

use App\Domains\Tiers\Models\TierTestAppeal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TierTestAppealResolvedNotification extends Notification
{
    use Queueable;

    public function __construct(public TierTestAppeal $appeal) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $revised = $this->appeal->revisedTest;

        $message = $revised
            ? "Апелляция принята: новый результат — {$revised->result_tier} ({$revised->result_score}%)"
            : 'Апелляция отклонена, результат теста остаётся в силе';

        return [
            'type' => 'tier_test_appeal_resolved',
            'appeal_id' => $this->appeal->id,
            'tier_test_id' => $this->appeal->tier_test_id,
            'status' => $this->appeal->status,
            'resolution' => $this->appeal->resolution,
            'message' => $message,
        ];
    }
}
