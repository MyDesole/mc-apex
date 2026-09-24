<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Services\TournamentBracketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TournamentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Tournament::query()->with('creator:id,username');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $tournaments = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($tournaments);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'in:solo,clan'],
            'format' => ['required', 'in:single_elim,double_elim,round_robin'],
            'prize_pool' => ['nullable', 'numeric', 'min:0'],
            'prize_currency' => ['nullable', 'string', 'max:8'],
            'prize_description' => ['nullable', 'string', 'max:255'],
            'min_tier' => ['nullable', 'in:S,A,B,C,D,E'],
            'max_tier' => ['nullable', 'in:S,A,B,C,D,E'],
            'max_participants' => ['required', 'integer', 'min:2', 'max:128'],
            'registration_starts_at' => ['nullable', 'date'],
            'registration_ends_at' => ['nullable', 'date', 'after:registration_starts_at'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($request->hasFile('banner')) {
            $validated['banner'] = $request->file('banner')->store('tournaments', 'public');
        }

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(6);
        $validated['created_by'] = $request->user()->id;
        $validated['status'] = 'registration';

        $tournament = Tournament::create($validated);

        return response()->json(['tournament' => $tournament], 201);
    }

    public function update(Request $request, Tournament $tournament): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['sometimes', 'in:solo,clan'],
            'format' => ['sometimes', 'in:single_elim,double_elim,round_robin'],
            'status' => ['sometimes', 'in:draft,registration,ongoing,completed,cancelled'],
            'prize_pool' => ['nullable', 'numeric', 'min:0'],
            'prize_currency' => ['nullable', 'string', 'max:8'],
            'prize_description' => ['nullable', 'string', 'max:255'],
            'min_tier' => ['nullable', 'in:S,A,B,C,D,E'],
            'max_tier' => ['nullable', 'in:S,A,B,C,D,E'],
            'max_participants' => ['sometimes', 'integer', 'min:2', 'max:128'],
            'registration_starts_at' => ['nullable', 'date'],
            'registration_ends_at' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $tournament->update($validated);

        return response()->json(['tournament' => $tournament->fresh()]);
    }

    public function destroy(Request $request, Tournament $tournament): JsonResponse
    {
        $tournament->delete();
        return response()->json(['ok' => true]);
    }

    // === Участники ===

    public function participants(Request $request, Tournament $tournament): JsonResponse
    {
        $participants = $tournament->participants()
            ->with(['user:id,username,avatar,tier', 'clan:id,name,tag,banner_color'])
            ->orderBy('seed')
            ->get();

        return response()->json(['participants' => $participants]);
    }

    public function approveParticipant(Request $request, Tournament $tournament, TournamentParticipant $participant): JsonResponse
    {
        abort_if($participant->tournament_id !== $tournament->id, 404);

        $participant->update(['status' => 'approved']);

        return response()->json(['participant' => $participant->fresh()]);
    }

    public function rejectParticipant(Request $request, Tournament $tournament, TournamentParticipant $participant): JsonResponse
    {
        abort_if($participant->tournament_id !== $tournament->id, 404);

        $participant->update(['status' => 'rejected']);

        return response()->json(['participant' => $participant->fresh()]);
    }

    public function setSeeds(Request $request, Tournament $tournament): JsonResponse
    {
        $validated = $request->validate([
            'seeds' => ['required', 'array'],
            'seeds.*.id' => ['required', 'exists:tournament_participants,id'],
            'seeds.*.seed' => ['required', 'integer', 'min:1'],
        ]);

        foreach ($validated['seeds'] as $item) {
            TournamentParticipant::where('id', $item['id'])
                ->where('tournament_id', $tournament->id)
                ->update(['seed' => $item['seed']]);
        }

        return response()->json(['ok' => true]);
    }

    // === Сетка ===

    public function matches(Request $request, Tournament $tournament): JsonResponse
    {
        $matches = $tournament->matches()
            ->with([
                'participant1.user:id,username,avatar',
                'participant1.clan:id,name,tag,banner_color',
                'participant2.user:id,username,avatar',
                'participant2.clan:id,name,tag,banner_color',
                'winner',
            ])
            ->get();

        return response()->json(['matches' => $matches]);
    }

    public function generateBracket(Request $request, Tournament $tournament): JsonResponse
    {
        abort_if($tournament->format !== 'single_elim', 422, 'Пока поддерживается только single_elim.');

        try {
            TournamentBracketService::generateSingleElim($tournament);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'matches' => $tournament->matches()->get(),
        ]);
    }

    public function updateMatch(Request $request, Tournament $tournament, TournamentMatch $match): JsonResponse
    {
        abort_if($match->tournament_id !== $tournament->id, 404);

        $validated = $request->validate([
            'score1' => ['nullable', 'integer', 'min:0'],
            'score2' => ['nullable', 'integer', 'min:0'],
            'winner_id' => ['nullable', 'exists:tournament_participants,id'],
            'status' => ['sometimes', 'in:pending,ready,live,completed,cancelled'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        if (!empty($validated['winner_id'])) {
            $validated['status'] = 'completed';
            $validated['completed_at'] = now();
        }

        $match->update($validated);

        // если есть победитель — продвигаем в следующий матч
        if ($match->winner_id) {
            TournamentBracketService::advanceWinner($match->fresh());
        }

        return response()->json(['match' => $match->fresh()]);
    }
}
