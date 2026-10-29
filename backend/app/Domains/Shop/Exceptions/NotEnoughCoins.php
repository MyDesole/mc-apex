<?php

namespace App\Domains\Shop\Exceptions;

use RuntimeException;

/**
 * Не хватает ApexCoin: сколько именно — чтобы клиент мог предложить,
 * где их заработать (шахта, экспедиции).
 */
class NotEnoughCoins extends RuntimeException
{
    public function __construct(public readonly int $missing)
    {
        parent::__construct('Недостаточно ApexCoin. Не хватает ' . number_format($missing, 0, ',', ' ') . '.');
    }
}
