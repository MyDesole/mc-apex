<?php

namespace App\Services;

use App\Models\ForumAttachment;
use App\Models\ForumCategory;
use App\Models\ForumLike;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use App\Http\Resources\ForumTopicResource;
use App\Support\ForumPresenter;
use App\Support\ForumReplyTree;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Логика форума: разделы, темы, вложенные ответы, лайки, вложения.
 *
 * Контроллер принимает запрос и проверяет права, всё остальное — здесь.
 */
class ForumService
{
    /** Сколько тем показываем на страницу по умолчанию. */
    public const PER_PAGE = 20;

    /* ----------------------------- Разделы ----------------------------- */

    /**
     * Разделы со счётчиками и последней темой.
     */
    public function categories(?User $me): array
    {
        $categories = ForumCategory::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $lastTopics = ForumTopic::query()
            ->visible()
            ->with(['author:id,username,avatar', 'lastReplyUser:id,username'])
            ->orderByDesc('last_reply_at')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('category_id')
            ->map(fn ($topics) => $topics->first());

        return [
            'categories' => $categories->map(
                fn (ForumCategory $category) => ForumPresenter::category(
                    $category,
                    $me,
                    $lastTopics->get($category->id)
                )
            ),
            'stats' => $this->stats(),
        ];
    }

