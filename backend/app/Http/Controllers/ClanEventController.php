<?php

namespace App\Http\Controllers;

use App\Models\Clan;
use App\Models\ClanEvent;
use App\Models\ClanMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClanEventController extends Controller
{
    public function index(Clan $clan): JsonResponse
    {
        $events = $clan->events()
            ->with('author:id,username,avatar')
            ->paginate(20);

        return response()->json($events);
    }

    public function store(Request $request, Clan $clan): JsonResponse
    {
        abort_unless($this->canManage($clan, $request->user()->id), 403);

        $validated = $request->validate([
            'type' => ['required', 'in:announcement,event,training'],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['nullable', 'date'],
        ]);

        $event = ClanEvent::create([
            ...$validated,
            'clan_id' => $clan->id,
            'author_id' => $request->user()->id,
        ]);

        return response()->json(['event' => $event->load('author:id,username,avatar')], 201);
    }

    public function destroy(Request $request, Clan $clan, ClanEvent $event): JsonResponse
    {
        abort_if($event->clan_id !== $clan->id, 404);
        abort_unless(
            $clan->isLeader($request->user()->id) || $event->author_id === $request->user()->id,
            403
        );

        $event->delete();

        return response()->json(['ok' => true]);
    }

    private function canManage(Clan $clan, int $userId): bool
    {
        return ClanMember::where('clan_id', $clan->id)
            ->where('user_id', $userId)
            ->whereIn('role', ['leader', 'officer'])
            ->exists();
    }
}
