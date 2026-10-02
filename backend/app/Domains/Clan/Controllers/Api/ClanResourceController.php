<?php

namespace App\Domains\Clan\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Clan\Requests\Clan\CreateClanResourceRequest;
use App\Domains\Clan\Models\ClanResource;
use App\Domains\Clan\Services\ClanResourceService;
use App\Domains\Clan\Support\ClanContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ресурсы клана. Контроллер тонкий: контекст клана — ClanContext,
 * логика — ClanResourceService, права — ClanResourcePolicy.
 */
class ClanResourceController extends Controller
{
    public function __construct(
        private readonly ClanResourceService $resources,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $clan = ClanContext::clan($request);

        return response()->json(
            $this->resources->list($clan, $request->query('category'))
        );
    }

    public function store(CreateClanResourceRequest $request): JsonResponse
    {
        $clan = ClanContext::clan($request);

        $resource = $this->resources->create(
            clan: $clan,
            author: $request->user(),
            data: $request->safe()->except('file'),
            file: $request->file('file'),
        );

        return response()->json(['resource' => $resource], 201);
    }

    public function download(Request $request, ClanResource $resource): JsonResponse
    {
        $this->resources->assertBelongsToClan($resource, ClanContext::clan($request));

        return response()->json(['url' => $this->resources->download($resource)]);
    }

    public function destroy(Request $request, ClanResource $resource): JsonResponse
    {
        $this->resources->assertBelongsToClan($resource, ClanContext::clan($request));

        $this->authorize('delete', $resource);

        $this->resources->delete($resource);

        return response()->json(['ok' => true]);
    }
}
