<?php

namespace App\Domains\Minecraft\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Одноразовый код привязки майнкрафт-игрока к аккаунту на сайте.
 *
 * Код показывается игроку в чате и живёт недолго. После привязки
 * удаляется: повторно им воспользоваться нельзя.
 */
class MinecraftLinkCode extends Model
{
    /** Сколько живёт код. */
    public const TTL_MINUTES = 15;

    /** Алфавит без похожих символов: 0/O, 1/I/L путаются при вводе. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Длина кода в символах. */
    public const LENGTH = 8;

    protected $fillable = ['uuid', 'username', 'code', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /** Новый код: 8 символов из безопасного алфавита. */
    public static function generate(): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    /**
     * Приводит введённый код к тому виду, в котором он хранится.
     *
     * Игрок может ввести его с дефисом, пробелами или в нижнем регистре.
     */
    public static function normalize(string $code): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($code)) ?? '';
    }

    /** Вид для показа игроку: XXXX-XXXX. */
    public static function format(string $code): string
    {
        $normalized = self::normalize($code);

        return implode('-', str_split($normalized, 4));
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
