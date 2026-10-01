<?php

namespace App\Http\Controllers;

use App\Http\Requests\Clan\ChallengeClanRequest;
use App\Http\Requests\Clan\CompleteClanWarRequest;
use App\Models\Clan;
use App\Models\ClanWar;
use App\Services\ClanWarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Войны кланов. Контроллер тонкий: валидация — в FormRequest,
 * права и правила — в ClanWarService.
 */
class ClanWarController extends Controller
{
    public function __construct(
        private readonly ClanWarService $wars,
    ) {
    }

    public function store(ChallengeClanRequest $request, Clan $clan): JsonResponse
    {
        $war = $this->wars->challenge($request->user(), $clan, $request->validated());

        return response()->json(['war' => $war], 201);
    }

    public function accept(Request $request, ClanWar $war): JsonResponse
    {
        return response()->json([
            'war' => $this->wars->accept($request->user(), $war),
        ]);
    }

    public function decline(Request $request, ClanWar $war): JsonResponse
    {
        $this->wars->decline($request->user(), $war);

        return response()->json(['ok' => true]);
    }

    public function complete(CompleteClanWarRequest $request, ClanWar $war): JsonResponse
    {
        return response()->json([
            'war' => $this->wars->complete($request->user(), $war, $request->validated()),
        ]);
    }

    public function join(Request $request, ClanWar $war): JsonResponse
    {
        $this->wars->join($request->user(), $war);

        return response()->json(['ok' => true]);
    }

    public function leave(Request $request, ClanWar $war): JsonResponse
    {
        $this->wars->leave($request->user(), $war);

        return response()->json(['ok' => true]);
    }

    public function show(Request $request, ClanWar $war): JsonResponse
    {
        return response()->json($this->wars->view($request->user(), $war));
    }
}
