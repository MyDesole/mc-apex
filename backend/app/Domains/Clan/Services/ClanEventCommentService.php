<?php

namespace App\Domains\Clan\Services;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanEvent;
use App\Domains\Clan\Models\ClanEventComment;
use App\Domains\Users\Models\User;
use App\Support\Concerns\AbortsWithMessage;
use Illuminate\Support\Collection;

/**
 * Комментарии к событиям клана: список, создание, удаление.
 */
class ClanEventCommentService
{
    use AbortsWithMessage;

    public function list(Clan $clan, ClanEvent $event, User $user): Collection
    {
        $this->assertAccess($clan, $event, $user);

        return ClanEventComment::where('clan_event_id', $event->id)
            ->whereNull('parent_id')
            ->with([
                'user:id,username,avatar,tier',
                'replies.user:id,username,avatar,tier',
            ])
            ->latest()
            ->get();
    }

    public function create(Clan $clan, ClanEvent $event, User $user, array $data): ClanEventComment
    {
        $this->assertAccess($clan, $event, $user);

        $parentId = $data['parent_id'] ?? null;

        // Родитель обязан быть из этого же события
        if ($parentId) {
            $parent = ClanEventComment::find($parentId);

            if (! $parent || $parent->clan_event_id !== $event->id) {
                $this->abortUnprocessable('Родительский комментарий из другого ивента.');
            }
        }

        $comment = ClanEventComment::create([
            'clan_event_id' => $event->id,
            'user_id' => $user->id,
            'parent_id' => $parentId,
            'body' => trim($data['body']),
        ]);

        return $comment->load('user:id,username,avatar,tier');
    }

    public function delete(Clan $clan, ClanEvent $event, ClanEventComment $comment, User $user, ClanService $clans): void
    {
        abort_if($event->clan_id !== $clan->id, 404);
        abort_if($comment->clan_event_id !== $event->id, 404);

        // Удаляет автор, лидер или офицер. Проверку руководства берём
        // из ClanService — раньше здесь была своя копия запроса.
        $canDelete = $comment->user_id === $user->id
            || $clans->isStaff($clan, $user->id);

        abort_unless($canDelete, 403);

        $comment->delete();
    }

    /**
     * Событие принадлежит клану, а смотреть комментарии может только участник.
     */
    private function assertAccess(Clan $clan, ClanEvent $event, User $user): void
    {
        abort_if($event->clan_id !== $clan->id, 404);
        abort_unless($clan->isMember($user->id), 403, 'Комментарии доступны участникам клана.');
    }
}
