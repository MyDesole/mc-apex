<?php

namespace App\Domains\Moderation\Services;

use App\Domains\Chat\Models\ConversationParticipant;
use App\Domains\Chat\Models\Message;
use App\Domains\Forum\Models\ForumReply;
use App\Domains\Forum\Models\ForumTopic;
use App\Domains\Moderation\Models\ModerationLog;
use App\Domains\Moderation\Models\Report;
use App\Domains\Moderation\Notifications\ModerationNotification;
use App\Domains\Users\Models\User;
use App\Support\Concerns\AbortsWithMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Жалобы: подача игроком и решение модератора.
 *
 * Решение по жалобе закрывает и остальные открытые жалобы на тот же
 * объект — модератору не нужно разбирать одно и то же десять раз.
 */
class ReportService
{
    use AbortsWithMessage;

    public function submit(User $reporter, string $type, int $id, string $reason, ?string $comment): Report
    {
        [$targetId, $snapshot] = $this->resolveSubject($reporter, $type, $id);

        if ($targetId === $reporter->id) {
            $this->abortUnprocessable('Нельзя пожаловаться на самого себя.');
        }

        $duplicate = Report::where('reporter_id', $reporter->id)
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->where('status', Report::STATUS_OPEN)
            ->exists();

        if ($duplicate) {
            $this->abortUnprocessable('Вы уже пожаловались — модераторы скоро рассмотрят жалобу.');
        }

        return Report::create([
            'reporter_id' => $reporter->id,
            'target_user_id' => $targetId,
            'subject_type' => $type,
            'subject_id' => $id,
            'reason' => $reason,
            'comment' => $comment,
            'snapshot' => $snapshot !== null ? Str::limit($snapshot, 2000) : null,
            'status' => Report::STATUS_OPEN,
        ]);
    }

    public function resolve(Report $report, User $moderator, string $action, ?string $note, ?int $banDays = null): Report
    {
        if ($report->status !== Report::STATUS_OPEN) {
            $this->abortUnprocessable('Жалоба уже рассмотрена.');
        }

        $target = $report->target;

        DB::transaction(function () use ($report, $moderator, $action, $note, $banDays, $target) {
            match ($action) {
                'ban' => $this->ban($report, $moderator, $target, $note, $banDays),
                'warn' => $this->warn($report, $moderator, $target, $note),
                'remove_content' => $this->removeContent($report, $moderator),
                default => null,
            };

            $status = $action === 'dismiss' ? Report::STATUS_DISMISSED : Report::STATUS_RESOLVED;

            // Закрываем все открытые жалобы на этот же объект одним решением
            $related = Report::where('subject_type', $report->subject_type)
                ->where('subject_id', $report->subject_id)
                ->where('status', Report::STATUS_OPEN)
                ->get();

            foreach ($related as $item) {
                $item->update([
                    'status' => $status,
                    'resolution' => $action,
                    'moderator_id' => $moderator->id,
                    'moderator_note' => $note,
                    'resolved_at' => now(),
                ]);

                $item->reporter?->notify(new ModerationNotification(
                    'report_resolved',
                    $status === Report::STATUS_DISMISSED
                        ? 'Жалоба рассмотрена: нарушений не найдено.'
                        : 'Жалоба рассмотрена: меры приняты. Спасибо, что помогаете APEX!',
                    ['report_id' => $item->id],
                ));
            }

            ModerationLog::record(
                $moderator,
                $status === Report::STATUS_DISMISSED ? 'report.dismiss' : 'report.resolve',
                $report->target_user_id,
                ['reports' => $related->count(), 'action' => $action, 'note' => $note],
                'report',
                $report->id,
            );
        });

        return $report->fresh(['reporter:id,username', 'target:id,username', 'moderator:id,username']);
    }

    /* ----------------------------- Решения ----------------------------- */

    private function ban(Report $report, User $moderator, ?User $target, ?string $note, ?int $days): void
    {
        if (! $target) {
            $this->abortUnprocessable('Игрок уже удалён.');
        }

        // Персонал банит только администратор, администратора — никто
        if ($target->isAdmin() || ($target->isStaff() && ! $moderator->isAdmin())) {
            $this->abortForbidden('Этого пользователя нельзя забанить.');
        }

        $reason = trim(Report::REASONS[$report->reason].($note ? ": {$note}" : ''));

        $target->update([
            'is_banned' => true,
            'ban_reason' => $reason,
            'banned_until' => $days ? now()->addDays($days) : null,
            'banned_by' => $moderator->id,
        ]);

        ModerationLog::record($moderator, 'user.ban', $target->id, [
            'reason' => $reason,
            'days' => $days,
            'report_id' => $report->id,
        ]);
    }

    private function warn(Report $report, User $moderator, ?User $target, ?string $note): void
    {
        if (! $target) {
            $this->abortUnprocessable('Игрок уже удалён.');
        }

        $message = 'Предупреждение от модерации: '.mb_strtolower(Report::REASONS[$report->reason])
            .($note ? ". {$note}" : '.').' Повторное нарушение может закончиться баном.';

        $target->notify(new ModerationNotification('moderation_warning', $message));

        ModerationLog::record($moderator, 'user.warn', $target->id, [
            'reason' => $report->reason,
            'note' => $note,
            'report_id' => $report->id,
        ]);
    }

    private function removeContent(Report $report, User $moderator): void
    {
        $removed = match ($report->subject_type) {
            'message' => (bool) Message::whereKey($report->subject_id)->delete(),
            'forum_topic' => (bool) ForumTopic::whereKey($report->subject_id)
                ->update(['deleted_at' => now(), 'deleted_by' => $moderator->id]),
            'forum_reply' => (bool) ForumReply::whereKey($report->subject_id)
                ->update(['deleted_at' => now(), 'deleted_by' => $moderator->id]),
            default => $this->abortUnprocessable('Профиль нельзя удалить — выберите бан или предупреждение.'),
        };

        ModerationLog::record(
            $moderator,
            'content.remove',
            $report->target_user_id,
            ['removed' => $removed, 'report_id' => $report->id],
            $report->subject_type,
            $report->subject_id,
        );
    }

    /* ----------------------------- Объект жалобы ----------------------------- */

    /**
     * @return array{0: ?int, 1: ?string} автор объекта и копия текста
     */
    private function resolveSubject(User $reporter, string $type, int $id): array
    {
        switch ($type) {
            case 'user':
                $user = User::find($id) ?? abort(404, 'Игрок не найден.');

                return [$user->id, $user->username];

            case 'message':
                $message = Message::find($id) ?? abort(404, 'Сообщение не найдено.');

                // Жаловаться можно только на сообщение из своего диалога
                $member = ConversationParticipant::where('conversation_id', $message->conversation_id)
                    ->where('user_id', $reporter->id)
                    ->exists();

                abort_unless($member, 403, 'Нет доступа к этому сообщению.');

                return [$message->user_id, $message->body];

            case 'forum_topic':
                $topic = ForumTopic::whereNull('deleted_at')->find($id) ?? abort(404, 'Тема не найдена.');

                return [$topic->author_id, $topic->title."\n\n".$topic->body];

            case 'forum_reply':
                $reply = ForumReply::whereNull('deleted_at')->find($id) ?? abort(404, 'Ответ не найден.');

                return [$reply->author_id, $reply->body];
        }

        abort(422, 'Неизвестный тип жалобы.');
    }
}
