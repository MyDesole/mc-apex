<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerificationCode;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{


    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $field = filter_var($validated['login'], FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'username';

        $credentials = [
            $field => $validated['login'],
            'password' => $validated['password'],
        ];

        if (!Auth::attempt($credentials, $validated['remember'] ?? false)) {
            throw ValidationException::withMessages([
                'login' => ['Неверный логин или пароль.'],
            ]);
        }

        $user = $request->user();

        if (!$user->hasVerifiedEmail()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'login' => ['Сначала подтвердите email. Проверьте почту.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Вход выполнен успешно.',
            'user' => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Вы вышли из аккаунта.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'aspectPvp',
            'aspectBedwars',
            'clanMember.clan',
            'achievements',
        ]);

        return response()->json([
            'user' => array_merge($user->toArray(), [
                'aspects' => [
                    'pvp' => $user->aspectPvp,
                    'bedwars' => $user->aspectBedwars,
                ],
            ]),
            'rank' => $this->getUserRank($user),
        ]);
    }

    /**
     * Место в общем топе по tier_score.
     */
    private function getUserRank(\App\Models\User $user): array
    {
        if ($user->tier_score <= 0) {
            return ['position' => null, 'total' => 0];
        }

        $position = \App\Models\User::where('tier_score', '>', $user->tier_score)->count() + 1;

        $total = \App\Models\User::where('tier_score', '>', 0)->count();

        return [
            'position' => $position,
            'total' => $total,
        ];
    }

    public function sendVerificationCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        EmailVerification::updateOrCreate(
            ['email' => $validated['email']],
            [
                'code' => $code,
                'expires_at' => now()->addMinutes(15),
                'verified_at' => null,
            ]
        );

        Mail::to($validated['email'])->send(new EmailVerificationCode($code));

        return response()->json([
            'message' => 'Код отправлен на ' . $validated['email'],
            'email' => $validated['email'],
        ]);
    }

    /**
     * Шаг 2: проверка кода.
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $verification = EmailVerification::where('email', $validated['email'])->first();

        if (!$verification || $verification->code !== $validated['code']) {
            throw ValidationException::withMessages([
                'code' => ['Неверный код подтверждения.'],
            ]);
        }

        if ($verification->isExpired()) {
            throw ValidationException::withMessages([
                'code' => ['Код истёк. Запросите новый.'],
            ]);
        }

        $verification->update(['verified_at' => now()]);

        // Выдаём одноразовый токен, чтобы нельзя было зарегистрироваться без верификации
        $token = Str::random(64);
        cache()->put('email_verified:' . $token, $validated['email'], now()->addMinutes(30));

        return response()->json([
            'message' => 'Email подтверждён.',
            'verification_token' => $token,
        ]);
    }

    /**
     * Шаг 3: регистрация — только с валидным verification_token.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => [
                'required', 'string', 'min:3', 'max:32',
                'regex:/^[a-zA-Z0-9_]+$/',
                'unique:users,username',
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'verification_token' => ['required', 'string'],
        ]);

        $email = cache()->pull('email_verified:' . $validated['verification_token']);

        if (!$email) {
            throw ValidationException::withMessages([
                'verification_token' => ['Сессия подтверждения email истекла. Начните заново.'],
            ]);
        }

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Этот email уже занят.'],
            ]);
        }

        $user = User::create([
            'username' => $validated['username'],
            'email' => $email,
            'password' => $validated['password'],
        ]);

        $user->markEmailAsVerified();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Аккаунт создан. Добро пожаловать!',
            'user' => $user,
        ], 201);
    }
}
