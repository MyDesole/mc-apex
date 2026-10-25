<?php

namespace App\Domains\Bridge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Ранг бриджера: звание, которое вручную выдаёт бридж-тестер.
 *
 * Не путать с тиром: тир считается по очкам, а ранг присваивается за
 * подтверждённые виды и общий уровень игры.
 */
class BridgeRank extends Model
{
    protected $fillable = [
        'key', 'label', 'color', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    private const CACHE_KEY = 'bridge_ranks:by_id';

    /** Звания редко меняются: держим их в кэше для показа рядом с ником. */
    public static function cachedById(int $id): ?array
    {
        $all = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
            ->get(['id', 'label', 'color'])
            ->keyBy('id')
            ->map(fn (self $rank) => ['label' => $rank->label, 'color' => $rank->color])
            ->all());

        return $all[$id] ?? null;
    }

    protected static function booted(): void
    {
        $forget = fn () => Cache::forget(self::CACHE_KEY);

        static::saved($forget);
        static::deleted($forget);
    }
}
