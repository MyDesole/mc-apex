<?php

namespace App\Domains\Project\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Цель роадмапа. Прогресс считается автоматически по metric
 * (см. ProjectService::METRICS) или вводится вручную в progress_current.
 */
class RoadmapItem extends Model
{
    public const STATUSES = [
        'planned' => 'В планах',
        'in_progress' => 'В работе',
        'done' => 'Готово',
    ];

    protected $fillable = [
        'title', 'description', 'status', 'metric',
        'progress_current', 'progress_target', 'eta', 'sort_order',
    ];

    protected $casts = [
        'progress_current' => 'integer',
        'progress_target' => 'integer',
        'sort_order' => 'integer',
    ];
}
