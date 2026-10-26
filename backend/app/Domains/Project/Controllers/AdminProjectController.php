<?php

namespace App\Domains\Project\Controllers;

use App\Domains\Moderation\Models\ModerationLog;
use App\Domains\Project\Models\ChangelogEntry;
use App\Domains\Project\Models\RoadmapItem;
use App\Domains\Project\Services\ProjectService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Админка: записи чейнджлога (в том числе черновики) и цели роадмапа.
 */
class AdminProjectController extends Controller
{
    public function __construct(private readonly ProjectService $project)
    {
    }

    /* ----------------------------- Чейнджлог ----------------------------- */

    public function changelog(): JsonResponse
    {
        $entries = ChangelogEntry::query()
            ->with('author:id,username')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json([
            'entries' => $entries->map(fn ($e) => $this->project->presentEntry($e))->values(),
            'categories' => ChangelogEntry::CATEGORIES,
        ]);
    }

    public function storeEntry(Request $request): JsonResponse
    {
        $data = $this->validateEntry($request);

        $entry = ChangelogEntry::create([
            ...$data,
            'slug' => $this->project->uniqueSlug($data['title']),
            'published_at' => $data['published_at'] ?? now(),
            'author_id' => $request->user()->id,
        ]);

        ModerationLog::record($request->user(), 'changelog.create', null, ['title' => $entry->title]);

        return response()->json(['entry' => $this->project->presentEntry($entry->load('author:id,username'))], 201);
    }

    public function updateEntry(Request $request, ChangelogEntry $entry): JsonResponse
    {
        $data = $this->validateEntry($request);

        // Ссылка уже могла уйти в Telegram — слаг меняем только по явной просьбе
        if ($request->boolean('regenerate_slug')) {
            $data['slug'] = $this->project->uniqueSlug($data['title'], $entry->id);
        }

        $entry->update($data);

        return response()->json(['entry' => $this->project->presentEntry($entry->fresh('author:id,username'))]);
    }

    public function destroyEntry(Request $request, ChangelogEntry $entry): JsonResponse
    {
        ModerationLog::record($request->user(), 'changelog.delete', null, ['title' => $entry->title]);
        $entry->delete();

        return response()->json(['ok' => true]);
    }

    private function validateEntry(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'version' => ['nullable', 'string', 'max:32'],
            'category' => ['required', Rule::in(array_keys(ChangelogEntry::CATEGORIES))],
            'body' => ['required', 'string', 'max:20000'],
            'is_published' => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ]);
    }

    /* ----------------------------- Роадмап ----------------------------- */

    public function roadmap(): JsonResponse
    {
        return response()->json([
            'items' => $this->project->roadmap(),
            'statuses' => RoadmapItem::STATUSES,
            'metrics' => ProjectService::METRICS,
        ]);
    }

    public function storeItem(Request $request): JsonResponse
    {
        $data = $this->validateItem($request);
        $data['sort_order'] ??= (int) RoadmapItem::max('sort_order') + 1;

        $item = RoadmapItem::create($data);

        return response()->json(['item' => $this->project->presentRoadmapItem($item)], 201);
    }

    public function updateItem(Request $request, RoadmapItem $item): JsonResponse
    {
        $item->update($this->validateItem($request));

        return response()->json(['item' => $this->project->presentRoadmapItem($item->fresh())]);
    }

    public function destroyItem(RoadmapItem $item): JsonResponse
    {
        $item->delete();

        return response()->json(['ok' => true]);
    }

    private function validateItem(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(array_keys(RoadmapItem::STATUSES))],
            'metric' => ['nullable', Rule::in(array_keys(ProjectService::METRICS))],
            'progress_current' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'progress_target' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'eta' => ['nullable', 'string', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);
    }
}
