<?php

namespace App\Domains\Tournaments\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Domains\Tournaments\Requests\Admin\SetTournamentSeedsRequest;
use App\Domains\Tournaments\Requests\Admin\StoreTournamentRequest;
use App\Domains\Tournaments\Requests\Admin\UpdateTournamentMatchRequest;
use App\Domains\Tournaments\Requests\Admin\UpdateTournamentRequest;
use App\Domains\Tournaments\Models\Tournament;
use App\Domains\Tournaments\Models\TournamentMatch;
use App\Domains\Tournaments\Models\TournamentParticipant;
use App\Domains\Tournaments\Services\TournamentBracketService;
use App\Domains\Tournaments\Notifications\TournamentNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Баннер сохраняем файлом, как при создании: раньше в базу
        // попадал путь временного файла загрузки
        if ($request->hasFile('banner')) {
            $validated['banner'] = $request->file('banner')->store('tournaments', 'public');
        } else {
            unset($validated['banner']);
        }

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
            ->with(['user:id,username,avatar,tier,bridge_primary,bridge_rank_id,profile_mode,name_effect,chat_bubble,profile_title,name_icon', 'clan:id,name,tag,banner_color'])
            ->orderBy('seed')
            ->get();

        return response()->json(['participants' => $participants]);
    }

    public function approveParticipant(Request $request, Tournament $tournament, TournamentParticipant $participant): JsonResponse
    {
        abort_if($participant->tournament_id !== $tournament->id, 404);

        $participant->update(['status' => 'approved']);

        $this->notifyParticipant($participant, 'tournament_approved', "Заявка на турнир «{$tournament->name}» одобрена — ждите сетку!");

        return response()->json(['participant' => $participant->fresh()]);
    }

    public function rejectParticipant(Request $request, Tournament $tournament, TournamentParticipant $participant): JsonResponse
    {
        abort_if($participant->tournament_id !== $tournament->id, 404);

        $participant->update(['status' => 'rejected']);

        $this->notifyParticipant($participant, 'tournament_rejected', "Заявка на турнир «{$tournament->name}» отклонена.");

        return response()->json(['participant' => $participant->fresh()]);
    }

    /**
     * Убрать участника из турнира совсем — например, уже одобренного,
     * который не сможет играть. Если сетка построена, но ни один матч
     * ещё не сыгран, сетку сбрасываем — админ строит её заново.
     */
    public function removeParticipant(Request $request, Tournament $tournament, TournamentParticipant $participant): JsonResponse
    {
        abort_if($participant->tournament_id !== $tournament->id, 404);

        $inBracket = TournamentMatch::where('tournament_id', $tournament->id)
            ->where(fn ($q) => $q->where('participant1_id', $participant->id)->orWhere('participant2_id', $participant->id))
            ->exists();

        $played = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('status', 'completed')
            ->where('is_bye', false)
            ->exists();

        abort_if($inBracket && $played, 422, 'Турнир уже идёт: участник есть в сыгранной сетке. Отметьте его поражение в матче вместо удаления.');

        $this->notifyParticipant($participant, 'tournament_rejected', "Вы удалены из турнира «{$tournament->name}».");

        DB::transaction(function () use ($tournament, $participant, $inBracket) {
            if ($inBracket) {
                $tournament->matches()->delete();
                $tournament->update(['status' => 'registration']);
            }

            $participant->delete();
        });

        return response()->json([
            'ok' => true,
            'bracket_reset' => $inBracket,
        ]);
    }

    /** Игроку — лично, клану — его лидеру. */
    private function notifyParticipant(TournamentParticipant $participant, string $type, string $message): void
    {
        $recipient = $participant->user ?? $participant->clan?->leader;

        $recipient?->notify(new TournamentNotification($type, $message, $participant->tournament_id));
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
            // Победитель — один из двух участников именно этого матча
            abort_unless(
                in_array((int) $validated['winner_id'], [(int) $match->participant1_id, (int) $match->participant2_id], true),
                422,
                'Победитель должен быть одним из участников матча.'
            );

            // Следующий матч уже сыгран — менять результат поздно: в нём играл прежний победитель
            $next = $match->nextMatch;
            abort_if(
                $next && $next->status === 'completed' && (int) $match->winner_id !== (int) $validated['winner_id'],
                422,
                'Следующий матч уже сыгран — сначала исправьте его.'
            );

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
