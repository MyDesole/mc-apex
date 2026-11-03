<?php

namespace App\Domains\Minecraft\Controllers\Api;

use App\Domains\Minecraft\Services\MinecraftLinkService;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Tiers\Services\TierTestService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Эндпоинты для плагина майнкрафт-сервера.
 *
 * Доступ только с общим секретом сервера. Пароль приходит по HTTPS, сразу
 * сверяется с хешем и нигде не сохраняется и не логируется.
 */
class MinecraftServerController extends Controller
{
    public function __construct(private readonly MinecraftLinkService $links)
    {
    }

    /**
     * Заявлен ли ник и на какой аккаунт.
     *
     * По этому ответу плагин решает: просить пароль или отправить игрока
     * заявить ник в профиле.
     */
    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nickname' => ['required', 'string', 'max:32'],
        ]);

        return response()->json($this->links->resolveNickname($data['nickname']));
    }

    /* ---------------- Тир-тесты для плагина ---------------- */

    /**
     * Заявки тестера. Результат тестер выставляет на сайте, плагин только
     * показывает, кого и в каком режиме тестировать.
     */
    public function tierTests(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tester' => ['nullable', 'string', 'max:32'],
        ]);

        $query = TierTest::query()
            ->whereIn('status', ['pending', 'claimed', 'in_progress'])
            ->with(['user:id,minecraft_username', 'tester:id,minecraft_username']);

        if (! empty($data['tester'])) {
            $nick = (string) $data['tester'];

            $query->whereHas('tester', function ($q) use ($nick) {
                $q->where('minecraft_username', $nick);
            });
        }

        $rows = $query
            ->orderByDesc('is_priority')
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'tests' => $rows->map(fn (TierTest $test) => [
                'id' => $test->id,
                'nickname' => $test->user?->minecraft_username,
                'account' => $test->user?->minecraft_username,
                'tester' => $test->tester?->minecraft_username,
                'mode' => $test->mode,
                'expected_tier' => $test->expected_tier,
                'status' => $test->status,
                'priority' => (bool) $test->is_priority,
                'scheduled_at' => $test->scheduled_at?->toIso8601String(),
                'created_at' => $test->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * Тестер пригласил игрока на дуэли. Свободную заявку закрепляем за ним,
     * чужую в работе не трогаем.
     */
    public function tierTestInvite(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'tester' => ['required', 'string', 'max:32'],
            'kits' => ['nullable', 'array', 'max:20'],
            'kits.*' => ['string', 'max:64'],
            'rounds' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $test = TierTest::find($id);

        if (! $test) {
            return response()->json(['message' => 'Тест не найден.'], 404);
        }

        $tester = User::query()->where('minecraft_username', $data['tester'])->first();

        if (! $tester) {
            return response()->json(['message' => 'Ник тестера не привязан к аккаунту.'], 422);
        }

        if ($test->status === 'pending') {
            app(TierTestService::class)->claim($test, $tester);
            $test->refresh();
        } elseif ($test->status !== 'in_progress' || (int) $test->tester_id !== $tester->id) {
            return response()->json(['message' => 'Заявка не ваша или уже закрыта.'], 422);
        }

        return response()->json([
            'ok' => true,
            'test' => ['id' => $test->id, 'status' => $test->status],
        ]);
    }

    /**
     * Серия дуэлей закончена. Счёт и режимы пишем в заметки заявки, тир
     * тестер ставит на сайте по аспектам.
     */
    public function tierTestFinish(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'tester' => ['nullable', 'string', 'max:32'],
            'subject_wins' => ['required', 'integer', 'min:0'],
            'tester_wins' => ['required', 'integer', 'min:0'],
            'draws' => ['nullable', 'integer', 'min:0'],
            'planned' => ['nullable', 'integer', 'min:0'],
            'completed' => ['nullable', 'boolean'],
            'games' => ['nullable', 'array', 'max:100'],
            'games.*.kit' => ['required', 'string', 'max:64'],
            'games.*.winner' => ['required', 'in:subject,tester,none'],
        ]);

        $test = TierTest::find($id);

        if (! $test) {
            return response()->json(['message' => 'Тест не найден.'], 404);
        }

        if (in_array($test->status, ['completed', 'cancelled'], true)) {
            return response()->json(['message' => 'Заявка уже закрыта.'], 422);
        }

        $lines = [sprintf(
            'Дуэли на сервере: игрок %d : %d тестер, ничьих %d%s.',
            $data['subject_wins'],
            $data['tester_wins'],
            $data['draws'] ?? 0,
            ($data['completed'] ?? true) ? '' : ' (серия прервана)'
        )];

        foreach ($data['games'] ?? [] as $i => $game) {
            $who = ['subject' => 'игрок', 'tester' => 'тестер', 'none' => 'ничья'][$game['winner']];
            $lines[] = sprintf('%d. %s - %s', $i + 1, $game['kit'], $who);
        }

        $test->update([
            'notes' => trim(($test->notes ? $test->notes . "\n\n" : '') . implode("\n", $lines)),
        ]);

        return response()->json([
            'ok' => true,
            'test' => ['id' => $test->id, 'status' => $test->status],
        ]);
    }
    /** Скин игрока для сервера: ссылка на PNG и модель рук. */
    public function skin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nick' => ['required', 'string', 'max:32'],
        ]);

        $user = User::query()->where('minecraft_username', $data['nick'])->first();

        if (! $user) {
            return response()->json(['url' => null, 'model' => null]);
        }

        // Без query-параметров: клиент 1.8 качает текстуру по адресу как есть,
        // а ссылку с ?v= он может не принять.
        return response()->json([
            'url' => url('/api/players/' . rawurlencode((string) $user->username) . '/skin'),
            'model' => $user->custom_skin_model ?: 'classic',
        ]);
    }

    /** Тиры по никам — для чата и таблисты. */
    public function tiers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nicks' => ['required', 'string', 'max:2000'],
        ]);

        $nicks = collect(explode(',', (string) $data['nicks']))
            ->map(fn (string $nick) => trim($nick))
            ->filter()
            ->unique()
            ->take(100)
            ->values();

        $rows = User::query()
            ->whereIn('minecraft_username', $nicks)
            ->get(['minecraft_username', 'tier']);

        return response()->json([
            'tiers' => $rows->map(fn (User $user) => [
                'nick' => $user->minecraft_username,
                'account' => $user->minecraft_username,
                'tier' => $user->tier,
            ])->all(),
        ]);
    }

    /** Вход по нику и паролю сайта. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nickname' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:255'],
            'uuid' => ['nullable', 'string', 'max:36'],
        ]);

        $uuid = isset($data['uuid']) && $data['uuid'] !== ''
                ? $this->links->normalizeUuid($data['uuid'])
                : null;

        $result = $this->links->login($data['nickname'], $data['password'], $uuid);

        $payload = [
            'ok' => $result['ok'],
            'reason' => $result['reason'],
            'attempts_left' => $result['attempts_left'],
            'retry_after' => $result['retry_after'],
        ];

        if (! $result['ok']) {
            // 429 на блокировку, 401 на неверный пароль
            return response()->json($payload, $result['reason'] === 'throttled' ? 429 : 401);
        }

        $payload['player'] = $this->links->profilePayload($result['user']);

        return response()->json($payload);
    }

    /** Данные игрока для показа в игре. */
    public function profile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nickname' => ['required', 'string', 'max:32'],
        ]);

        $profile = $this->links->profileByNickname($data['nickname']);

        if (! $profile) {
            return response()->json(['claimed' => false], 404);
        }

        return response()->json([
            'claimed' => true,
            'player' => $profile,
        ]);
    }

    /**
     * На какой аккаунт заявлен ник.
     *
     * Нужно админу, когда игрок не может зайти: в offline-режиме доказать
     * принадлежность ника нельзя, поэтому решает админ.
     */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nickname' => ['required', 'string', 'max:32'],
        ]);

        $found = $this->links->lookupByNickname($data['nickname']);

        return response()->json($found ?? [
            'claimed' => false,
            'nickname' => $data['nickname'],
        ]);
    }

    /** Освободить ник от аккаунта. */
    public function unlink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nickname' => ['required', 'string', 'max:32'],
        ]);

        $done = $this->links->adminRelease($data['nickname']);

        return response()->json([
            'ok' => $done,
            'nickname' => $data['nickname'],
        ]);
    }
}
