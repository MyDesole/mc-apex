<?php

namespace App\Domains\Tiers\Controllers;

use App\Domains\Tiers\Models\TierTest;
use App\Domains\Tiers\Models\TierTestAppeal;
use App\Domains\Tiers\Requests\Appeal\ResolveAppealRequest;
use App\Domains\Tiers\Requests\Appeal\SubmitAppealRequest;
use App\Domains\Tiers\Services\TierTestAppealService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Апелляции: подача игроком и рассмотрение модератором.
 */
class TierTestAppealController extends Controller
{
    private const RELATIONS = [
        'tierTest:id,user_id,tester_id,mode,result_tier,result_score,aspects,notes,completed_at',
        'tierTest.tester:id,username',
        'revisedTest:id,result_tier,result_score',
        'user:id,username,avatar,tier,bridge_primary,bridge_rank_id,profile_mode',
        'reviewer:id,username',
    ];

    public function __construct(private TierTestAppealService $appeals) {}

    /** Апелляции текущего игрока. */
    public function mine(Request $request): JsonResponse
    {
        $appeals = TierTestAppeal::where('user_id', $request->user()->id)
            ->with(self::RELATIONS)
            ->latest()
            ->get();

        return response()->json(['appeals' => $appeals]);
    }

    public function store(SubmitAppealRequest $request, TierTest $tierTest): JsonResponse
    {
        $appeal = $this->appeals->submit($tierTest, $request->user(), $request->validated('reason'));

        return response()->json(['appeal' => $appeal->load(self::RELATIONS)], 201);
    }

    /** Очередь на рассмотрение (модератор, админ). */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', TierTestAppeal::STATUS_PENDING);

        $appeals = TierTestAppeal::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(self::RELATIONS)
            ->oldest()
            ->limit(100)
            ->get();

        return response()->json(['appeals' => $appeals]);
    }

    public function resolve(ResolveAppealRequest $request, TierTestAppeal $appeal): JsonResponse
    {
        $appeal = $this->appeals->resolve(
            $appeal,
            $request->user(),
            $request->validated('decision'),
            $request->validated('resolution'),
            $request->validated('aspects'),
        );

        return response()->json(['appeal' => $appeal->load(self::RELATIONS)]);
    }
}
