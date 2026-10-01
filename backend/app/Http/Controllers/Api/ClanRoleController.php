<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clan\UpdateClanRoleRequest;
use App\Models\Clan;
use App\Models\User;
use App\Services\ClanService;
use App\Support\ClanContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Роли внутри клана. Контроллер тонкий: контекст клана — ClanContext,
 * правила — ClanService, транзакция передачи лидерства — там же.
 */
class ClanRoleController extends Controller
{
    public function __construct(
        private readonly ClanService $clans,
    ) {
    }

    public function update(UpdateClanRoleRequest $request, User $user): JsonResponse
    {
        $clan = ClanContext::clan($request);
        $me = ClanContext::membership($request);

        // Право проверяет политика, а abort даёт понятное сообщение вместо
        // стандартного «This action is unauthorized».
        abort_unless(
            $request->user()->can('manageRoles', $clan),
            403,
            'Только лидер может менять роли.'
        );

        $member = $this->clans->updateMemberRole($clan, $request->user(), $user, $request->validated());

        return response()->json(['member' => $member]);
    }

    public function kick(Request $request, User $user): JsonResponse
    {
        $clan = ClanContext::clan($request);
        $me = ClanContext::membership($request);

        $this->authorize('kick', $clan);

        $this->clans->kick($clan, $user);

        return response()->json(['ok' => true]);
    }

    public function transferLeadership(Request $request, User $user): JsonResponse
    {
        $clan = ClanContext::clan($request);
        $me = ClanContext::membership($request);

        abort_unless(
            $request->user()->can('manageRoles', $clan),
            403,
            'Только лидер может передать лидерство.'
        );

        $this->clans->transferLeadership($clan, $request->user(), $user);

        return response()->json(['ok' => true]);
    }
}
