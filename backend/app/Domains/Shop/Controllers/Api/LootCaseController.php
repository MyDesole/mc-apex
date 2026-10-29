<?php

namespace App\Domains\Shop\Controllers\Api;

use App\Domains\Shop\Exceptions\NotEnoughCoins;
use App\Domains\Shop\Services\LootCaseService;
use App\Domains\Shop\Support\LootCases;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Кейсы с косметикой: витрина и открытие.
 */
class LootCaseController extends Controller
{
    public function index(Request $request, LootCaseService $cases): JsonResponse
    {
        return response()->json($cases->catalog($request->user('sanctum')));
    }

    /** Для меню сайта: ждёт ли игрока бесплатный сундук. */
    public function status(Request $request, LootCaseService $cases): JsonResponse
    {
        $user = $request->user('sanctum');

        return response()->json(['daily_available' => $user ? $cases->dailyAvailable($user) : false]);
    }

    public function open(Request $request, string $slug, LootCaseService $cases): JsonResponse
    {
        $data = $request->validate(['count' => ['nullable', 'integer', 'in:' . implode(',', LootCases::BATCHES)]]);

        try {
            return response()->json($cases->openMany($request->user(), $slug, (int) ($data['count'] ?? 1)));
        } catch (NotEnoughCoins $e) {
            // Клиент предложит заработать: шахта, экспедиции питомцев
            return response()->json(['message' => $e->getMessage(), 'missing' => $e->missing, 'earn' => '/miner'], 422);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
