<?php

namespace App\Domains\Players\Controllers\Api;

use App\Domains\Players\Services\GuideService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Путеводитель по сайту: состояние, старт, шаги, свернуть панель.
 */
class GuideController extends Controller
{
    public function __construct(private readonly GuideService $guide)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->guide->state($request->user()));
    }

    public function start(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->guide->start($request->user()) + ['balance' => (int) $request->user()->fresh()->apex_coins]);
    }

    public function complete(Request $request, string $step): JsonResponse
    {
        return $this->run(fn () => $this->guide->complete($request->user(), $step) + ['balance' => (int) $request->user()->fresh()->apex_coins]);
    }

    public function skip(Request $request, string $step): JsonResponse
    {
        return $this->run(fn () => $this->guide->skip($request->user(), $step));
    }

    public function hide(Request $request): JsonResponse
    {
        $hidden = (bool) $request->validate(['hidden' => ['required', 'boolean']])['hidden'];

        return $this->run(fn () => $this->guide->hide($request->user(), $hidden));
    }

    private function run(callable $operation): JsonResponse
    {
        try {
            return response()->json($operation());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
