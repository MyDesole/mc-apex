<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClanStatsController extends Controller
{
    /**
     * Прибавить/убавить победы и поражения клана.
     */
    public function update(Request $request, Clan $clan): JsonResponse
    {
        $validated = $request->validate([
            'wins' => ['sometimes', 'integer', 'min:-999', 'max:999'],
            'losses' => ['sometimes', 'integer', 'min:-999', 'max:999'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($clan, $validated) {
            $clan->wins = max(0, $clan->wins + ($validated['wins'] ?? 0));
            $clan->losses = max(0, $clan->losses + ($validated['losses'] ?? 0));
            $clan->save();

            // лог изменения (опционально, если есть таблица)
            if (class_exists(\App\Models\ClanStatsLog::class)) {
                \App\Models\ClanStatsLog::create([
                    'clan_id' => $clan->id,
                    'user_id' => auth()->id(),
                    'wins_delta' => $validated['wins'] ?? 0,
                    'losses_delta' => $validated['losses'] ?? 0,
                    'reason' => $validated['reason'] ?? null,
                ]);
            }

            $clan->recalculatePower();
        });

        return response()->json(['clan' => $clan->fresh()]);
    }

    /**
     * Установить точные значения (не дельта).
     */
    public function set(Request $request, Clan $clan): JsonResponse
    {
        $validated = $request->validate([
            'wins' => ['required', 'integer', 'min:0', 'max:100000'],
            'losses' => ['required', 'integer', 'min:0', 'max:100000'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($clan, $validated) {
            $clan->wins = $validated['wins'];
            $clan->losses = $validated['losses'];
            $clan->save();

            $clan->recalculatePower();
        });

        return response()->json(['clan' => $clan->fresh()]);
    }

    public function logs(Clan $clan): JsonResponse
    {
        if (!class_exists(\App\Models\ClanStatsLog::class)) {
            return response()->json(['logs' => []]);
        }

        $logs = \App\Models\ClanStatsLog::where('clan_id', $clan->id)
            ->with('user:id,username')
            ->latest()
            ->limit(50)
            ->get();

        return response()->json(['logs' => $logs]);
    }
}
