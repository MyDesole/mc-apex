<?php

namespace App\Domains\Shop\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Что-то изменилось на аукционе: ставка, снятие лота, сообщение в чате.
 *
 * Каналы публичные: аукцион и чат лота открыты и гостям. Лот уходит
 * в общий канал «auctions» (список), сообщения — в канал своего лота.
 */
class AuctionChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  string  $type  lot | message | message_deleted
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $auctionId,
        public string $type,
        public array $payload,
    ) {
    }

    public function broadcastOn(): array
    {
        return $this->type === 'lot'
            ? [new Channel('auctions'), new Channel("auction.{$this->auctionId}")]
            : [new Channel("auction.{$this->auctionId}")];
    }

    public function broadcastAs(): string
    {
        return "auction.{$this->type}";
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
