<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyClanController extends Controller
{
    /**
     * Дашборд моего клана — всё сразу.
     */
    public function index(Request $request): JsonResponse
    {
        $membership = $request->user()->clanMember;

        if (!$membership) {
            return response()->json(['clan' => null]);
        }

        $clan = $membership->clan()->withCount('members')->first();

        return response()->json([
            'clan' => $clan,
            'my_role' => $membership->role,
            'my_permissions' => [
                'news' => $membership->can('news'),
                'forum' => $membership->can('forum'),
                'applications' => $membership->can('applications'),
                'wars' => $membership->can('wars'),
                'resources' => $membership->can('resources'),
                'roles' => $membership->role === 'leader',
                'kick' => $membership->role === 'leader',
                'edit_clan' => $membership->role === 'leader',
            ],
            'stats' => [
                'members' => $clan->members()->count(),
                'applications' => $clan->applications()->count(),
                'wars_active' => \App\Models\ClanWar::where('challenger_clan_id', $clan->id)
                    ->orWhere('opponent_clan_id', $clan->id)
                    ->whereIn('status', ['pending', 'accepted'])
                    ->count(),
                'forum_topics' => $clan->forumTopics()->count(),
                'resources' => $clan->resources()->count(),
            ],
        ]);
    }
}
