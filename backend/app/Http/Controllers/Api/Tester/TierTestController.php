<?php

namespace App\Http\Controllers\Api\Tester;

use App\Http\Controllers\Controller;
use App\Http\Resources\TierTestResource;
use App\Http\Requests\Tester\CancelTierTestRequest;
use App\Http\Requests\Tester\CompleteTierTestRequest;
use App\Models\TierTest;
use App\Services\TierTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Тир-тесты со стороны тестера. Контроллер тонкий:
 * валидация — в FormRequest, очередь и расчёт тира — в TierTestService.
 */
class TierTestController extends Controller
{
    public function __construct(
        private readonly TierTestService $tierTests,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $queue = $this->tierTests->queue(
            filters: [
                'status' => $request->query('status'),
                'mode' => $request->query('mode'),
                'mine' => $request->query('mine') === '1',
                'free' => $request->query('free') === '1',
            ],
            tester: $request->user(),
        );

        // Ресурс применяем к элементам пагинатора, а не к пагинатору целиком:
        // иначе Laravel прячет current_page/per_page/total в meta и ломает контракт.
        $queue->setCollection(
            TierTestResource::collection($queue->getCollection())->collection
        );

        return response()->json($queue);
    }

    public function show(Request $request, TierTest $tierTest): JsonResponse
    {
        return response()->json($this->tierTests->find($tierTest));
    }

    public function claim(Request $request, TierTest $tierTest): JsonResponse
    {
        return response()->json([
            'tier_test' => new TierTestResource($this->tierTests->claim($tierTest, $request->user())),
        ]);
    }

    public function unclaim(Request $request, TierTest $tierTest): JsonResponse
    {
        $this->tierTests->unclaim($tierTest, $request->user());

        return response()->json(['ok' => true]);
    }

    public function complete(CompleteTierTestRequest $request, TierTest $tierTest): JsonResponse
    {
        $finished = $this->tierTests->complete(
            tierTest: $tierTest,
            tester: $request->user(),
            scores: $request->validated(),
        );

        return response()->json([
            'tier_test' => new TierTestResource($finished),
            'user' => $finished->user?->fresh(),
        ]);
    }

    public function cancel(CancelTierTestRequest $request, TierTest $tierTest): JsonResponse
    {
        $cancelled = $this->tierTests->cancel(
            tierTest: $tierTest,
            tester: $request->user(),
            reason: $request->input('reason'),
        );

        return response()->json(['tier_test' => new TierTestResource($cancelled)]);
    }

    public function stats(Request $request): JsonResponse
    {
        return response()->json($this->tierTests->stats($request->user()));
    }
}
