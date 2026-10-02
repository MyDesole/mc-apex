<?php

namespace App\Domains\Players\Controllers;

use App\Domains\Players\Requests\Player\UpdateAspectsRequest;
use App\Domains\Players\Requests\Player\UpdateMeRequest;
use App\Support\Concerns\ResolvesFromUrl;
use App\Domains\Players\Requests\Player\UpdateProfileRequest;
use App\Domains\Users\Models\User;
use App\Domains\Players\Services\PlayerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * Профиль игрока. Контроллер тонкий: валидация — в FormRequest,
 * логика и работа с файлами — в PlayerProfileService.
 */
class PlayerController extends Controller
{
    use ResolvesFromUrl;

    public function __construct(
        private readonly PlayerProfileService $profiles,
    ) {
    }

    /**
     * Профиль игрока. Открыт и гостям, поэтому $me может быть null.
     */
    public function show(Request $request, string $user): JsonResponse
    {
        $player = $this->resolveUser($user);

        return response()->json(
            $this->profiles->view($player, $request->user())
        );
    }

    public function updateMe(UpdateMeRequest $request): JsonResponse
    {
        $user = $this->profiles->updateMe(
            user: $request->user(),
            data: $request->safe()->except(['avatar', 'cover']),
            files: array_filter([
                'avatar' => $request->file('avatar'),
                'cover' => $request->file('cover'),
            ]),
        );

        return response()->json(['user' => $user]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profiles->updateProfile(
            user: $request->user(),
            data: $request->safe()->except(['card_background']),
            files: array_filter(['card_background' => $request->file('card_background')]),
        );

        return response()->json(['user' => $user]);
    }

    public function updateAspects(UpdateAspectsRequest $request): JsonResponse
    {
        $data = $request->validated();

        // mode — это переключатель набора полей, в саму запись он не входит
        unset($data['mode']);

        $result = $this->profiles->updateAspects(
            user: $request->user(),
            mode: $request->mode(),
            values: $data,
        );

        return response()->json($result);
    }

    public function removeAvatar(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->profiles->removeAvatar($request->user())]);
    }

    public function removeCover(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->profiles->removeCover($request->user())]);
    }

    public function removeCardBackground(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->profiles->removeCardBackground($request->user()),
        ]);
    }

    /**
     * Игрок из URL: число — это id, иначе ник (без учёта регистра).
     */
    private function resolveUser(string $value): User
    {
        return $this->resolveFromUrl(User::class, $value, 'username', 'Игрок не найден.');
    }
}
