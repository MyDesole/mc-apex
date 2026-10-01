<?php

namespace App\Services;

use App\Models\EmailVerification;
use App\Models\User;
use App\Services\Concerns\AbortsWithMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Подтверждение email кодом.
 *
 * Три шага: запросить код → проверить код → получить одноразовый токен,
 * с которым уже можно регистрироваться.
 */
class EmailVerificationService
{
    use AbortsWithMessage;

    /** Сколько живёт код. */
    private const CODE_TTL_MINUTES = 15;

    /** Сколько живёт токен подтверждения после ввода кода. */
    private const TOKEN_TTL_MINUTES = 30;

    private const CACHE_PREFIX = 'email_verified:';

    /**
     * Создать код и «отправить» его на почту.
     * Возвращает код, чтобы вызывающий мог отправить письмо.
     */
    public function issueCode(string $email): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        EmailVerification::updateOrCreate(
            ['email' => $email],
            [
                'code' => $code,
                'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
                'verified_at' => null,
            ]
        );

        return $code;
    }

    /**
     * Проверить код и выдать одноразовый токен подтверждения.
     */
    public function verifyCode(string $email, string $code): string
    {
        $verification = EmailVerification::where('email', $email)->first();

        if (! $verification || $verification->code !== $code) {
            $this->invalid('code', 'Неверный код подтверждения.');
        }

        if ($verification->isExpired()) {
            $this->invalid('code', 'Код истёк. Запросите новый.');
        }

        $verification->update(['verified_at' => now()]);

        $token = Str::random(64);

        Cache::put(
            self::CACHE_PREFIX . $token,
            $email,
            now()->addMinutes(self::TOKEN_TTL_MINUTES)
        );

        return $token;
    }

    /**
     * Обменять токен на подтверждённый email. Токен одноразовый.
     */
    public function consumeToken(string $token): string
    {
        $email = Cache::pull(self::CACHE_PREFIX . $token);

        if (! $email) {
            $this->invalid('verification_token', 'Сессия подтверждения email истекла. Начните заново.');
        }

        return $email;
    }
}
