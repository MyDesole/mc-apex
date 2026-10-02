<?php

namespace App\Domains\Auth\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Auth\Requests\Auth\LoginRequest;
use App\Domains\Auth\Requests\Auth\RegisterRequest;
use App\Domains\Auth\Requests\Auth\SendVerificationCodeRequest;
use App\Domains\Auth\Requests\Auth\VerifyCodeRequest;
use App\Domains\Auth\Mail\EmailVerificationCode;
use App\Domains\Auth\Services\AuthService;
use App\Domains\Auth\Services\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

/**
 * Авторизация. Контроллер тонкий: валидация — в FormRequest,
 * работа с сессией, почтой и наградами — в сервисах.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly EmailVerificationService $verification,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->auth->login(
            login: $request->string('login')->toString(),
            password: $request->string('password')->toString(),
            remember: $request->boolean('remember'),
        );

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
        return response()->json(
            $this->auth->me($request->user())
        );
    }

    /**
     * Шаг 1: отправить код на почту.
     */
    public function sendVerificationCode(SendVerificationCodeRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();

        $code = $this->verification->issueCode($email);

        Mail::to($email)->send(new EmailVerificationCode($code));

        return response()->json([
            'message' => 'Код отправлен на ' . $email,
            'email' => $email,
        ]);
    }

    /**
     * Шаг 2: проверить код и получить одноразовый токен.
     */
    public function verifyCode(VerifyCodeRequest $request): JsonResponse
    {
        $token = $this->verification->verifyCode(
            email: $request->string('email')->toString(),
            code: $request->string('code')->toString(),
        );

        return response()->json([
            'message' => 'Email подтверждён.',
            'verification_token' => $token,
        ]);
    }

    /**
     * Шаг 3: регистрация — только с валидным verification_token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->auth->register(
            username: $request->string('username')->toString(),
            password: $request->string('password')->toString(),
            verificationToken: $request->string('verification_token')->toString(),
            referralCode: $request->input('referral_code'),
        );

        $this->auth->loginAfterRegister($user);

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Аккаунт создан. Добро пожаловать!',
            'user' => $user,
        ], 201);
    }
}
