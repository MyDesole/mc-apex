<?php

namespace App\Domains\Search\Services;

use App\Domains\Clan\Models\Clan;
use App\Domains\Forum\Models\ForumTopic;
use App\Domains\News\Models\News;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Поиск по всему сайту для командной палитры (Ctrl+K).
 *
 * Ищем подстрокой по нескольким сущностям сразу и отдаём короткие
 * карточки: палитре нужно только то, что помещается в одну строку.
 * Совпадения с начала строки поднимаем выше — так «des» сначала
 * находит «desole», а не «ultradestroyer».
 */
class GlobalSearchService
{
    public const MIN_LENGTH = 2;

    private const LIMITS = [
        'players' => 6,
        'clans' => 4,
        'topics' => 4,
        'news' => 3,
    ];

    /**
     * @return array{players: array, clans: array, topics: array, news: array}
     */
    public function search(string $query): array
    {
        $query = trim($query);

        if (mb_strlen($query) < self::MIN_LENGTH) {
            return ['players' => [], 'clans' => [], 'topics' => [], 'news' => []];
        }

        return [
            'players' => $this->players($query),
            'clans' => $this->clans($query),
            'topics' => $this->topics($query),
            'news' => $this->news($query),
        ];
    }

    private function players(string $query): array
    {
        $builder = User::query()
            ->where(fn (Builder $q) => $this->whereContains($q, ['username', 'minecraft_username'], $query))
            ->with('clanMember.clan:id,tag,banner_color');

        return $this->preferPrefix($builder, 'username', $query)
            ->orderByDesc('tier_score')
            ->limit(self::LIMITS['players'])
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'username' => $user->username,
                'minecraft_username' => $user->minecraft_username,
                'avatar_url' => $user->avatar_url,
                'tier' => $user->tier,
                'tier_score' => (int) $user->tier_score,
                'clan_tag' => $user->clan_tag,
                'clan_color' => $user->clan_color,
            ])
            ->all();
    }

    private function clans(string $query): array
    {
        $builder = Clan::query()
            ->where('is_banned', false)
            ->where(fn (Builder $q) => $this->whereContains($q, ['name', 'tag'], $query))
            ->withCount('members');

        return $this->preferPrefix($builder, 'name', $query)
            ->orderByDesc('power')
            ->limit(self::LIMITS['clans'])
            ->get()
            ->map(fn (Clan $clan) => [
                'id' => $clan->id,
                'name' => $clan->name,
                'tag' => $clan->tag,
                'avatar_url' => $clan->avatar_url,
                'banner_color' => $clan->banner_color,
                'power' => (int) $clan->power,
                'members_count' => (int) $clan->members_count,
            ])
            ->all();
    }

    private function topics(string $query): array
    {
        return ForumTopic::query()
            ->visible()
            ->where(fn (Builder $q) => $this->whereContains($q, ['title'], $query))
            ->with('category:id,name')
            ->orderByDesc('last_reply_at')
            ->limit(self::LIMITS['topics'])
            ->get(['id', 'category_id', 'title', 'slug', 'replies_count', 'last_reply_at'])
            ->map(fn (ForumTopic $topic) => [
                'id' => $topic->id,
                'title' => $topic->title,
                'slug' => $topic->slug,
                'category' => $topic->category?->name,
                'replies_count' => (int) $topic->replies_count,
            ])
            ->all();
    }

    private function news(string $query): array
    {
        return News::query()
            ->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn (Builder $q) => $this->whereContains($q, ['title'], $query))
            ->orderByDesc('published_at')
            ->limit(self::LIMITS['news'])
            ->get(['id', 'title', 'type', 'published_at', 'created_at'])
            ->map(fn (News $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'type' => $item->type,
                'published_at' => ($item->published_at ?? $item->created_at)?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Подстрока в любой из колонок.
     *
     * LIKE в SQLite не различает регистр только у латиницы, поэтому для
     * кириллицы дополнительно ищем варианты в нижнем регистре и с заглавной.
     */
    private function whereContains(Builder $q, array $columns, string $query): void
    {
        $variants = array_unique([
            $query,
            mb_strtolower($query),
            mb_convert_case($query, MB_CASE_TITLE),
        ]);

        foreach ($columns as $column) {
            foreach ($variants as $variant) {
                $q->orWhereRaw(
                    "{$column} LIKE ? ESCAPE '!'",
                    ['%' . $this->escapeLike($variant) . '%']
                );
            }
        }
    }

    private function preferPrefix(Builder $builder, string $column, string $query): Builder
    {
        return $builder->orderByRaw(
            "CASE WHEN LOWER({$column}) LIKE ? ESCAPE '!' THEN 0 ELSE 1 END",
            [mb_strtolower($this->escapeLike($query)) . '%']
        );
    }

    /**
     * Экранирование для LIKE через «!»: обратная косая черта
     * в SQLite и MySQL записывается по-разному, «!» — одинаково.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
