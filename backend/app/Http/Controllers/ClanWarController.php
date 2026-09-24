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
            \App\Services\AchievementService::check($war->challenger->leader);
            \App\Services\AchievementService::check($war->opponent->leader);
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

    public function join(Request $request, ClanWar $war): JsonResponse
    {
        $user = $request->user();
        $myClan = $user->clanMember?->clan;

        abort_if(!$myClan, 403, 'Вы не в клане.');
        abort_if(
            !in_array($myClan->id, [$war->challenger_clan_id, $war->opponent_clan_id]),
            403,
            'Вы не участвуете в этой войне.'
        );
        abort_if(
            in_array($war->status, ['completed', 'cancelled', 'declined']),
            422,
            'Война уже завершена.'
        );

        $exists = \App\Models\ClanWarParticipant::where('clan_war_id', $war->id)
            ->where('user_id', $user->id)
            ->exists();

        abort_if($exists, 422, 'Вы уже участвуете.');

        \App\Models\ClanWarParticipant::create([
            'clan_war_id' => $war->id,
            'user_id' => $user->id,
            'clan_id' => $myClan->id,
        ]);

        return response()->json(['ok' => true]);
    }

    public function leave(Request $request, ClanWar $war): JsonResponse
    {
        $user = $request->user();

        \App\Models\ClanWarParticipant::where('clan_war_id', $war->id)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function show(Request $request, ClanWar $war): JsonResponse
    {
        $user = $request->user();
        $myClan = $user->clanMember?->clan;

        abort_if(!$myClan, 403);
        abort_if(
            !in_array($myClan->id, [$war->challenger_clan_id, $war->opponent_clan_id]),
            403
        );

        $war->load([
            'challenger:id,name,tag,power,banner_color,avatar',
            'opponent:id,name,tag,power,banner_color,avatar',
            'participants.user:id,username,avatar,tier',
            'participants.clan:id,name,tag,banner_color',
        ]);

        return response()->json([
            'war' => $war,
            'my_clan_id' => $myClan->id,
            'is_participant' => $war->participants->contains('user_id', $user->id),
        ]);
    }
}
