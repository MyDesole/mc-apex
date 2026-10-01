<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CoinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * Баланс и сводка по источникам начислений.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'balance' => (int) $user->apex_coins,
            'spent' => (int) $user->apex_coins_spent,
            'earned' => (int) $user->apex_coins + (int) $user->apex_coins_spent,
            'earned_by_source' => CoinService::earnedBySource($user),
            'gifted_today' => CoinService::giftedToday($user),
            'daily_bonus_available' => CoinService::dailyBonusAvailable($user),
            'daily_bonus_amount' => (int) config('apex.coins.daily_bonus.amount'),
            'gift_limits' => config('apex.coins.gift'),
            'currency' => config('apex.currency'),
        ]);
    }

    /**
     * История операций (леджер).
     */
    public function transactions(Request $request): JsonResponse
    {
        $perPage = min(100, max(5, (int) $request->query('per_page', 30)));

        return response()->json(CoinService::history($request->user(), $perPage));
    }

    /**
     * Ежедневный бонус за вход.
     */
    public function claimDaily(Request $request): JsonResponse
    {
        $user = $request->user();
        $amount = CoinService::claimDailyBonus($user);

        if ($amount === 0) {
            return response()->json([
                'message' => 'Ежедневный бонус уже получен. Возвращайтесь завтра!',
                'balance' => (int) $user->fresh()->apex_coins,
            ], 422);
        }

        return response()->json([
            'message' => "Получено {$amount} ApexCoin за ежедневный вход.",
            'amount' => $amount,
            'balance' => (int) $user->fresh()->apex_coins,
        ]);
    }
}
