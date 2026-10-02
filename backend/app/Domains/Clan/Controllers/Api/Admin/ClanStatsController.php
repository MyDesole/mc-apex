<?php

namespace App\Domains\Clan\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Domains\Clan\Requests\Admin\AdjustClanStatsRequest;
use App\Domains\Clan\Requests\Admin\SetClanStatsRequest;
use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Services\ClanStatsService;
use Illuminate\Http\JsonResponse;

/**
 * Админская правка статистики клана. Транзакции — в ClanStatsService.
 */
class ClanStatsController extends Controller
{
    public function __construct(
        private readonly ClanStatsService $stats,
    ) {
    }

    /**
     * Прибавить/убавить победы и поражения клана.
     */
    public function update(AdjustClanStatsRequest $request, Clan $clan): JsonResponse
    {
        return response()->json([
            'clan' => $this->stats->adjust($clan, $request->validated()),
        ]);
    }

    /**
     * Установить точные значения (не дельта).
     */
    public function set(SetClanStatsRequest $request, Clan $clan): JsonResponse
    {
        return response()->json([
            'clan' => $this->stats->set($clan, $request->validated()),
        ]);
    }

    public function logs(Clan $clan): JsonResponse
    {
        if (! class_exists(\App\Domains\Clan\Models\ClanStatsLog::class)) {
            return response()->json(['logs' => []]);
        }

        $logs = \App\Domains\Clan\Models\ClanStatsLog::where('clan_id', $clan->id)
            ->with('user:id,username')
            ->latest()
            ->limit(50)
            ->get();

        return response()->json(['logs' => $logs]);
    }
}
