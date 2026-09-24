<?php

namespace App\Http\Controllers;

use App\Models\Clan;
use App\Models\ClanApplication;
use App\Models\ClanMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ClanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Clan::query()->with('leader:id,username,avatar');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('tag', 'like', "%{$search}%");
            });
        }

        $clans = $query->orderByDesc('power')->paginate(20);

        $clans->getCollection()->transform(function ($clan) {
            $clan->members_count = $clan->members()->count();
            return $clan;
        });

        return response()->json($clans);
    }

    public function top(): JsonResponse
    {
        $clans = Clan::query()
            ->with('leader:id,username,avatar')
            ->orderByDesc('power')
            ->limit(10)
            ->get()
            ->map(function ($clan) {
                return [
                    'id' => $clan->id,
                    'name' => $clan->name,
                    'tag' => $clan->tag,
                    'avatar' => $clan->avatar,
                    'banner_color' => $clan->banner_color,
                    'power' => $clan->power,
                    'wins' => $clan->wins,
                    'losses' => $clan->losses,
                    'members_count' => $clan->members()->count(),
                    'leader' => $clan->leader,
                ];
            });

        return response()->json(['clans' => $clans]);
    }

    public function show(Request $request, Clan $clan): JsonResponse
    {
        $clan->load([
            'leader:id,username,avatar,tier',
            'members.user:id,username,avatar,tier',
            'events.author:id,username,avatar',
        ]);

        $me = $request->user();
        $myClan = $me->clanMember?->clan_id;
        $isMember = $clan->isMember($me->id);

        $application = ClanApplication::where('clan_id', $clan->id)
            ->where('user_id', $me->id)
            ->where('status', 'pending')
            ->first();

        $incomingWars = \App\Models\ClanWar::where('opponent_clan_id', $clan->id)
            ->where('status', 'pending')
            ->with(['challenger:id,name,tag,power,banner_color', 'opponent:id,name,tag,power,banner_color'])
            ->latest()
            ->get();

        $outgoingWars = \App\Models\ClanWar::where('challenger_clan_id', $clan->id)
            ->with(['challenger:id,name,tag,power,banner_color', 'opponent:id,name,tag,power,banner_color'])
            ->latest()
            ->get();

        return response()->json([
            'clan' => $clan,
            'is_member' => $isMember,
            'my_clan_id' => $myClan,
            'application' => $application,
            'members_count' => $clan->members()->count(),
            'incoming_wars' => $incomingWars,
            'outgoing_wars' => $outgoingWars,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->clanMember, 422, 'Вы уже в клане.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:32', 'unique:clans,name'],
            'tag' => ['required', 'string', 'min:2', 'max:8', 'unique:clans,tag'],
            'description' => ['nullable', 'string', 'max:1000'],
            'banner_color' => ['nullable', 'string', 'max:16'],
            'is_open' => ['boolean'],
        ]);

        $clan = DB::transaction(function () use ($validated, $request) {
            $clan = Clan::create([
                ...$validated,
                'leader_id' => $request->user()->id,
                'banner_color' => $validated['banner_color'] ?? '#7c3aed',
                'is_open' => $validated['is_open'] ?? true,
            ]);

            ClanMember::create([
                'clan_id' => $clan->id,
                'user_id' => $request->user()->id,
                'role' => 'leader',
                'joined_at' => now(),
            ]);

            return $clan;
        });

        return response()->json(['clan' => $clan], 201);
    }

    public function update(Request $request, Clan $clan): JsonResponse
    {
        abort_unless($clan->isLeader($request->user()->id), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:32', 'unique:clans,name,' . $clan->id],
            'tag' => ['sometimes', 'string', 'min:2', 'max:8', 'unique:clans,tag,' . $clan->id],
            'description' => ['nullable', 'string', 'max:1000'],
            'banner_color' => ['nullable', 'string', 'max:16'],
            'is_open' => ['boolean'],
            'is_highlighted' => ['boolean'],

            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'socials' => ['nullable', 'array'],
            'socials.discord' => ['nullable', 'string', 'max:255'],
            'socials.telegram' => ['nullable', 'string', 'max:255'],
            'socials.youtube' => ['nullable', 'string', 'max:255'],
            'socials.vk' => ['nullable', 'string', 'max:255'],
            'socials.website' => ['nullable', 'url', 'max:255'],
        ]);

        // Файлы — заменяем значение поля на путь в storage
        if ($request->hasFile('avatar')) {
            if ($clan->avatar) {
                Storage::disk('public')->delete($clan->avatar);
            }

            $validated['avatar'] = $request
                ->file('avatar')
                ->store("clans/{$clan->id}", 'public');
        }

        if ($request->hasFile('cover')) {
            if ($clan->cover_path) {
                Storage::disk('public')->delete($clan->cover_path);
            }

            $validated['cover_path'] = $request
                ->file('cover')
                ->store("clans/{$clan->id}/covers", 'public');
        }

        // Удаляем только ключ 'cover' — его нет в БД, есть 'cover_path'
        unset($validated['cover']);

        $clan->update($validated);

        return response()->json(['clan' => $clan->fresh()]);
    }

    public function removeCover(Request $request, Clan $clan): JsonResponse
    {
        abort_unless($clan->isLeader($request->user()->id), 403);

        if ($clan->cover_path) {
            Storage::disk('public')->delete($clan->cover_path);
            $clan->update(['cover_path' => null]);
        }

        return response()->json(['clan' => $clan->fresh()]);
    }

    public function removeAvatar(Request $request, Clan $clan): JsonResponse
    {
        abort_unless($clan->isLeader($request->user()->id), 403);

        if ($clan->avatar) {
            Storage::disk('public')->delete($clan->avatar);
            $clan->update(['avatar' => null]);
        }

        return response()->json(['clan' => $clan->fresh()]);
    }

    public function apply(Request $request, Clan $clan): JsonResponse
    {
        $user = $request->user();
        abort_if($user->clanMember, 422, 'Вы уже в клане.');
        abort_if(!$clan->is_open, 422, 'Клан закрыт для вступления.');

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $existing = ClanApplication::where('clan_id', $clan->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && $existing->status === 'pending') {
            return response()->json(['message' => 'Заявка уже отправлена.'], 422);
        }

        $application = ClanApplication::updateOrCreate(
            ['clan_id' => $clan->id, 'user_id' => $user->id],
            ['message' => $validated['message'] ?? null, 'status' => 'pending']
        );

        // Уведомляем лидера
        $clan->leader->notify(new \App\Notifications\ClanApplicationNotification($clan, $user));

        return response()->json(['application' => $application], 201);
    }

    public function applications(Request $request, Clan $clan): JsonResponse
    {
        abort_unless(
            $clan->isLeader($request->user()->id) || $this->isOfficer($clan, $request->user()->id),
            403
        );

        $applications = ClanApplication::where('clan_id', $clan->id)
            ->where('status', 'pending')
            ->with('user:id,username,avatar,tier,tier_score')
            ->latest()
            ->get();

        return response()->json(['applications' => $applications]);
    }
    public function acceptApplication(Request $request, Clan $clan, ClanApplication $application): JsonResponse
    {
        abort_unless($clan->isLeader($request->user()->id) || $this->isOfficer($clan, $request->user()->id), 403);
        abort_if($application->clan_id !== $clan->id, 404);

        if ($clan->members()->count() >= $clan->max_members) {
            return response()->json(['message' => 'Клан заполнен.'], 422);
        }

        DB::transaction(function () use ($application, $clan) {
            ClanMember::create([
                'clan_id' => $clan->id,
                'user_id' => $application->user_id,
                'role' => 'member',
                'joined_at' => now(),
            ]);

            $application->update(['status' => 'accepted']);
            $clan->recalculatePower();
        });

        // уведомляем заявителя
        $application->user->notify(new \App\Notifications\ClanApplicationAcceptedNotification($clan));

        return response()->json(['ok' => true]);
    }

    public function declineApplication(Request $request, Clan $clan, ClanApplication $application): JsonResponse
    {
        abort_unless($clan->isLeader($request->user()->id) || $this->isOfficer($clan, $request->user()->id), 403);
        abort_if($application->clan_id !== $clan->id, 404);

        $application->update(['status' => 'declined']);

        $application->user->notify(new \App\Notifications\ClanApplicationDeclinedNotification($clan));

        return response()->json(['ok' => true]);
    }

    public function leave(Request $request, Clan $clan): JsonResponse
    {
        $member = ClanMember::where('clan_id', $clan->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_if($member->role === 'leader', 422, 'Лидер не может покинуть клан. Передайте лидерство.');

        $member->delete();
        $clan->recalculatePower();

        return response()->json(['ok' => true]);
    }

    public function kick(Request $request, Clan $clan, \App\Models\User $user): JsonResponse
    {
        abort_unless($clan->isLeader($request->user()->id), 403);
        abort_if($user->id === $clan->leader_id, 422, 'Нельзя кикнуть лидера.');

        ClanMember::where('clan_id', $clan->id)
            ->where('user_id', $user->id)
            ->delete();

        $clan->recalculatePower();

        return response()->json(['ok' => true]);
    }

    private function isOfficer(Clan $clan, int $userId): bool
    {
        return ClanMember::where('clan_id', $clan->id)
            ->where('user_id', $userId)
            ->where('role', 'officer')
            ->exists();
    }
}
