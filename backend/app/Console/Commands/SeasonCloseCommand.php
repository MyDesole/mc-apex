<?php

namespace App\Console\Commands;

use App\Domains\Seasons\Services\SeasonService;
use Illuminate\Console\Command;

/**
 * Закрыть сезон: сохранить места игроков и открыть следующий.
 * Тиры и очки не сбрасываются.
 */
class SeasonCloseCommand extends Command
{
    protected $signature = 'apex:season-close
        {--name= : Название следующего сезона (по умолчанию «Сезон N»)}
        {--force : Не спрашивать подтверждение}';

    protected $description = 'Закрыть текущий сезон, сохранить итоги рейтинга и открыть следующий';

    public function handle(SeasonService $seasons): int
    {
        $current = $seasons->current();

        if (! $this->option('force') && ! $this->confirm("Закрыть «{$current->name}» и открыть следующий сезон?")) {
            return self::SUCCESS;
        }

        $result = $seasons->close($this->option('name'));

        $this->info("«{$result['closed']->name}» закрыт, сохранено мест: {$result['players']}.");
        $this->info("Открыт «{$result['next']->name}».");

        return self::SUCCESS;
    }
}
