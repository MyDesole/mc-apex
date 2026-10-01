<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SetTournamentSeedsRequest;
use App\Http\Requests\Admin\StoreTournamentRequest;
use App\Http\Requests\Admin\UpdateTournamentMatchRequest;
use App\Http\Requests\Admin\UpdateTournamentRequest;
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

    public function store(StoreTournamentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('banner')) {
            $validated['banner'] = $request->file('banner')->store('tournaments', 'public');
        }

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(6);
        $validated['created_by'] = $request->user()->id;
        $validated['status'] = 'registration';

        $tournament = Tournament::create($validated);

        return response()->json(['tournament' => $tournament], 201);
    }

    public function update(UpdateTournamentRequest $request, Tournament $tournament): JsonResponse
    {
        $validated = $request->validated();

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

    public function setSeeds(SetTournamentSeedsRequest $request, Tournament $tournament): JsonResponse
    {
        $validated = $request->validated();

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

    public function updateMatch(UpdateTournamentMatchRequest $request, Tournament $tournament, TournamentMatch $match): JsonResponse
    {
        abort_if($match->tournament_id !== $tournament->id, 404);

        $validated = $request->validated();

        if (! empty($validated['winner_id'])) {
            // Победитель обязан быть участником ЭТОГО турнира: правило
            // exists:tournament_participants проверяло только существование,
            // и в следующий матч мог пройти чужой участник.
            $belongs = TournamentParticipant::where('id', $validated['winner_id'])
                ->where('tournament_id', $tournament->id)
                ->exists();

            abort_unless($belongs, 422, 'Победитель не участвует в этом турнире.');

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
