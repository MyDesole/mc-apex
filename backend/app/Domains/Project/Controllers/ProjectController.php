<?php

namespace App\Domains\Project\Controllers;

use App\Domains\Project\Models\ChangelogEntry;
use App\Domains\Project\Services\ProjectService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Публичные страницы: чейнджлог и роадмап.
 */
class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $project)
    {
    }

    public function changelog(): JsonResponse
    {
        $page = ChangelogEntry::query()
            ->published()
            ->with('author:id,username')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'data' => collect($page->items())->map(fn ($e) => $this->project->presentEntry($e))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function entry(string $slug): JsonResponse
    {
        $entry = ChangelogEntry::query()->published()->with('author:id,username')->where('slug', $slug)->firstOrFail();

        return response()->json(['entry' => $this->project->presentEntry($entry)]);
    }

    public function roadmap(): JsonResponse
    {
        return response()->json(['items' => $this->project->roadmap()]);
    }
}
