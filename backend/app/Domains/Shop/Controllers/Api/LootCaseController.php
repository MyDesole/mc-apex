<?php

namespace App\Domains\Shop\Controllers\Api;

use App\Domains\Shop\Services\LootCaseService;
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
        try {
            return response()->json($cases->open($request->user(), $slug));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
