<?php

namespace App\Http\Controllers;

use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\ClanWar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClanWarController extends Controller
{
    public function store(Request $request, Clan $clan): JsonResponse
    {
        $user = $request->user();
        $myClan = $user->clanMember?->clan;

        abort_if(!$myClan, 403, 'Вы не в клане.');
        abort_if($myClan->id === $clan->id, 422, 'Нельзя вызвать свой клан.');
        abort_unless($this->canManage($myClan, $user->id), 403, 'Нет прав.');

        $validated = $request->validate([
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $war = ClanWar::create([
            'challenger_clan_id' => $myClan->id,
            'opponent_clan_id' => $clan->id,
            'created_by' => $user->id,
            'status' => 'pending',
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json(['war' => $war->load(['challenger', 'opponent'])], 201);
    }

    public function accept(Request $request, ClanWar $war): JsonResponse
    {
        $user = $request->user();
        $myClan = $user->clanMember?->clan;

        abort_if(!$myClan || $myClan->id !== $war->opponent_clan_id, 403);
        abort_unless($this->canManage($myClan, $user->id), 403);
        abort_if($war->status !== 'pending', 422);

        $war->update(['status' => 'accepted']);

        return response()->json(['war' => $war->fresh(['challenger', 'opponent'])]);
    }

    public function decline(Request $request, ClanWar $war): JsonResponse
    {
        $user = $request->user();
        $myClan = $user->clanMember?->clan;

        abort_if(!$myClan || $myClan->id !== $war->opponent_clan_id, 403);
        abort_if($war->status !== 'pending', 422);

        $war->update(['status' => 'declined']);

        return response()->json(['ok' => true]);
    }

    public function complete(Request $request, ClanWar $war): JsonResponse
    {
        $user = $request->user();
        $myClan = $user->clanMember?->clan;

        abort_if(!$myClan, 403);
        abort_if(
            !in_array($myClan->id, [$war->challenger_clan_id, $war->opponent_clan_id]),
            403
        );

        $validated = $request->validate([
            'challenger_score' => ['required', 'integer', 'min:0', 'max:100'],
            'opponent_score' => ['required', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($war, $validated) {
            $winnerId = $validated['challenger_score'] > $validated['opponent_score']
                ? $war->challenger_clan_id
                : $war->opponent_clan_id;

            $war->update([
                ...$validated,
                'status' => 'completed',
                'winner_clan_id' => $winnerId,
            ]);

            $challenger = Clan::find($war->challenger_clan_id);
            $opponent = Clan::find($war->opponent_clan_id);

            if ($winnerId === $challenger->id) {
                $challenger->increment('wins');
                $opponent->increment('losses');
            } else {
                $opponent->increment('wins');
                $challenger->increment('losses');
            }

            $challenger->recalculatePower();
            $opponent->recalculatePower();
        });

        return response()->json(['war' => $war->fresh(['challenger', 'opponent'])]);
    }

    private function canManage(Clan $clan, int $userId): bool
    {
        return ClanMember::where('clan_id', $clan->id)
            ->where('user_id', $userId)
            ->whereIn('role', ['leader', 'officer'])
            ->exists();
    }
}
