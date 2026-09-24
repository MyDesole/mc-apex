<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClanMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClanRoleController extends Controller
{
    public function update(Request $request, User $user): JsonResponse
    {
        $clan = $request->attributes->get('clan');
        $me = $request->attributes->get('clan_membership');

        abort_unless($me->role === 'leader', 403, 'Только лидер может менять роли.');
        abort_if($user->id === $request->user()->id, 422, 'Нельзя менять свою роль.');

        $member = ClanMember::where('clan_id', $clan->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'role' => ['required', 'in:officer,member'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['in:news,forum,applications,wars,resources'],
            'title' => ['nullable', 'string', 'max:32'],
        ]);

        $member->update([
            'role' => $validated['role'],
            'permissions' => $validated['permissions'] ?? null,
            'title' => $validated['title'] ?? null,
            'promoted_at' => now(),
            'promoted_by' => $request->user()->id,
        ]);

        return response()->json(['member' => $member->fresh()->load('user:id,username,avatar')]);
    }

    public function kick(Request $request, User $user): JsonResponse
    {
        $clan = $request->attributes->get('clan');
        $me = $request->attributes->get('clan_membership');

        abort_unless($me->role === 'leader', 403);
        abort_if($user->id === $clan->leader_id, 422, 'Нельзя кикнуть лидера.');

        ClanMember::where('clan_id', $clan->id)
            ->where('user_id', $user->id)
            ->delete();

        $user->update(['clan_joined_at' => null]);
        $clan->recalculatePower();

        return response()->json(['ok' => true]);
    }

    public function transferLeadership(Request $request, User $user): JsonResponse
    {
        $clan = $request->attributes->get('clan');
        $me = $request->attributes->get('clan_membership');

        abort_unless($me->role === 'leader', 403);
        abort_if($user->id === $request->user()->id, 422);

        $newLeaderMember = ClanMember::where('clan_id', $clan->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        DB::transaction(function () use ($clan, $me, $newLeaderMember, $request) {
            // старый лидер → офицер
            $me->update(['role' => 'officer']);
            // новый → лидер
            $newLeaderMember->update(['role' => 'leader']);
            // обновляем клан
            $clan->update(['leader_id' => $user->id]);
        });

        return response()->json(['ok' => true]);
    }
}
