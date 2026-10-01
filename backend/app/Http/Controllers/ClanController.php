<?php

namespace App\Http\Controllers;

use App\Http\Requests\Clan\ApplyToClanRequest;
use App\Http\Requests\Clan\CreateClanRequest;
use App\Http\Controllers\Concerns\ResolvesFromUrl;
use App\Http\Requests\Clan\UpdateClanRequest;
use App\Http\Resources\ClanApplicationResource;
use App\Models\Clan;
use App\Models\ClanApplication;
use App\Models\User;
use App\Services\ClanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Кланы. Контроллер тонкий: валидация — в FormRequest,
 * права — в ClanPolicy, логика — в ClanService.
 */
class ClanController extends Controller
{
    use ResolvesFromUrl;

    public function __construct(
        private readonly ClanService $clans,
    ) {
    }

    /* ----------------------------- Списки и профиль ----------------------------- */

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->clans->list($request->query('search'), $request->user())
        );
    }

    public function top(): JsonResponse
    {
        return response()->json(['clans' => $this->clans->top()]);
    }

    /**
     * Клан открыт и гостям: принимаем и id, и имя в URL.
     */
    public function show(Request $request, string $clan): JsonResponse
    {
        return response()->json(
            $this->clans->view($this->resolveClan($clan), $request->user())
        );
    }

    /* ----------------------------- Создание и правка ----------------------------- */

    public function store(CreateClanRequest $request): JsonResponse
    {
        $clan = $this->clans->create($request->user(), $request->validated());

        return response()->json(['clan' => $clan], 201);
    }

    public function update(UpdateClanRequest $request, Clan $clan): JsonResponse
    {
        $this->authorize('update', $clan);

        $clan = $this->clans->update(
            clan: $clan,
            data: $request->safe()->except(['avatar', 'cover']),
            files: array_filter([
                'avatar' => $request->file('avatar'),
                'cover' => $request->file('cover'),
            ]),
        );

        return response()->json(['clan' => $clan]);
    }

    public function removeAvatar(Request $request, Clan $clan): JsonResponse
    {
        $this->authorize('removeMedia', $clan);

        return response()->json(['clan' => $this->clans->removeAvatar($clan)]);
    }

    public function removeCover(Request $request, Clan $clan): JsonResponse
    {
        $this->authorize('removeMedia', $clan);

        return response()->json(['clan' => $this->clans->removeCover($clan)]);
    }

    /* ----------------------------- Заявки ----------------------------- */

    public function apply(ApplyToClanRequest $request, Clan $clan): JsonResponse
    {
        $application = $this->clans->apply(
            clan: $clan,
            user: $request->user(),
            message: $request->input('message'),
        );

        return response()->json(['application' => $application], 201);
    }

    public function applications(Request $request, Clan $clan): JsonResponse
    {
        $this->authorize('reviewApplications', $clan);

        return response()->json([
            'applications' => ClanApplicationResource::collection($this->clans->applications($clan)),
        ]);
    }

    public function acceptApplication(Request $request, Clan $clan, ClanApplication $application): JsonResponse
    {
        $this->authorize('reviewApplications', $clan);
        $this->clans->assertApplicationBelongsToClan($clan, $application);

        $this->clans->acceptApplication($clan, $application);

        return response()->json(['ok' => true]);
    }

    public function declineApplication(Request $request, Clan $clan, ClanApplication $application): JsonResponse
    {
        $this->authorize('reviewApplications', $clan);
        $this->clans->assertApplicationBelongsToClan($clan, $application);

        $this->clans->declineApplication($application);

        return response()->json(['ok' => true]);
    }

    /* ----------------------------- Участники ----------------------------- */

    public function leave(Request $request, Clan $clan): JsonResponse
    {
        $this->clans->leave($clan, $request->user());

        return response()->json(['ok' => true]);
    }

    public function kick(Request $request, Clan $clan, User $user): JsonResponse
    {
        $this->authorize('kick', $clan);

        $this->clans->kick($clan, $user);

        return response()->json(['ok' => true]);
    }

    /* ----------------------------- Внутреннее ----------------------------- */


    /**
     * Клан из URL: число — это id, иначе имя.
     * Пробелы и подчёркивания взаимозаменяемы, регистр не важен.
     */
    private function resolveClan(string $value): Clan
    {
        return $this->resolveFromUrl(
            Clan::class,
            $value,
            'name',
            'Клан не найден.',
            $this->underscoreVariants(trim(rawurldecode($value))),
        );
    }
}
