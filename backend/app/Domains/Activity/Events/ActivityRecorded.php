<?php

namespace App\Domains\Activity\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Новое событие в ленте — для всех, кто смотрит главную.
 *
 * Канал публичный: лента открыта и гостям, а в событии нет ничего,
 * чего нет на страницах сайта.
 */
class ActivityRecorded implements ShouldBroadcastNow
{
    use Dispatchable;

    public const CHANNEL = 'activity';

    /**
     * @param  array<string, mixed>  $activity  событие в виде ActivityResource
     */
    public function __construct(
        public array $activity,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new Channel(self::CHANNEL)];
    }

    public function broadcastAs(): string
    {
        return 'activity.recorded';
    }

    public function broadcastWith(): array
    {
        return ['activity' => $this->activity];
    }
}
