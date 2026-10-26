<?php

namespace App\Domains\Clan\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ежедневная отметка участника в клане. Каждая даёт клану опыт.
 */
class ClanCheckin extends Model
{
    // day храним строкой Y-m-d без каста: с кастом date Eloquent пишет
    // «Y-m-d 00:00:00», и поиск по дню перестаёт совпадать
    protected $fillable = ['clan_id', 'user_id', 'day'];

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
