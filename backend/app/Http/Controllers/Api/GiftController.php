<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CoinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftController extends Controller
{
    /**
     * Подарить ApexCoin другу.
     */
    public function store(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        try {
            [$received, $fee] = CoinService::gift($request->user(), $user, (int) $validated['amount']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $sender = $request->user()->fresh();

        return response()->json([
            'message' => $fee > 0
                ? "Подарено {$received} ApexCoin игроку {$user->username} (комиссия {$fee})."
                : "Подарено {$received} ApexCoin игроку {$user->username}.",
            'received' => $received,
            'fee' => $fee,
            'balance' => (int) $sender->apex_coins,
            'gifted_today' => CoinService::giftedToday($sender),
        ], 201);
    }

    /**
     * Сколько можно подарить сегодня и кому (друзья).
     */
    public function limits(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'balance' => (int) $user->apex_coins,
            'gifted_today' => CoinService::giftedToday($user),
            'limits' => config('apex.coins.gift'),
        ]);
    }
}
