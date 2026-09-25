<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\PlayerAspect;
use App\Models\PlayerAspectBedwars;
use App\Models\PlayerAspectPvp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PlayerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $me = $request->user();

        $query = User::query()
            ->with('clanMember.clan:id,name,tag,banner_color')
            ->where('id', '!=', $me->id);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($tier = $request->query('tier')) {
            $query->where('tier', $tier);
        }

        $players = $query->orderByDesc('tier_score')->paginate(20);

        // friendship map (как было)
        $ids = collect($players->items())->pluck('id')->all();

        $friendships = Friendship::where(function ($q) use ($me, $ids) {
            $q->where('user_id', $me->id)->whereIn('friend_id', $ids);
        })->orWhere(function ($q) use ($me, $ids) {
            $q->where('friend_id', $me->id)->whereIn('user_id', $ids);
        })->get();

        $statusMap = [];
        foreach ($friendships as $f) {
            $otherId = $f->user_id === $me->id ? $f->friend_id : $f->user_id;
            $statusMap[$otherId] = [
                'status' => $f->status,
                'initiated_by_me' => $f->user_id === $me->id,
            ];
        }

        $players->getCollection()->transform(function ($player) use ($statusMap) {
            $player->friendship = $statusMap[$player->id] ?? null;
            return $player;
        });

        return response()->json($players);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'avatar_frame' => ['nullable', 'string', 'max:32'],
            'profile_effect' => ['nullable', 'string', 'max:32'],
            'accent_color' => ['nullable', 'string', 'max:16'],
            'status' => ['nullable', 'string', 'max:64'],
            'quote' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:500'],
            'favorite_clan_id' => ['nullable', 'exists:clans,id'],
            'featured_achievements' => ['nullable', 'array', 'max:6'],
            'featured_achievements.*' => ['integer', 'exists:achievements,id'],
            'profile_visibility' => ['nullable', 'in:public,friends,private'],

            // новые
            'discord_tag' => ['nullable', 'string', 'max:64'],
            'favorite_modes' => ['nullable', 'array'],
            'favorite_modes.*' => ['string', 'in:bedwars,skywars,duels,pvp,survival,other'],

            // файл
            'card_background' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        // загрузка кастомного фона
        if ($request->hasFile('card_background')) {
            if ($user->card_background) {
                Storage::disk('public')->delete($user->card_background);
            }

            $validated['card_background'] = $request
                ->file('card_background')
                ->store("users/{$user->id}/backgrounds", 'public');
        }

        unset($validated['card_background_file']); // если было

        $user->update($validated);

        return response()->json(['user' => $user->fresh()]);
    }

    public function removeCardBackground(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->card_background) {
            Storage::disk('public')->delete($user->card_background);
            $user->update(['card_background' => null]);
        }

        return response()->json(['user' => $user->fresh()]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $user->load([
            'aspectPvp',       // ← вместо 'aspects'
            'aspectBedwars',   // ← вместо 'aspects'
            'tierTests' => fn ($q) => $q->latest()->limit(10),
            'clanMember.clan',
            'achievements',
        ]);

        $me = $request->user();

        $friendship = Friendship::where(function ($q) use ($me, $user) {
            $q->where('user_id', $me->id)->where('friend_id', $user->id);
        })->orWhere(function ($q) use ($me, $user) {
            $q->where('user_id', $user->id)->where('friend_id', $me->id);
        })->first();

        $position = null;
        $total = 0;

        if ($user->tier_score > 0) {
            $position = User::where('tier_score', '>', $user->tier_score)->count() + 1;
            $total = User::where('tier_score', '>', 0)->count();
        }

        // 👇 Собираем aspects вручную
        $userArray = $user->toArray();
        $userArray['aspects'] = [
            'pvp' => $user->aspectPvp,
            'bedwars' => $user->aspectBedwars,
        ];

        $userArray['all_achievements'] = $user->achievements->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'icon' => $a->icon,
            'color' => $a->color,
            'description' => $a->description,
            'points' => $a->points,
            'earned_at' => $a->pivot->earned_at ?? null,
        ])->values();

        return response()->json([
            'user' => $userArray,
            'friendship' => $friendship ? [
                'status' => $friendship->status,
                'initiated_by_me' => $friendship->user_id === $me->id,
            ] : null,
            'rank' => [
                'position' => $position,
                'total' => $total,
            ],
        ]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'bio' => ['nullable', 'string', 'max:500'],
            'banner_color' => ['nullable', 'string', 'max:16'],

            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'socials' => ['nullable', 'array'],
            'socials.discord' => ['nullable', 'string', 'max:255'],
            'socials.telegram' => ['nullable', 'string', 'max:255'],
            'socials.youtube' => ['nullable', 'string', 'max:255'],
            'socials.vk' => ['nullable', 'string', 'max:255'],
            'socials.website' => ['nullable', 'string', 'max:255'],  // ← без 'url', чтобы не резало
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $validated['avatar'] = $request
                ->file('avatar')
                ->store("users/{$user->id}", 'public');
        }

        if ($request->hasFile('cover')) {
            if ($user->cover_path) {
                Storage::disk('public')->delete($user->cover_path);
            }

            $validated['cover_path'] = $request
                ->file('cover')
                ->store("users/{$user->id}/covers", 'public');
        }

        unset($validated['cover']);

        $user->update($validated);
        \App\Services\AchievementService::check($user);
        return response()->json(['user' => $user->fresh()]);
    }

    public function removeAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return response()->json(['user' => $user->fresh()]);
    }

    public function removeCover(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->cover_path) {
            Storage::disk('public')->delete($user->cover_path);
            $user->update(['cover_path' => null]);
        }

        return response()->json(['user' => $user->fresh()]);
    }

    public function updateAspects(Request $request): JsonResponse
    {
        $mode = $request->input('mode');

        if ($mode === 'pvp') {
            $validated = $request->validate([
                'block_placing' => ['required', 'integer', 'min:0', 'max:10'],
                'rotka' => ['required', 'integer', 'min:0', 'max:10'],
                'movement' => ['required', 'integer', 'min:0', 'max:10'],
                'aim' => ['required', 'integer', 'min:0', 'max:10'],
                'game_sense' => ['required', 'integer', 'min:0', 'max:10'],
            ]);

            $aspect = PlayerAspectPvp::updateOrCreate(
                ['user_id' => $request->user()->id],
                $validated
            );
        } else {
            $validated = $request->validate([
                'pvp' => ['required', 'integer', 'min:0', 'max:10'],
                'game_sense' => ['required', 'integer', 'min:0', 'max:10'],
                'bed_play' => ['required', 'integer', 'min:0', 'max:10'],
                'teamplay' => ['required', 'integer', 'min:0', 'max:10'],
                'building' => ['required', 'integer', 'min:0', 'max:10'],
            ]);

            $aspect = PlayerAspectBedwars::updateOrCreate(
                ['user_id' => $request->user()->id],
                $validated
            );
        }

        $user = $request->user();
        $pvp = $user->aspectPvp;
        $bw = $user->aspectBedwars;

        $bestPercent = max(
            $pvp?->percent() ?? 0,
            $bw?->percent() ?? 0
        );

        $bestTier = match (true) {
            $bestPercent >= 71 => 'A',
            $bestPercent >= 56 => 'B',
            $bestPercent >= 41 => 'C',
            $bestPercent >= 21 => 'D',
            default => 'E',
        };

        $user->tier_score = $bestPercent;
        $user->tier = $bestTier;
        $user->save();

        return response()->json(['aspect' => $aspect]);
    }
}
