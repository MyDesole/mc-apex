<?php

namespace App\Domains\Clan\Controllers;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanInvitation;
use App\Domains\Clan\Services\ClanInvitationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Приглашения в клан. Звать и отменять приглашения может руководство клана
 * (то же право, что у заявок); отвечать — приглашённый игрок.
 */
class ClanInvitationController extends Controller
{
    public function __construct(private readonly ClanInvitationService $invitations)
    {
    }

    /** Приглашения клана (для руководства). */
    public function index(Request $request, Clan $clan): JsonResponse
    {
        $this->authorize('reviewApplications', $clan);

        return response()->json([
            'invitations' => $this->invitations->forClan($clan)->map(fn (ClanInvitation $i) => [
                'id' => $i->id,
                'message' => $i->message,
                'expires_at' => $i->expires_at?->toIso8601String(),
                'created_at' => $i->created_at?->toIso8601String(),
                'user' => $i->user?->only(['id', 'username', 'avatar_url', 'tier']),
                'inviter' => $i->inviter?->only(['id', 'username']),
            ])->values(),
        ]);
    }

    public function store(Request $request, Clan $clan): JsonResponse
    {
        $this->authorize('reviewApplications', $clan);

        $data = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'message' => ['nullable', 'string', 'max:200'],
        ]);

        $invitation = $this->invitations->invite($clan, $request->user(), $data['username'], $data['message'] ?? null);

        return response()->json(['invitation' => ['id' => $invitation->id, 'user' => $invitation->user?->only(['id', 'username'])]], 201);
    }

    public function cancel(Request $request, Clan $clan, ClanInvitation $invitation): JsonResponse
    {
        $this->authorize('reviewApplications', $clan);

        $this->invitations->cancel($clan, $invitation);

        return response()->json(['ok' => true]);
    }

    /** Мои приглашения: показываем на странице кланов. */
    public function mine(Request $request): JsonResponse
    {
        return response()->json([
            'invitations' => $this->invitations->forUser($request->user())->map(fn (ClanInvitation $i) => [
                'id' => $i->id,
                'message' => $i->message,
                'expires_at' => $i->expires_at?->toIso8601String(),
                'inviter' => $i->inviter?->only(['id', 'username']),
                'clan' => $i->clan ? [
                    'id' => $i->clan->id,
                    'name' => $i->clan->name,
                    'tag' => $i->clan->tag,
                    'avatar_url' => $i->clan->avatar_url,
                    'banner_color' => $i->clan->banner_color,
                    'members_count' => (int) $i->clan->members_count,
                    'level' => (int) ($i->clan->level ?: 1),
                    'style' => $i->clan->style,
                ] : null,
            ])->values(),
        ]);
    }

    public function accept(Request $request, ClanInvitation $invitation): JsonResponse
    {
        $clan = $this->invitations->accept($invitation, $request->user());

        return response()->json(['ok' => true, 'clan_id' => $clan->id]);
    }

    public function decline(Request $request, ClanInvitation $invitation): JsonResponse
    {
        $this->invitations->decline($invitation, $request->user());

        return response()->json(['ok' => true]);
    }
}
