<?php

namespace App\Domains\Wallet\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * Уникальный код приглашения.
     */
    private static function generateReferralCode(): string
    {
        do {
            $code = strtoupper(\Illuminate\Support\Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Баланс и сводка по источникам начислений.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Код приглашения: генерируем при первом обращении
        if (! $user->referral_code) {
            $user->forceFill(['referral_code' => self::generateReferralCode()])->save();
        }

        $invited = User::where('referred_by', $user->id)->count();

        return response()->json([
            'referral' => [
                'code' => $user->referral_code,
                'link' => rtrim((string) config('app.frontend_url'), '/') . '/register?ref=' . $user->referral_code,
                'invited_count' => $invited,
                'reward_per_invite' => (int) (\App\Domains\Shop\Services\ShopSettingService::getInt(
                    'referral.amount',
                    (int) config('apex.coins.referral.amount')
                )),
                'invited_users' => User::where('referred_by', $user->id)
                    ->latest()
                    ->limit(10)
                    ->get(['id', 'username', 'avatar', 'created_at'])
                    ->map(fn ($u) => [
                        'id' => $u->id,
                        'username' => $u->username,
                        'avatar_url' => $u->avatar_url,
                        'joined_at' => $u->created_at?->toIso8601String(),
                    ]),
            ],
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
