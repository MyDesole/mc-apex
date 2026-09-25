<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    /**
     * Шаг 1: запрос кода на email.
     */
    public function forgot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Не палим существование аккаунта — всегда отвечаем одинаково.
        if (!$user) {
            return response()->json([
                'message' => 'Если такой email зарегистрирован, мы отправили на него код.',
            ]);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token' => Hash::make($code),
                'created_at' => now(),
            ]
        );

        Mail::to($user->email)->send(new PasswordResetCode($code));

        return response()->json([
            'message' => 'Если такой email зарегистрирован, мы отправили на него код.',
        ]);
    }

    /**
     * Шаг 2: проверка кода + смена пароля.
     */
    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$record || !Hash::check($validated['code'], $record->token)) {
            throw ValidationException::withMessages([
                'code' => ['Неверный код.'],
            ]);
        }

        // Срок жизни кода — 15 минут
        if (now()->diffInMinutes($record->created_at) > 15) {
            throw ValidationException::withMessages([
                'code' => ['Код истёк. Запросите новый.'],
            ]);
        }

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Пользователь не найден.'],
            ]);
        }

        $user->update([
            'password' => $validated['password'], // каст 'hashed' сам захеширует
        ]);

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        return response()->json([
            'message' => 'Пароль обновлён. Теперь можно войти.',
        ]);
    }
}
