<?php

namespace App\Domains\Project\Services;

use App\Domains\Clan\Models\ClanWar;
use App\Domains\Project\Models\ChangelogEntry;
use App\Domains\Project\Models\RoadmapItem;
use App\Domains\Tournaments\Models\Tournament;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Чейнджлог и роадмап: подготовка для страниц и автоматический
 * прогресс целей (активные игроки, сыгранные КВ, проведённые турниры).
 */
class ProjectService
{
    /** Автоматические показатели для целей роадмапа. */
    public const METRICS = [
        'active_players' => 'Активные игроки (заходили за 30 дней)',
        'clan_wars' => 'Сыгранные клановые войны',
        'tournaments' => 'Проведённые турниры',
        'players' => 'Зарегистрированные игроки',
    ];

    /** Значения показателей: пересчитываем раз в 10 минут. */
    public function metrics(): array
    {
        return Cache::remember('project:metrics', 600, fn () => [
            'active_players' => User::query()->excludeStaff()->where('last_seen_at', '>=', now()->subDays(30))->count(),
            'clan_wars' => ClanWar::where('status', 'completed')->count(),
            'tournaments' => Tournament::where('status', 'completed')->count(),
            'players' => User::query()->excludeStaff()->count(),
        ]);
    }

    public function forgetMetrics(): void
    {
        Cache::forget('project:metrics');
    }

    public function presentRoadmapItem(RoadmapItem $item, ?array $metrics = null): array
    {
        $metrics ??= $this->metrics();

        $current = $item->metric && array_key_exists($item->metric, $metrics)
            ? $metrics[$item->metric]
            : $item->progress_current;

        $target = $item->progress_target;

        $percent = $item->status === 'done'
            ? 100
            : ($target ? min(100, (int) floor(($current ?? 0) / $target * 100)) : null);

        return [
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'status' => $item->status,
            'status_label' => RoadmapItem::STATUSES[$item->status] ?? $item->status,
            'metric' => $item->metric,
            'metric_label' => $item->metric ? (self::METRICS[$item->metric] ?? null) : null,
            'progress_current' => $current,
            'progress_manual' => $item->progress_current,
            'progress_target' => $target,
            'progress_percent' => $percent,
            'eta' => $item->eta,
            'sort_order' => $item->sort_order,
        ];
    }

    public function roadmap(): array
    {
        $metrics = $this->metrics();

        return RoadmapItem::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (RoadmapItem $item) => $this->presentRoadmapItem($item, $metrics))
            ->all();
    }

    public function presentEntry(ChangelogEntry $entry, bool $full = true): array
    {
        return [
            'id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'version' => $entry->version,
            'category' => $entry->category,
            'category_label' => ChangelogEntry::CATEGORIES[$entry->category] ?? $entry->category,
            'body' => $full ? $entry->body : Str::limit($entry->body, 280),
            'excerpt' => Str::limit(preg_replace('/\s+/u', ' ', strip_tags($entry->body)), 200),
            'is_published' => $entry->is_published,
            'published_at' => ($entry->published_at ?? $entry->created_at)?->toIso8601String(),
            'author' => $entry->author ? ['id' => $entry->author->id, 'username' => $entry->author->username] : null,
        ];
    }

    /** Уникальный слаг из заголовка: «Новые макеты профиля» → novye-makety-profilya. */
    public function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'update';
        $base = Str::limit($base, 160, '');
        $slug = $base;
        $i = 2;

        while (ChangelogEntry::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
