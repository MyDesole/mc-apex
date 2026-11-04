<?php

namespace App\Domains\Players\Controllers\Api;

use App\Domains\Players\Services\QuestService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Доска заданий питомцев. */
class QuestController extends Controller
{
    public function __construct(private readonly QuestService $quests)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->quests->board($request->user()));
    }

    public function claim(Request $request, string $key): JsonResponse
    {
        return $this->run(fn () => $this->quests->claim($request->user(), $key));
    }

    public function bonus(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->quests->claimBonus($request->user()));
    }

    private function run(callable $call): JsonResponse
    {
        try {
            return response()->json($call());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
