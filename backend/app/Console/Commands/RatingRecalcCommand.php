<?php

namespace App\Console\Commands;

use App\Domains\Players\Services\RatingService;
use Illuminate\Console\Command;

/**
 * Пересчитать очки рейтинга всех игроков и силу всех кланов.
 * Нужен после миграции и как страховка, если события что-то пропустили.
 */
class RatingRecalcCommand extends Command
{
    protected $signature = 'apex:rating-recalc';

    protected $description = 'Пересчитать очки общего рейтинга игроков и силу кланов';

    public function handle(RatingService $rating): int
    {
        $count = $rating->recalculateAll();

        $this->info("Пересчитано игроков: {$count}. Сила кланов обновлена.");

        return self::SUCCESS;
    }
}
