<?php

namespace App\Domains\Players\Controllers\Api;

use App\Domains\Players\Models\GuestbookEntry;
use App\Domains\Players\Services\ProfileBlocksService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Конструктор профиля: сохранение раскладки и гостевая книга.
 */
class ProfileBuilderController extends Controller
{
    public function __construct(private readonly ProfileBlocksService $blocks)
    {
    }

    /**
     * Сохранить раскладку блоков и настройки страницы;
     * blocks = null — профиль по умолчанию.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'blocks' => ['present', 'nullable', 'array', 'max:' . ProfileBlocksService::MAX_BLOCKS],
            'page' => ['sometimes', 'nullable', 'array'],
        ]);

        $saved = $this->blocks->save($request->user(), $request->input('blocks'), $request->input('page'));

        return response()->json([
            'message' => $saved === null ? 'Профиль вернулся к обычному виду.' : 'Профиль сохранён.',
            'blocks' => $saved,
            'page' => $request->user()->profile_page,
        ]);
    }

    /** Карточки тиммейтов: ?ids=1,2,3 (до 8 игроков). */
    public function teammates(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->take(8)
            ->values()
            ->all();

        return response()->json(['players' => $this->blocks->teammates($ids)]);
    }

    public function poll(Request $request, User $user, string $block): JsonResponse
    {
        return response()->json($this->blocks->pollResults($user, $block, $request->user('sanctum')));
    }

    public function votePoll(Request $request, User $user, string $block): JsonResponse
    {
        $data = $request->validate(['option' => ['required', 'integer', 'min:0', 'max:3']]);

        return response()->json($this->blocks->vote($user, $request->user(), $block, $data['option']));
    }

    public function guestbook(User $user): JsonResponse
    {
        return response()->json(['entries' => $this->blocks->guestbook($user)]);
    }

    public function writeGuestbook(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:200']]);

        $entry = $this->blocks->write($user, $request->user(), $data['body']);

        return response()->json(['entry' => $entry], 201);
    }

    public function deleteGuestbook(Request $request, GuestbookEntry $entry): JsonResponse
    {
        $this->blocks->remove($entry, $request->user());

        return response()->json(['message' => 'Запись удалена.']);
    }
}
