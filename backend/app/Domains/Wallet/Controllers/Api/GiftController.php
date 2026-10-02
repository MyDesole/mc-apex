<?php

namespace App\Domains\Wallet\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Wallet\Requests\Wallet\GiftCoinsRequest;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Подарки ApexCoin. Логика — в CoinService, здесь только ответ.
 */
class GiftController extends Controller
{
    /**
     * Подарить ApexCoin игроку.
     */
    public function store(GiftCoinsRequest $request, User $user): JsonResponse
    {
        try {
            [$received, $fee] = CoinService::gift(
                $request->user(),
                $user,
                $request->integer('amount')
            );
        } catch (RuntimeException $e) {
            // Бизнес-правила подарка (не друг, лимит, не хватает монет) — это 422
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
     * Текущие лимиты подарков.
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
