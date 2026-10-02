<?php

namespace App\Domains\Clan\Controllers;

use App\Domains\Clan\Requests\Clan\CreateClanEventRequest;
use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanEvent;
use App\Domains\Clan\Services\ClanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * События клана. Контроллер тонкий: дублирующая проверка руководства
 * убрана — используется ClanService::isStaff().
 */
class ClanEventController extends Controller
{
    public function __construct(
        private readonly ClanService $clans,
    ) {
    }

    public function index(Clan $clan): JsonResponse
    {
        return response()->json(
            $clan->events()->with('author:id,username,avatar')->paginate(20)
        );
    }

    public function store(CreateClanEventRequest $request, Clan $clan): JsonResponse
    {
        abort_unless(
            $request->user()->can('createEvent', $clan),
            403,
            'Создавать события может руководство клана.'
        );

        $event = ClanEvent::create([
            ...$request->validated(),
            'clan_id' => $clan->id,
            'author_id' => $request->user()->id,
        ]);

        return response()->json(['event' => $event->load('author:id,username,avatar')], 201);
    }

    public function destroy(Request $request, Clan $clan, ClanEvent $event): JsonResponse
    {
        abort_if($event->clan_id !== $clan->id, 404);

        // Офицер может создавать события, значит должен уметь и удалять:
        // раньше удалить можно было только своё или будучи лидером.
        abort_unless(
            $request->user()->can('deleteEvent', [$clan, $event->author_id]),
            403,
            'Нет прав на удаление события.'
        );

        $event->delete();

        return response()->json(['ok' => true]);
    }
}
