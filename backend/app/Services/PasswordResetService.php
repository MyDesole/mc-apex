<?php

namespace App\Services;

use App\Mail\PasswordResetCode;
use App\Models\User;
use App\Services\Concerns\AbortsWithMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Сброс пароля по коду из письма.
 *
 * Код живёт 15 минут и хранится в password_reset_tokens в хешированном виде.
 */
class PasswordResetService
{
    use AbortsWithMessage;

    private const CODE_TTL_MINUTES = 15;

    private const TABLE = 'password_reset_tokens';

    /**
     * Запросить код. Существование аккаунта не раскрываем:
     * ответ одинаковый в обоих случаях.
     */
    public function sendCode(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table(self::TABLE)->updateOrInsert(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token' => Hash::make($code),
                'created_at' => now(),
            ]
        );

        Mail::to($user->email)->send(new PasswordResetCode($code));
    }

    /**
     * Проверить код и сменить пароль.
     */
    public function reset(string $email, string $code, string $password): void
    {
        $record = DB::table(self::TABLE)->where('email', $email)->first();

        if (! $record || ! Hash::check($code, $record->token)) {
            $this->invalid('code', 'Неверный код.');
        }

        // Сравниваем метки времени, а не diffInMinutes: в Carbon 3 разница
        // знаковая, из-за чего условие «> 15» никогда не срабатывало и
        // просроченный код принимался.
        if (now()->subMinutes(self::CODE_TTL_MINUTES)->greaterThan($record->created_at)) {
            $this->invalid('code', 'Код истёк. Запросите новый.');
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->invalid('email', 'Пользователь не найден.');
        }

        // Каст 'hashed' сам захеширует значение
        $user->update(['password' => $password]);

        DB::table(self::TABLE)->where('email', $user->email)->delete();
    }
}
