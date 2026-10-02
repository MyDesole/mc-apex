<?php

namespace App\Domains\Tiers\Notifications;

use App\Domains\Tiers\Models\TierTest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TierTestCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(public TierTest $tierTest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'tier_test_completed',
            'tier_test_id' => $this->tierTest->id,
            'tier' => $this->tierTest->result_tier,
            'score' => $this->tierTest->result_score,
            'message' => "Ваш тир-тест завершён: {$this->tierTest->result_tier} ({$this->tierTest->result_score}%)",
        ];
    }
}
