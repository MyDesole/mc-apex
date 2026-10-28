<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Планировщик. На сервере его запускает cron раз в минуту (deploy/cron).
 * Только уборка: упавшие задачи очереди и протухшие токены не копятся вечно.
 */
Schedule::command('queue:prune-failed --hours=168')->daily()->at('03:30');
Schedule::command('sanctum:prune-expired --hours=24')->daily()->at('03:40');

// Аукцион питомцев: закрыть закончившиеся торги
Schedule::call(fn () => app(\App\Domains\Shop\Services\PetAuctionService::class)->closeExpired())
    ->everyMinute()
    ->name('pet-auctions:close')
    ->withoutOverlapping();

// Карта сайта для поисковиков — свежие новости и кланы
Schedule::command('sitemap:generate')->dailyAt('04:10');

// Награды тройке самых уважаемых за прошедшую неделю
Schedule::command('respects:weekly-rewards')->weeklyOn(1, '00:05');
