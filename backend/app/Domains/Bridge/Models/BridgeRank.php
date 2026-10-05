<?php

namespace App\Domains\Bridge\Models;

use Illuminate\Database\Eloquent\Model;

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
}
