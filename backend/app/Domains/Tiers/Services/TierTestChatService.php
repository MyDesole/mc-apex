<?php

namespace App\Domains\Tiers\Services;

use App\Domains\Tiers\Models\TierTest;
use App\Domains\Tiers\Models\TierTestMessage;
use App\Domains\Tiers\Notifications\TierTestMessageNotification;
use App\Domains\Users\Models\User;
use App\Support\Concerns\AbortsWithMessage;

/**
 * Чат тестируемого с тестером в рамках заявки.
 *
 * Правила:
 *  - переписка открывается, когда тестер взял заявку в работу;
 *  - первым пишет тестер, пока он не написал — тестируемый писать не может;
 *  - после завершения или отмены заявки чат остаётся для чтения.
 */
class TierTestChatService
{
    use AbortsWithMessage;

    public const MAX_LENGTH = 1000;

    /** Сколько сообщений отдаём за раз при первой загрузке. */
    private const HISTORY_LIMIT = 200;

    /* ----------------------------- Доступ ----------------------------- */

    /** Роль пользователя в заявке: player, tester или null, если он посторонний. */
    public function roleOf(TierTest $test, User $user): ?string
    {
        if ($test->user_id === $user->id) {
            return 'player';
        }

        if ($test->claimed_by === $user->id || $test->tester_id === $user->id) {
            return 'tester';
        }

        return null;
    }

    private function assertParticipant(TierTest $test, User $user): string
    {
        $role = $this->roleOf($test, $user);

        abort_if($role === null && ! $user->isAdmin(), 403, 'Нет доступа к чату этой заявки.');

        return $role ?? 'observer';
    }

    /* ----------------------------- Чтение ----------------------------- */

    /**
     * Сообщения чата и состояние: можно ли писать и почему нельзя.
     *
     * @param  int  $afterId  отдать только сообщения новее этого id (опрос)
     */
    public function thread(TierTest $test, User $user, int $afterId = 0): array
    {
        $role = $this->assertParticipant($test, $user);

        $query = TierTestMessage::query()
            ->where('tier_test_id', $test->id)
            ->with('user:id,username,avatar')
            ->orderBy('id');

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        } else {
            // Последние сообщения, но в хронологическом порядке
            $query->whereIn('id', TierTestMessage::query()
                ->where('tier_test_id', $test->id)
                ->orderByDesc('id')
                ->limit(self::HISTORY_LIMIT)
                ->select('id'));
        }

        $messages = $query->get();

        // Открыв чат, участник «прочитал» всё, что написал собеседник
        if ($role !== 'observer') {
            TierTestMessage::query()
                ->where('tier_test_id', $test->id)
                ->where('user_id', '!=', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        [$canSend, $reason] = $this->sendState($test, $role);

        $test->loadMissing('tester:id,username,avatar', 'user:id,username,avatar');

        return [
            'tier_test_id' => $test->id,
            'status' => $test->status,
            'role' => $role,
            'can_send' => $canSend,
            'reason' => $reason,
            'player' => $test->user?->only(['id', 'username', 'avatar_url']),
            'tester' => $test->tester?->only(['id', 'username', 'avatar_url']),
            'messages' => $messages->map(fn (TierTestMessage $m) => $this->present($m, $user))->values(),
            'max_length' => self::MAX_LENGTH,
        ];
    }

    private function present(TierTestMessage $m, User $viewer): array
    {
        return [
            'id' => $m->id,
            'user_id' => $m->user_id,
            'username' => $m->user?->username,
            'avatar_url' => $m->user?->avatar_url,
            'body' => $m->body,
            'mine' => $m->user_id === $viewer->id,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    /**
     * Можно ли писать и, если нет, что сказать пользователю.
     *
     * @return array{0: bool, 1: ?string}
     */
    private function sendState(TierTest $test, string $role): array
    {
        if ($role === 'observer') {
            return [false, 'Вы наблюдаете за чатом как администратор.'];
        }

        if (! in_array($test->status, ['pending', 'in_progress'], true)) {
            return [false, 'Тест завершён, чат доступен только для чтения.'];
        }

        if ($test->status === 'pending' || ! $test->claimed_by) {
            return [false, $role === 'player'
                ? 'Чат откроется, когда тестер возьмёт вашу заявку в работу.'
                : 'Возьмите заявку в работу, чтобы написать игроку.'];
        }

        if ($role === 'player' && ! $this->testerHasWritten($test)) {
            return [false, 'Сначала напишет тестер — вы сможете ответить сразу после его сообщения.'];
        }

        return [true, null];
    }

    private function testerHasWritten(TierTest $test): bool
    {
        return TierTestMessage::query()
            ->where('tier_test_id', $test->id)
            ->where('user_id', '!=', $test->user_id)
            ->exists();
    }

    /* ----------------------------- Отправка ----------------------------- */

    public function send(TierTest $test, User $user, string $body): array
    {
        $role = $this->assertParticipant($test, $user);

        [$canSend, $reason] = $this->sendState($test, $role);

        if (! $canSend) {
            $this->abortUnprocessable($reason ?? 'Сейчас писать нельзя.');
        }

        $body = trim(preg_replace('/\s+/u', ' ', $body) ?? '');

        if ($body === '') {
            $this->invalid('body', 'Сообщение пустое.');
        }

        if (mb_strlen($body) > self::MAX_LENGTH) {
            $this->invalid('body', 'Сообщение длиннее ' . self::MAX_LENGTH . ' символов.');
        }

        $message = TierTestMessage::create([
            'tier_test_id' => $test->id,
            'user_id' => $user->id,
            'body' => $body,
        ]);

        $recipient = $role === 'tester' ? $test->user : ($test->claimer ?? $test->tester);

        if ($recipient && $recipient->id !== $user->id) {
            $recipient->notify(new TierTestMessageNotification(
                tierTest: $test,
                message: $message,
                senderName: $user->username,
                toTester: $role === 'player',
            ));
        }

        $message->setRelation('user', $user);

        return $this->present($message, $user);
    }

    /* ----------------------------- Сводка ----------------------------- */

    /**
     * Чаты пользователя, которые идут прямо сейчас: заявка игрока и
     * заявки в работе у тестера. Для плавающей кнопки и бейджей.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activeFor(User $user): array
    {
        $tests = TierTest::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere(fn ($q2) => $q2->where('claimed_by', $user->id)->where('status', 'in_progress'));
            })
            ->with('user:id,username,avatar', 'tester:id,username,avatar')
            ->get();

        if ($tests->isEmpty()) {
            return [];
        }

        $unread = TierTestMessage::query()
            ->whereIn('tier_test_id', $tests->pluck('id'))
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->selectRaw('tier_test_id, COUNT(*) as total')
            ->groupBy('tier_test_id')
            ->pluck('total', 'tier_test_id');

        return $tests->map(function (TierTest $t) use ($user, $unread) {
            $role = $t->user_id === $user->id ? 'player' : 'tester';
            $other = $role === 'player' ? $t->tester : $t->user;

            return [
                'tier_test_id' => $t->id,
                'role' => $role,
                'status' => $t->status,
                'mode' => $t->mode,
                'other' => $other?->only(['id', 'username', 'avatar_url']),
                'unread' => (int) ($unread[$t->id] ?? 0),
            ];
        })->values()->all();
    }
}
