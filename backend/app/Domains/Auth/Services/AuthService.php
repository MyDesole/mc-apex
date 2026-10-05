<?php

namespace App\Domains\Auth\Services;

use App\Domains\Users\Models\User;
use App\Support\Concerns\AbortsWithMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Domains\Players\Services\PlayerProfileService;
use App\Domains\Bridge\Services\BridgeService;
use App\Domains\Wallet\Services\RewardService;

/**
 * Аутентификация: вход, выход, регистрация.
 *
 * Сессионная схема (Sanctum SPA), поэтому работа с сессией — часть сервиса,
 * а контроллер только принимает запрос и отдаёт ответ.
 */
class AuthService
{
    use AbortsWithMessage;

    public function __construct(
        private readonly EmailVerificationService $verification,
    ) {
    }

    /**
     * Вход по логину (email или username) и паролю.
     */
    public function login(string $login, string $password, bool $remember = false): User
    {
        // Логином может быть и почта, и ник
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => $login, 'password' => $password], $remember)) {
            $this->invalid('login', 'Неверный логин или пароль.');
        }

        $user = Auth::user();

        // Забаненного не пускаем сразу: иначе он получал рабочую сессию,
        // а блокировка срабатывала только на следующих запросах.
        if ($user->isBanned()) {
            Auth::guard('web')->logout();

            $this->invalid('login', 'Ваш аккаунт забанен.');
        }

        // Неподтверждённую почту не пускаем: сессию сразу закрываем
        if (! $user->hasVerifiedEmail()) {
            Auth::guard('web')->logout();

            $this->invalid('login', 'Сначала подтвердите email. Проверьте почту.');
        }

        return $user;
    }

    /**
     * Регистрация. Требует токен, полученный после подтверждения email.
     */
    public function register(
        string $username,
        string $password,
        string $verificationToken,
        ?string $referralCode = null,
        string $profileMode = 'pvp',
    ): User {
        $email = $this->verification->consumeToken($verificationToken);

        if (User::where('email', $email)->exists()) {
            $this->invalid('email', 'Этот email уже занят.');
        }

        $referrer = $referralCode
            ? User::where('referral_code', $referralCode)->first()
            : null;

        $user = User::create([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'referral_code' => $this->generateReferralCode(),
            'referred_by' => $referrer?->id,
            // Бриджер сразу получает бридж-профиль
            'profile_mode' => $profileMode === 'bridge' ? 'bridge' : 'pvp',
        ]);

        if ($referrer) {
            // Награда пригласившему и приветственный бонус новичку
            RewardService::forReferral($referrer, $user);
            RewardService::welcomeBonus($user);
        }

        $user->markEmailAsVerified();

        return $user;
    }

    /**
     * Войти сразу после регистрации.
     */
    public function loginAfterRegister(User $user): void
    {
        Auth::guard('web')->login($user);
    }

    /**
     * Профиль текущего пользователя со всеми связями и местом в топе.
     */
    public function me(User $user): array
    {
        $user->load([
            'aspectPvp',
            'aspectBedwars',
            'clanMember.clan' => fn ($q) => $q->withCount('members'),
            'achievements',
            'tierTests',
            'friendsList',
            'friendsOf',
            'bridgeRank',
        ]);

        [$position, $total] = app(PlayerProfileService::class)->rankOf($user);

        $bridge = app(BridgeService::class);

        return [
            'user' => array_merge($user->toArray(), [
                'aspects' => [
                    'pvp' => $user->aspectPvp,
                    'bedwars' => $user->aspectBedwars,
                ],

                /*
                 * Сводка по бриджу: по ней страницы решают, показывать ли
                 * приглашение подтвердить вид, и что писать в шапке.
                 */
                'bridge' => [
                    'summary' => $bridge->summary($user),
                    'rank' => $user->bridgeRank
                        ? [
                            'id' => $user->bridgeRank->id,
                            'key' => $user->bridgeRank->key,
                            'label' => $user->bridgeRank->label,
                            'color' => $user->bridgeRank->color,
                        ]
                        : null,
                ],
            ]),
            'rank' => [
                'position' => $position,
                'total' => $total,
            ],
        ];
    }

    /**
     * Уникальный код приглашения.
     */
    public function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}
