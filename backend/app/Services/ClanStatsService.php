<?php

namespace App\Services;

use App\Models\Clan;
use Illuminate\Support\Facades\DB;

/**
 * Админская правка статистики клана: дельта и точные значения.
 */
class ClanStatsService
{
    /**
     * Изменить статистику на дельту (может быть отрицательной).
     */
    public function adjust(Clan $clan, array $data): Clan
    {
        return DB::transaction(function () use ($clan, $data) {
            $clan->wins = max(0, $clan->wins + ($data['wins'] ?? 0));
            $clan->losses = max(0, $clan->losses + ($data['losses'] ?? 0));
            $clan->save();

            if (class_exists(\App\Models\ClanStatsLog::class)) {
                \App\Models\ClanStatsLog::create([
                    'clan_id' => $clan->id,
                    'wins_delta' => $data['wins'] ?? 0,
                    'losses_delta' => $data['losses'] ?? 0,
                    'reason' => $data['reason'] ?? null,
                ]);
            }

            $clan->recalculatePower();

            return $clan->fresh();
        });
    }

    /**
     * Установить точные значения побед и поражений.
     */
    public function set(Clan $clan, array $data): Clan
    {
        return DB::transaction(function () use ($clan, $data) {
            $clan->wins = $data['wins'];
            $clan->losses = $data['losses'];
            $clan->save();

            $clan->recalculatePower();

            return $clan->fresh();
        });
    }
}
