<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Tournament::query()
            ->with('creator:id,username,avatar')
            ->withCount('participants');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $tournaments = $query->orderByDesc('created_at')->paginate(12);

        return response()->json($tournaments);
    }

    public function show(Request $request, Tournament $tournament): JsonResponse
    {
        $tournament->load([
            'creator:id,username,avatar',
            'participants.user:id,username,avatar,tier',
            'participants.clan:id,name,tag,banner_color,avatar',
            'matches.participant1.user:id,username,avatar',
            'matches.participant1.clan:id,name,tag',
            'matches.participant2.user:id,username,avatar',
            'matches.participant2.clan:id,name,tag',
        ]);

        $me = $request->user();

        $myParticipation = null;
        if ($me) {
            $myParticipation = TournamentParticipant::where('tournament_id', $tournament->id)
                ->where(function ($q) use ($me) {
                    $q->where('user_id', $me->id);
                    if ($me->clanMember) {
                        $q->orWhere('clan_id', $me->clanMember->clan_id);
                    }
                })
                ->first();
        }

        return response()->json([
            'tournament' => $tournament,
            'my_participation' => $myParticipation,
            'registration_open' => $tournament->isRegistrationOpen(),
        ]);
    }

    public function register(Request $request, Tournament $tournament): JsonResponse
    {
        abort_if(!$tournament->isRegistrationOpen(), 422, 'Регистрация закрыта.');

        $user = $request->user();

        // Проверка по тиру.
        // S+ обязан быть в списке: раньше его там не было, array_search
        // возвращал false, и S+ обходил любые ограничения.
        if ($tournament->min_tier || $tournament->max_tier) {
            $order = ['E', 'D', 'C', 'B', 'A', 'S', 'S+'];

            $userIdx = array_search($user->tier, $order, true);

            abort_if($userIdx === false, 422, 'Неизвестный тир игрока.');

            if ($tournament->min_tier) {
                $minIdx = array_search($tournament->min_tier, $order, true);
                abort_if(
                    $minIdx === false || $userIdx < $minIdx,
                    422,
                    "Нужен тир {$tournament->min_tier} или выше."
                );
            }

            if ($tournament->max_tier) {
                $maxIdx = array_search($tournament->max_tier, $order, true);
                abort_if(
                    $maxIdx === false || $userIdx > $maxIdx,
                    422,
                    "Нужен тир {$tournament->max_tier} или ниже."
                );
            }
        }

        // Клановый турнир: без клана заявка создавала участника
        // с user_id = null и clan_id = null — «фантомный» слот в сетке.
        if ($tournament->type === 'clan') {
            abort_if(! $user->clanMember, 422, 'Нужно состоять в клане.');
        }

        // проверка на дубликат
        $existing = TournamentParticipant::where('tournament_id', $tournament->id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if ($user->clanMember) {
                    $q->orWhere('clan_id', $user->clanMember->clan_id);
                }
            })
            ->first();

        abort_if($existing, 422, 'Вы уже зарегистрированы.');

        // проверка лимита
        $approvedCount = $tournament->approvedParticipants()->count();
        abort_if($approvedCount >= $tournament->max_participants, 422, 'Турнир заполнен.');

        $participant = TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $tournament->type === 'solo' ? $user->id : null,
            'clan_id' => $tournament->type === 'clan' ? $user->clanMember?->clan_id : null,
            'status' => 'pending',
        ]);

        return response()->json(['participant' => $participant], 201);
    }

    public function withdraw(Request $request, Tournament $tournament): JsonResponse
    {
        $user = $request->user();

        $participant = TournamentParticipant::where('tournament_id', $tournament->id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if ($user->clanMember) {
                    $q->orWhere('clan_id', $user->clanMember->clan_id);
                }
            })
            ->firstOrFail();

        abort_if($participant->status === 'approved', 422, 'Нельзя снять заявку после одобрения.');

        $participant->delete();

        return response()->json(['ok' => true]);
    }
}
