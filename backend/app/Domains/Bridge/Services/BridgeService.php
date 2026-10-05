<?php

namespace App\Domains\Bridge\Services;

use App\Domains\Bridge\Models\BridgeTechnique;
use App\Domains\Bridge\Models\UserBridgeTechnique;
use App\Domains\Bridge\Notifications\BridgeTechniqueReviewedNotification;
use App\Domains\Users\Models\User;
use App\Support\Concerns\AbortsWithMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Бридж-тесты: виды бриджа, заявки игроков и проверка тестером.
 *
 * Механика отличается от обычных тир-тестов: записи в очередь нет — игрок
 * отмечает, что умеет, и прикладывает видео. Тестер проверяет видео и
 * ставит оценки. До подтверждения вид в профиле показан серым.
 */
class BridgeService
{
    use AbortsWithMessage;

    /** Сколько подтверждённых видов приносит один «вес» при ранжировании. */
    private const TECHNIQUE_WEIGHT = 1000;

    /* ----------------------------- Каталог ----------------------------- */

    /** Активные виды бриджа по порядку. */
    public function techniques(): Collection
    {
        return BridgeTechnique::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Виды бриджа вместе с заявкой игрока.
     *
     * Отдаём весь каталог, а не только заполненные виды: так профиль
     * показывает и то, что игрок ещё не заявил, но может.
     */
    public function forUser(User $user): Collection
    {
        $mine = UserBridgeTechnique::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('technique_id');

        return $this->techniques()->map(fn (BridgeTechnique $technique) => [
            'technique' => $technique,
            'submission' => $mine->get($technique->id),
        ]);
    }

    /** Только подтверждённые виды игрока — они попадают в топ. */
    public function confirmedForUser(User $user): Collection
    {
        return UserBridgeTechnique::query()
            ->where('user_id', $user->id)
            ->where('status', UserBridgeTechnique::STATUS_CONFIRMED)
            ->with('technique')
            ->get();
    }

    /* ----------------------------- Подача ----------------------------- */

    /**
     * Игрок отмечает вид бриджа и прикладывает видео.
     *
     * Повторная подача перезаписывает видео: если тестер отклонил заявку,
     * игрок может прислать новый ролик.
     */
    public function declare(User $user, int $techniqueId, ?string $videoUrl): UserBridgeTechnique
    {
        $technique = BridgeTechnique::query()
            ->where('is_active', true)
            ->find($techniqueId);

        if (! $technique) {
            $this->abortUnprocessable('Такого вида бриджа нет.');
        }

        $existing = UserBridgeTechnique::query()
            ->where('user_id', $user->id)
            ->where('technique_id', $technique->id)
            ->first();

        if ($existing && $existing->isConfirmed()) {
            $this->abortUnprocessable(
                'Этот вид уже подтверждён тестером. Повторная подача не нужна.'
            );
        }

        return UserBridgeTechnique::updateOrCreate(
            ['user_id' => $user->id, 'technique_id' => $technique->id],
            [
                'status' => UserBridgeTechnique::STATUS_DECLARED,
                'video_url' => $videoUrl,
                // Прошлые оценки тестера сбрасываются: видео новое
                'stability' => null,
                'speed' => null,
                'difficulty' => null,
                'score' => null,
                'review_notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ],
        );
    }

    /** Игрок убирает свою заявку. Подтверждённую убрать нельзя. */
    public function withdraw(User $user, UserBridgeTechnique $submission): void
    {
        if ($submission->user_id !== $user->id) {
            $this->abortForbidden('Это не ваша заявка.');
        }

        if ($submission->isConfirmed()) {
            $this->abortUnprocessable('Подтверждённый вид убрать нельзя.');
        }

        $submission->delete();
    }

    /* ----------------------------- Проверка ----------------------------- */

    /** Заявки, ожидающие проверки. */
    public function pending(): Collection
    {
        return UserBridgeTechnique::query()
            ->where('status', UserBridgeTechnique::STATUS_DECLARED)
            ->with([
                'user:id,username,avatar,tier,tier_score',
                'user.clanMember.clan:id,name,tag,banner_color',
                'technique',
            ])
            ->latest('updated_at')
            ->get();
    }

    /**
     * Тестер подтверждает вид и ставит оценки.
     *
     * @param  array{stability:int, speed:int, difficulty:int, score:int, notes?:?string}  $data
     */
    public function confirm(UserBridgeTechnique $submission, User $reviewer, array $data): UserBridgeTechnique
    {
        $this->assertAwaitingReview($submission);

        $submission->update([
            'status' => UserBridgeTechnique::STATUS_CONFIRMED,
            'stability' => $data['stability'],
            'speed' => $data['speed'],
            'difficulty' => $data['difficulty'],
            'score' => $data['score'],
            'review_notes' => $data['notes'] ?? null,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $this->notify($submission->fresh(), true);

        return $submission->fresh();
    }

    /** Тестер отклоняет заявку с причиной. */
    public function reject(UserBridgeTechnique $submission, User $reviewer, ?string $notes): UserBridgeTechnique
    {
        $this->assertAwaitingReview($submission);

        $submission->update([
            'status' => UserBridgeTechnique::STATUS_REJECTED,
            'review_notes' => $notes,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $this->notify($submission->fresh(), false);

        return $submission->fresh();
    }

    private function assertAwaitingReview(UserBridgeTechnique $submission): void
    {
        if ($submission->status !== UserBridgeTechnique::STATUS_DECLARED) {
            $this->abortUnprocessable('Эта заявка уже проверена.');
        }
    }

    private function notify(UserBridgeTechnique $submission, bool $confirmed): void
    {
        $submission->loadMissing('technique', 'user');

        $submission->user?->notify(
            new BridgeTechniqueReviewedNotification($submission, $confirmed)
        );
    }

    /* ----------------------------- Топ ----------------------------- */

    /**
     * Место в бридже: сначала число подтверждённых видов, затем сумма
     * аспектов. Одно число, чтобы работала общая курсорная пагинация.
     */
    public function scoreExpression(): string
    {
        $count = $this->confirmedCountExpression();
        $total = $this->aspectsTotalExpression();

        return '(' . $count . ' * ' . self::TECHNIQUE_WEIGHT . ' + ' . $total . ')';
    }

    public function confirmedCountExpression(): string
    {
        return "COALESCE((
            SELECT COUNT(*) FROM user_bridge_techniques ubt
            WHERE ubt.user_id = users.id AND ubt.status = 'confirmed'
        ), 0)";
    }

    public function aspectsTotalExpression(): string
    {
        return "COALESCE((
            SELECT SUM(COALESCE(ubt.stability, 0) + COALESCE(ubt.speed, 0) + COALESCE(ubt.difficulty, 0))
            FROM user_bridge_techniques ubt
            WHERE ubt.user_id = users.id AND ubt.status = 'confirmed'
        ), 0)";
    }

    /** Сводка игрока для профиля: подтверждённые виды и их сумма. */
    public function summary(User $user): array
    {
        $rows = $this->confirmedForUser($user);

        return [
            'confirmed_count' => $rows->count(),
            'aspects_total' => (int) $rows->sum(fn (UserBridgeTechnique $row) => $row->total),
            'score_total' => (int) $rows->sum(fn (UserBridgeTechnique $row) => (int) $row->score),
        ];
    }

    /** Пересчитывает ничего не хранящегося: топ считается запросом. */
    public function rankOf(User $user): array
    {
        $summary = $this->summary($user);

        if ($summary['confirmed_count'] === 0) {
            return [null, 0];
        }

        $score = $summary['confirmed_count'] * self::TECHNIQUE_WEIGHT + $summary['aspects_total'];

        $ahead = User::query()
            ->excludeStaff()
            ->whereRaw($this->scoreExpression() . ' > ?', [$score])
            ->count();

        $total = User::query()
            ->excludeStaff()
            ->whereRaw($this->confirmedCountExpression() . ' > 0')
            ->count();

        return [$ahead + 1, $total];
    }
}
