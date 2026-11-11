<?php

namespace App\Domains\Tiers\Notifications;

use App\Domains\Tiers\Models\TierTest;
use App\Domains\Tiers\Models\TierTestMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Новое сообщение в чате тир-теста.
 *
 * В колокольчик сайта приходит всегда, на почту — не чаще раза в
 * MAIL_COOLDOWN_MINUTES на пару «заявка — получатель»: активная переписка
 * не должна засыпать ящик письмом на каждую реплику.
 */
class TierTestMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const MAIL_COOLDOWN_MINUTES = 10;

    public function __construct(
        public TierTest $tierTest,
        public TierTestMessage $message,
        public string $senderName,
        public bool $toTester,
    ) {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->email && $this->claimMailSlot($notifiable->id)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** Берёт «слот» письма: true, если за последние минуты письмо уже не уходило. */
    private function claimMailSlot(int $recipientId): bool
    {
        return Cache::add(
            "tier-chat-mail:{$this->tierTest->id}:{$recipientId}",
            1,
            now()->addMinutes(self::MAIL_COOLDOWN_MINUTES),
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $where = $this->toTester ? 'в панели тестера, вкладка «Мои в работе»' : 'на сайте — в окне чата тир-теста';

        return (new MailMessage)
            ->subject("Новое сообщение по тир-тесту от {$this->senderName}")
            ->greeting("Привет, {$notifiable->username}!")
            ->line("{$this->senderName} написал(а) вам в чате тир-теста:")
            ->line('«' . Str::limit($this->message->body, 200) . '»')
            ->action('Открыть чат', url($this->toTester ? '/tester' : '/profile'))
            ->line("Ответить можно {$where}.")
            ->line('Если вы не ждёте тир-тест, просто проигнорируйте письмо.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'tier_test_message',
            'tier_test_id' => $this->tierTest->id,
            'message_id' => $this->message->id,
            'from' => $this->senderName,
            'message' => "{$this->senderName} написал(а) в чате тир-теста: " . Str::limit($this->message->body, 80),
        ];
    }
}
