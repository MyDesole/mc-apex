<?php

namespace App\Domains\Clan\Services;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanForumReply;
use App\Domains\Clan\Models\ClanForumTopic;
use App\Domains\Users\Models\User;
use App\Support\Concerns\AbortsWithMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Форум внутри клана: темы, ответы, закрепление.
 */
class ClanForumService
{
    use AbortsWithMessage;

    public const PER_PAGE = 20;

    public function topics(Clan $clan): LengthAwarePaginator
    {
        return ClanForumTopic::where('clan_id', $clan->id)
            ->with(['author:id,username,avatar', 'lastReplyUser:id,username'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_reply_at')
            ->paginate(self::PER_PAGE);
    }

    public function createTopic(Clan $clan, User $author, array $data): ClanForumTopic
    {
        $topic = ClanForumTopic::create([
            'clan_id' => $clan->id,
            'author_id' => $author->id,
            'title' => $data['title'],
            'body' => $data['body'],
        ]);

        return $topic->load('author:id,username,avatar');
    }

    public function showTopic(Clan $clan, ClanForumTopic $topic): ClanForumTopic
    {
        $this->assertBelongsToClan($topic, $clan);

        $topic->increment('views');

        return $topic->load([
            'author:id,username,avatar',
            'replies.author:id,username,avatar',
        ]);
    }

    public function addReply(Clan $clan, ClanForumTopic $topic, User $author, array $data): ClanForumReply
    {
        $this->assertBelongsToClan($topic, $clan);

        if ($topic->is_locked) {
            $this->abortUnprocessable('Топик закрыт.');
        }

        $parentId = $data['parent_id'] ?? null;

        // Родитель обязан быть из этой же темы: раньше проверялось только
        // существование записи, и ответ мог ссылаться на чужой топик.
        if ($parentId) {
            $parent = ClanForumReply::find($parentId);

            if (! $parent || $parent->topic_id !== $topic->id) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Можно отвечать только на сообщения из этой темы.'],
                ]);
            }
        }

        $reply = ClanForumReply::create([
            'topic_id' => $topic->id,
            'author_id' => $author->id,
            'parent_id' => $parentId,
            'body' => $data['body'],
        ]);

        $topic->update([
            'replies_count' => $topic->replies()->count(),
            'last_reply_at' => now(),
            'last_reply_user_id' => $author->id,
        ]);

        return $reply->load('author:id,username,avatar');
    }

    public function togglePin(Clan $clan, ClanForumTopic $topic): ClanForumTopic
    {
        $this->assertBelongsToClan($topic, $clan);

        $topic->update(['is_pinned' => ! $topic->is_pinned]);

        return $topic->fresh();
    }

    public function toggleLock(Clan $clan, ClanForumTopic $topic): ClanForumTopic
    {
        $this->assertBelongsToClan($topic, $clan);

        $topic->update(['is_locked' => ! $topic->is_locked]);

        return $topic->fresh();
    }

    public function deleteTopic(Clan $clan, ClanForumTopic $topic): void
    {
        $this->assertBelongsToClan($topic, $clan);

        $topic->delete();
    }

    /**
     * Тема обязана принадлежать клану из контекста запроса.
     */
    public function assertBelongsToClan(ClanForumTopic $topic, Clan $clan): void
    {
        abort_if($topic->clan_id !== $clan->id, 404);
    }
}
