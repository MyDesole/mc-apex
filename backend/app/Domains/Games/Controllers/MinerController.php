<?php

namespace App\Domains\Games\Controllers;

use App\Domains\Games\Services\MinerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Мини-игра «Шахтёр»: состояние, начать забег, удар киркой, забрать монеты.
 */
class MinerController extends Controller
{
    public function __construct(private readonly MinerService $miner)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->miner->state($request->user()));
    }

    public function start(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->miner->start($request->user()));
    }

    public function dig(Request $request): JsonResponse
    {
        $data = $request->validate(['cell' => ['required', 'integer', 'min:0', 'max:1000']]);

        return $this->run(fn () => $this->miner->dig($request->user(), (int) $data['cell']));
    }

    public function cashOut(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->miner->cashOut($request->user()));
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
