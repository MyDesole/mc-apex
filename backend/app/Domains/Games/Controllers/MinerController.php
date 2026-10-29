<?php

namespace App\Domains\Games\Controllers;

use App\Domains\Games\Services\MinerProgress;
use App\Domains\Games\Services\MinerService;
use App\Domains\Games\Support\MinerWorld;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Мини-игра «Шахтёр»: состояние, забег (удар, динамит, сундук, житель,
 * спуск, забрать монеты), улучшения и ежедневные задания.
 */
class MinerController extends Controller
{
    public function __construct(
        private readonly MinerService $miner,
        private readonly MinerProgress $progress,
    ) {
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
        $cell = $this->cell($request);

        return $this->run(fn () => $this->miner->dig($request->user(), $cell));
    }

    public function blast(Request $request): JsonResponse
    {
        $cell = $this->cell($request);

        return $this->run(fn () => $this->miner->blast($request->user(), $cell));
    }

    public function chest(Request $request): JsonResponse
    {
        $cell = $this->cell($request);

        return $this->run(fn () => $this->miner->openChest($request->user(), $cell));
    }

    public function trade(Request $request): JsonResponse
    {
        $cell = $this->cell($request);
        $offer = $request->validate(['offer' => ['required', Rule::in(array_keys(MinerWorld::TRADES))]])['offer'];

        return $this->run(fn () => $this->miner->trade($request->user(), $cell, $offer));
    }

    public function descend(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->miner->descend($request->user()));
    }

    public function cashOut(Request $request): JsonResponse
    {
        return $this->run(fn () => $this->miner->cashOut($request->user()));
    }

    public function upgrade(Request $request): JsonResponse
    {
        $key = $request->validate(['upgrade' => ['required', Rule::in(array_keys(MinerWorld::UPGRADES))]])['upgrade'];

        return $this->run(function () use ($request, $key) {
            $this->progress->upgrade($request->user(), $key);

            return $this->miner->state($request->user()) + ['balance' => (int) $request->user()->fresh()->apex_coins];
        });
    }

    public function claimQuest(Request $request, string $quest): JsonResponse
    {
        return $this->run(function () use ($request, $quest) {
            $reward = $this->progress->claim($request->user(), $quest);

            return $this->miner->state($request->user()) + [
                'reward' => $reward,
                'balance' => (int) $request->user()->fresh()->apex_coins,
            ];
        });
    }

    private function cell(Request $request): int
    {
        return (int) $request->validate(['cell' => ['required', 'integer', 'min:0', 'max:1000']])['cell'];
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
