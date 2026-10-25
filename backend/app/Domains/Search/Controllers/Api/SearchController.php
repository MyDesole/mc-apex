<?php

namespace App\Domains\Search\Controllers\Api;

use App\Domains\Search\Services\GlobalSearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Поиск по сайту для командной палитры.
 */
class SearchController extends Controller
{
    public function __construct(
        private readonly GlobalSearchService $search,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:64'],
        ]);

        return response()->json(
            $this->search->search((string) ($validated['q'] ?? ''))
        );
    }
}
