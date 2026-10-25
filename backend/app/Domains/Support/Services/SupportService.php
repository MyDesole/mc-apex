<?php

namespace App\Domains\Support\Services;

use App\Domains\Support\Models\SupportMessage;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Notifications\SupportNotification;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Техподдержка: обращения игроков и ответы персонала.
 *
 * Игрок пишет — тикет «ждёт ответа», персонал отвечает — «ответили»,
 * и игроку приходит уведомление. Лишних уведомлений не шлём: пока
 * прежнее по этому тикету не прочитано, новое не создаём.
 */
class SupportService
{
    /** Сколько открытых обращений может держать один игрок. */
    public const MAX_OPEN = 3;

    public function open(User $user, string $subject, string $category, string $body): SupportTicket
    {
        $active = SupportTicket::where('user_id', $user->id)
            ->where('status', '!=', SupportTicket::STATUS_CLOSED)
            ->count();

        abort_if($active >= self::MAX_OPEN, 422, 'У вас уже ' . self::MAX_OPEN . ' открытых обращения — дождитесь ответа или закройте решённые.');

        return DB::transaction(function () use ($user, $subject, $category, $body) {
            $ticket = SupportTicket::create([
                'user_id' => $user->id,
                'subject' => $subject,
                'category' => array_key_exists($category, SupportTicket::CATEGORIES) ? $category : 'other',
                'status' => SupportTicket::STATUS_OPEN,
                'last_message_at' => now(),
            ]);

            $ticket->messages()->create(['user_id' => $user->id, 'is_staff' => false, 'body' => $body]);

            return $ticket;
        });
    }

    /** Сообщение игрока: тикет снова ждёт ответа персонала. */
    public function playerReply(SupportTicket $ticket, User $user, string $body): SupportMessage
    {
        $message = $ticket->messages()->create(['user_id' => $user->id, 'is_staff' => false, 'body' => $body]);

        $ticket->update(['status' => SupportTicket::STATUS_OPEN, 'last_message_at' => now()]);

        // Персонал, который уже ведёт обращение, узнаёт об ответе игрока
        if ($ticket->assignee && $ticket->assignee->id !== $user->id) {
            $this->notifyOnce($ticket->assignee, 'support_player_reply', "{$user->username} ответил в обращении «" . Str::limit($ticket->subject, 50) . '»', $ticket);
        }

        return $message;
    }

    /** Ответ персонала: тикет ждёт игрока, игроку — уведомление. */
    public function staffReply(SupportTicket $ticket, User $staff, string $body): SupportMessage
    {
        $message = $ticket->messages()->create(['user_id' => $staff->id, 'is_staff' => true, 'body' => $body]);

        $ticket->update([
            'status' => SupportTicket::STATUS_ANSWERED,
            'assigned_to' => $ticket->assigned_to ?? $staff->id,
            'last_message_at' => now(),
        ]);

        $this->notifyOnce($ticket->user, 'support_reply', 'Поддержка ответила на обращение «' . Str::limit($ticket->subject, 50) . '»', $ticket);

        return $message;
    }

    public function close(SupportTicket $ticket): SupportTicket
    {
        $ticket->update(['status' => SupportTicket::STATUS_CLOSED]);

        return $ticket;
    }

    public function present(SupportTicket $ticket, bool $withMessages = false): array
    {
        $ticket->loadMissing('user:id,username,avatar', 'assignee:id,username');

        $data = [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'category_label' => SupportTicket::CATEGORIES[$ticket->category] ?? 'Другое',
            'status' => $ticket->status,
            'user' => $ticket->user ? ['id' => $ticket->user->id, 'username' => $ticket->user->username, 'avatar_url' => $ticket->user->avatar_url] : null,
            'assignee' => $ticket->assignee ? ['id' => $ticket->assignee->id, 'username' => $ticket->assignee->username] : null,
            'last_message_at' => $ticket->last_message_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
        ];

        if ($withMessages) {
            $data['messages'] = $ticket->messages()
                ->with('user:id,username,avatar,role')
                ->orderBy('id')
                ->get()
                ->map(fn (SupportMessage $m) => [
                    'id' => $m->id,
                    'body' => $m->body,
                    'is_staff' => $m->is_staff,
                    'user' => $m->user ? ['id' => $m->user->id, 'username' => $m->user->username, 'avatar_url' => $m->user->avatar_url] : null,
                    'created_at' => $m->created_at?->toIso8601String(),
                ])
                ->values();
        }

        return $data;
    }

    private function notifyOnce(?User $recipient, string $type, string $message, SupportTicket $ticket): void
    {
        if (! $recipient) {
            return;
        }

        $pending = $recipient->unreadNotifications()
            ->where('data->type', $type)
            ->where('data->ticket_id', $ticket->id)
            ->exists();

        if (! $pending) {
            $recipient->notify(new SupportNotification($type, $message, $ticket->id));
        }
    }
}