    private function stats(): array
    {
        $latest = ForumTopic::visible()
            ->with(['author:id,username,avatar', 'category:id,slug,name,color'])
            ->orderByDesc('created_at')
            ->first();

        return [
            'topics' => ForumTopic::visible()->count(),
            'replies' => ForumReply::visible()->count(),
            'players' => User::count(),
            'latest_topic' => $latest ? [
                'id' => $latest->id,
                'title' => $latest->title,
                'category' => $latest->category?->name,
                'author' => $latest->author?->username,
                'created_at' => $latest->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    /* ----------------------------- Темы ----------------------------- */

    /**
     * Список тем с фильтрами, сортировкой и пагинацией.
     */
    public function topics(array $filters): LengthAwarePaginator
    {
        $query = ForumTopic::query()
            ->visible()
            ->with([
                'author:id,username,avatar,tier,role,is_verified',
                'category:id,slug,name,color,icon',
                'lastReplyUser:id,username',
            ]);

        if (! empty($filters['category'])) {
            $category = ForumCategory::where('slug', $filters['category'])->firstOrFail();
            $query->where('category_id', $category->id);
        }

        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('body', 'like', $term));
        }

        match ($filters['sort'] ?? 'activity') {
            'new' => $query->orderByDesc('created_at'),
            'popular' => $query->orderByDesc('likes_count')->orderByDesc('views'),
            'unanswered' => $query->where('replies_count', 0)->orderByDesc('created_at'),
            default => $query->orderByDesc('last_reply_at')->orderByDesc('created_at'),
        };

        $query->orderByDesc('is_pinned');

        $topics = $query->paginate($filters['per_page'] ?? self::PER_PAGE);

        // Отдаём через ресурс: карточка темы читает плоские поля автора
        // и категории, которых у модели нет
        $topics->setCollection(
            ForumTopicResource::collection($topics->getCollection())->collection
        );

        return $topics;
    }

    /**
     * Создать тему, при необходимости сразу с вложениями.
     */
    public function createTopic(User $author, int $categoryId, string $title, string $body, array $attachmentIds = []): ForumTopic
    {
        $category = ForumCategory::findOrFail($categoryId);

        if (! $category->is_active) {
            throw ValidationException::withMessages([
                'category_id' => ['Раздел закрыт.'],
            ]);
        }

        // Нет права писать в раздел — это отказ в доступе (403), а не ошибка данных
        abort_unless(
            $category->allowsPosting($author),
            403,
            'В этом разделе нельзя создавать темы.'
        );

        $topic = ForumTopic::create([
            'category_id' => $category->id,
            'author_id' => $author->id,
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::lower(Str::random(5)),
            'body' => $body,
            'last_reply_at' => now(),
            'last_reply_user_id' => $author->id,
        ]);

        ForumAttachment::attach($author, 'topic', $topic->id, $attachmentIds);

        return $topic->load(['author', 'category', 'attachments']);
    }

    /**
     * Тема с деревом ответов. Просмотр засчитывается раз в сутки с одного IP.
     */
    public function showTopic(ForumTopic $topic, ?User $me, ?string $ip): array
    {
        if ($topic->isDeleted()) {
            abort(404);
        }

        $this->trackView($topic, $ip);

        $topic->load(['author', 'category', 'attachments']);

        $replies = ForumReply::query()
            ->where('topic_id', $topic->id)
            ->visible()
            ->with(['author', 'attachments'])
            ->orderBy('created_at')
            ->get();

        return [
            'topic' => (new ForumTopicResource($topic))->resolve(),
            'replies' => ForumReplyTree::build($replies, $me),
            'replies_count' => $replies->count(),
            'max_depth' => ForumReply::MAX_DEPTH,
            'can_reply' => ! $topic->is_locked,
        ];
    }

    /**
     * Ответ в теме, в том числе ответ на ответ.
     */
    public function addReply(
        ForumTopic $topic,
        User $author,
        string $body,
        ?int $parentId = null,
        array $attachmentIds = [],
    ): ForumReply {
        if ($topic->isDeleted()) {
            abort(404);
        }

        if ($topic->is_locked) {
            throw ValidationException::withMessages([
                'body' => ['Тема закрыта — отвечать нельзя.'],
            ]);
        }

        if ($parentId) {
            $this->assertValidParent($topic, $parentId);
        }

        $reply = ForumReply::create([
            'topic_id' => $topic->id,
            'author_id' => $author->id,
            'parent_id' => $parentId,
            'body' => $body,
        ]);

        ForumAttachment::attach($author, 'reply', $reply->id, $attachmentIds);

        $topic->update([
            'replies_count' => ForumReply::where('topic_id', $topic->id)->visible()->count(),
            'last_reply_at' => now(),
            'last_reply_user_id' => $author->id,
        ]);

        return $reply->load(['author', 'attachments']);
    }

    /**
     * Автор может править своё только в течение суток; модератор — всегда.
     * Это бизнес-правило, поэтому 422, а не 403.
     */
    public function assertWithinEditWindow(
        int $authorId,
        ?\Illuminate\Support\Carbon $createdAt,
        \App\Models\User $user,
        string $what = 'сообщение',
    ): void {
        if ($authorId !== $user->id || $user->isModerator()) {
            return;
        }

        abort_if(
            $createdAt && $createdAt->diffInHours(now()) > 24,
            422,
            "Редактировать {$what} можно в течение 24 часов."
        );
    }

    /**
     * Обновление темы. Права и срок давности проверяет политика.
     */
    public function updateTopic(ForumTopic $topic, array $attributes): ForumTopic
    {
        $topic->update($attributes);

        return $topic->fresh()->load(['author', 'category', 'attachments']);
    }

    /**
     * Ответ: правка только своего (модератор — любой) в течение суток.
     */
    public function updateReply(ForumReply $reply, string $body): ForumReply
    {
        $reply->update([
            'body' => $body,
            'edited_at' => now(),
        ]);

        return $reply->fresh()->load(['author', 'attachments']);
    }

    public function deleteTopic(ForumTopic $topic, User $user): void
    {
        $topic->update([
            'deleted_by' => $user->id,
            'deleted_at' => now(),
        ]);
    }

    /**
     * Удаление ответа вместе со всей веткой: иначе ветка осталась бы
     * без корня и повисла в дереве.
     *
     * @return int сколько сообщений скрыто
     */
    public function deleteReply(ForumReply $reply, User $user): int
    {
        $ids = $reply->descendantIds();
        $ids[] = $reply->id;

        ForumReply::whereIn('id', $ids)->update([
            'deleted_by' => $user->id,
            'deleted_at' => now(),
        ]);

        $topic = $reply->topic;

        if ($topic) {
            $topic->update([
                'replies_count' => ForumReply::where('topic_id', $topic->id)->visible()->count(),
            ]);
        }

        return count($ids);
    }

    /**
     * Переключить лайк темы или ответа.
     *
     * @return array{liked: bool, count: int}
     */
    public function toggleLike(User $user, string $type, int $id): array
    {
        $likeable = $type === 'topic'
            ? ForumTopic::visible()->findOrFail($id)
            : ForumReply::visible()->findOrFail($id);

        return ForumLike::toggle($user, $type, $id, $likeable);
    }

    /* ----------------------------- Вложения ----------------------------- */

    /**
     * Загрузить файл «про запас»: id потом передаётся при создании темы/ответа.
     */
    public function uploadAttachment(User $user, UploadedFile $file): ForumAttachment
    {
        $path = $file->store('forum/' . now()->format('Y/m'), 'public');

        $mime = $file->getClientMimeType() ?: $file->getMimeType();

        return ForumAttachment::create([
            'user_id' => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $mime,
            'size' => $file->getSize(),
            'is_image' => str_starts_with((string) $mime, 'image/'),
        ]);
    }

    /* ----------------------------- Поиск ----------------------------- */

    /**
     * Поиск по темам и ответам.
     */
    public function search(string $query): array
    {
        $term = '%' . $query . '%';

        return [
            'topics' => ForumTopic::visible()
                ->with(['author:id,username', 'category:id,slug,name,color'])
                ->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('body', 'like', $term))
                ->orderByDesc('last_reply_at')
                ->limit(20)
                ->get(),
            'replies' => ForumReply::visible()
                ->with(['author:id,username', 'topic:id,title,category_id'])
                ->where('body', 'like', $term)
                ->latest()
                ->limit(20)
                ->get(),
        ];
    }

    /* ----------------------------- Внутреннее ----------------------------- */

    /**
     * Родитель обязан быть из этой же темы, а вложенность — не выше предела.
     */
    private function assertValidParent(ForumTopic $topic, int $parentId): void
    {
        $parent = ForumReply::find($parentId);

        if (! $parent || $parent->topic_id !== $topic->id) {
            throw ValidationException::withMessages([
                'parent_id' => ['Можно отвечать только на сообщения из этой темы.'],
            ]);
        }

        // Уровни считаются от 0, поэтому ответ на глубине MAX_DEPTH уже не помещается
        if ($parent->depth() + 1 >= ForumReply::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'parent_id' => ['Достигнута максимальная вложенность — ответь в начало ветки.'],
            ]);
        }
    }

    /**
     * Просмотр засчитываем один раз в сутки с одного IP.
     * Через кэш, а не сессию: у публичного API-роута сессии нет.
     */
    private function trackView(ForumTopic $topic, ?string $ip): void
    {
        $key = 'forum_viewed:' . $topic->id . ':' . sha1((string) $ip);

        if (! Cache::has($key)) {
            $topic->increment('views');
            Cache::put($key, true, now()->addDay());
        }
    }
}
