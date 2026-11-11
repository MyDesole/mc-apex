<?php

namespace App\Domains\Clan\Services;

use App\Domains\Clan\Models\Clan;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Бусты клана за казну: лидер покупает временное усиление для всех участников.
 *
 * Бусты складываются по времени: повторная покупка продлевает действующий буст
 * на сутки, а не заменяет его.
 */
class ClanBoostService
{
    public const DURATION_HOURS = 24;

    /** ключ => [название, описание, цена в казне, колонка со сроком] */
    public const BOOSTS = [
        'xp' => [
            'title' => 'Двойной опыт',
            'text' => 'Каждая отметка даёт клану вдвое больше опыта.',
            'price' => 300,
            'column' => 'xp_boost_until',
        ],
        'coins' => [
            'title' => 'Двойная казна',
            'text' => 'Каждая отметка приносит в казну вдвое больше ApexCoin.',
            'price' => 300,
            'column' => 'coin_boost_until',
        ],
    ];

    /** Каталог бустов и сколько времени осталось у действующих. */
    public function state(Clan $clan): array
    {
        return collect(self::BOOSTS)->map(function (array $boost, string $key) use ($clan) {
            $until = $clan->{$boost['column']};
            $active = $until && $until->isFuture();

            return [
                'key' => $key,
                'title' => $boost['title'],
                'text' => $boost['text'],
                'price' => $boost['price'],
                'hours' => self::DURATION_HOURS,
                'active' => $active,
                'until' => $active ? $until->toIso8601String() : null,
            ];
        })->values()->all();
    }

    /** Купить буст из казны. Покупает только лидер (проверяет маршрут). */
    public function buy(Clan $clan, User $leader, string $key): array
    {
        $boost = self::BOOSTS[$key] ?? null;

        if (! $boost) {
            throw new RuntimeException('Такого буста нет.');
        }

        DB::transaction(function () use ($clan, $leader, $boost) {
            app(ClanTreasuryService::class)->spend($clan, $leader, $boost['price'], 'Буст: ' . $boost['title']);

            $current = $clan->fresh()->{$boost['column']};
            $from = $current && $current->isFuture() ? $current : now();

            Clan::whereKey($clan->id)->update([$boost['column'] => $from->copy()->addHours(self::DURATION_HOURS)]);
        });

        return $this->state($clan->fresh());
    }
}
