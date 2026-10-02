<?php

namespace App\Domains\Auth\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Auth\Requests\Auth\ForgotPasswordRequest;
use App\Domains\Auth\Requests\Auth\ResetPasswordRequest;
use App\Domains\Auth\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;

/**
 * Сброс пароля. Контроллер тонкий: валидация — в FormRequest,
 * код и сроки — в PasswordResetService.
 */
class PasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $passwords,
    ) {
    }

    /**
     * Шаг 1: запросить код. Ответ одинаковый и для неизвестного email,
     * чтобы не раскрывать существование аккаунта.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwords->sendCode($request->string('email')->toString());

        return response()->json([
            'message' => 'Если такой email зарегистрирован, мы отправили на него код.',
        ]);
    }

    /**
     * Шаг 2: проверить код и сменить пароль.
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwords->reset(
            email: $request->string('email')->toString(),
            code: $request->string('code')->toString(),
            password: $request->string('password')->toString(),
        );

        return response()->json([
            'message' => 'Пароль обновлён. Теперь можно войти.',
        ]);
    }
}
