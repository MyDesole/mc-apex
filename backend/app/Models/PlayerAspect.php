<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerAspect extends Model
{
    protected $fillable = [
        'user_id', 'mode',
        'block_placing', 'rotka', 'movement', 'building', 'ppl',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Средний балл по аспектам.
     */
    public function averageScore(): float
    {
        return round(
            ($this->block_placing + $this->rotka + $this->movement
                + $this->building + $this->ppl) / 5,
            2
        );
    }

    /**
     * Процент = сумма × 2.
     */
    public function percent(): float
    {
        $sum = $this->block_placing + $this->rotka + $this->movement
            + $this->building + $this->ppl;

        return round($sum * 2, 2);
    }

    /**
     * Тир по проценту.
     */
    public function tier(): string
    {
        $p = $this->percent();

        return match (true) {
            $p >= 90 => 'S',
            $p >= 80 => 'A',
            $p >= 70 => 'B',
            $p >= 60 => 'C',
            $p >= 50 => 'D',
            default  => 'E',
        };
    }
}
