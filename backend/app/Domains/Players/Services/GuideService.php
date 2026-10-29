<?php

namespace App\Domains\Players\Services;

use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Путеводитель по сайту: шаг за шагом показывает рейтинг, тир-тест или
 * бридж, профиль, кланы, турниры, магазин, питомцев, арену, аукцион,
 * топы и шахту.
 *
 * Стартовые 10 000 ApexCoin выдаются за первые шаги — ровно столько,
 * сколько стоит сундук питомцев, к которому путеводитель ведёт дальше.
 * Монеты за тир-тест — за то, что игрок узнал, как записаться, а не за
 * заявку: иначе очередь тестеров забьют пустыми заявками.
 *
 * Чтобы стартовые монеты не перегоняли на основной аккаунт, неделю после
 * старта путеводителя нельзя дарить монеты и торговать на аукционе.
 *
 * Тексты и подсветка шагов — на клиенте; здесь порядок, награды и
 * проверки, которые можно проверить по базе.
 */
class GuideService
{
    /** Шаг => награда и как понять, что он пройден (seen — игрок посмотрел). */
    public const STEPS = [
        'welcome' => ['reward' => 6000, 'check' => 'seen'],
        'rating' => ['reward' => 2000, 'check' => 'seen'],
        'test' => ['reward' => 2000, 'check' => 'seen'],
        'profile' => ['reward' => 0, 'check' => 'seen'],
        'clans' => ['reward' => 0, 'check' => 'seen'],
        'tournaments' => ['reward' => 0, 'check' => 'seen'],
        'shop' => ['reward' => 0, 'check' => 'seen'],
        'pet' => ['reward' => 0, 'check' => 'pet'],
        'pets' => ['reward' => 0, 'check' => 'seen'],
        'expedition' => ['reward' => 0, 'check' => 'expedition'],
        'arena' => ['reward' => 0, 'check' => 'battle'],
        'auction' => ['reward' => 0, 'check' => 'seen'],
        'tops' => ['reward' => 0, 'check' => 'seen'],
        'miner' => ['reward' => 0, 'check' => 'miner'],
    ];

    /** Сколько дней после старта нельзя дарить монеты и торговать на аукционе. */
    public const TRADE_LOCK_DAYS = 7;

    public function __construct(private readonly PetHubService $pets)
    {
    }

    public function state(User $user): array
    {
        $row = $this->row($user);

        // Шаги, которые видно по базе, засчитываются сами, как только сделаны
        if ($row && ! $row->finished_at) {
            $row = $this->advance($user, $row);
        }

        $steps = $row ? (json_decode($row->steps ?? '[]', true) ?: []) : [];
        $current = $row ? $this->current($steps) : null;

        return [
            'started' => (bool) $row,
            'hidden' => (bool) ($row->hidden ?? false),
            'finished' => (bool) ($row->finished_at ?? false),
            'current' => $current,
            'steps' => array_map(fn ($id) => [
                'id' => $id,
                'reward' => self::STEPS[$id]['reward'],
                'auto' => self::STEPS[$id]['check'] !== 'seen',
                'status' => $steps[$id] ?? 'todo',
            ], array_keys(self::STEPS)),
            'total' => array_sum(array_column(self::STEPS, 'reward')),
            'earned' => array_sum(array_map(fn ($id) => ($steps[$id] ?? null) === 'done' ? self::STEPS[$id]['reward'] : 0, array_keys(self::STEPS))),
            'trade_lock_until' => $this->tradeLockUntil($row)?->toIso8601String(),
            'mascot' => $this->mascot(),
        ];
    }

    /** Начать путеводитель: приветственные монеты сразу. */
    public function start(User $user): array
    {
        DB::transaction(function () use ($user) {
            $inserted = DB::table('user_guides')->insertOrIgnore([
                'user_id' => $user->id,
                'steps' => json_encode([]),
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted) {
                $this->mark($user, 'welcome', 'done');
            }
        });

        return $this->state($user);
    }

    /** Отметить текущий шаг пройденным. */
    public function complete(User $user, string $step): array
    {
        DB::transaction(function () use ($user, $step) {
            $row = $this->lockedRow($user, $step);
            $check = self::STEPS[$step]['check'];

            if ($check !== 'seen' && ! $this->satisfied($user, $check)) {
                throw new RuntimeException(match ($check) {
                    'pet' => 'Сначала открой сундук питомцев.',
                    'expedition' => 'Сначала отправь питомца в экспедицию.',
                    'battle' => 'Сначала проведи бой на арене.',
                    'miner' => 'Сначала спустись в шахту и закончи забег.',
                    default => 'Шаг ещё не выполнен.',
                });
            }

            $this->mark($user, $step, 'done');
        });

        return $this->state($user);
    }

    /** Пропустить текущий шаг — без награды. */
    public function skip(User $user, string $step): array
    {
        DB::transaction(function () use ($user, $step) {
            $this->lockedRow($user, $step);
            $this->mark($user, $step, 'skipped');
        });

        return $this->state($user);
    }

    public function hide(User $user, bool $hidden): array
    {
        DB::table('user_guides')->where('user_id', $user->id)->update(['hidden' => $hidden, 'updated_at' => now()]);

        return $this->state($user);
    }

    /**
     * Нельзя переводить монеты другим игрокам, пока действует замок после
     * стартовых монет. $what — что именно, для текста ошибки.
     */
    public static function assertCanTrade(User $user, string $what): void
    {
        $started = DB::table('user_guides')->where('user_id', $user->id)->value('started_at');

        if (! $started) {
            return;
        }

        $until = Carbon::parse($started)->addDays(self::TRADE_LOCK_DAYS);

        if ($until->isFuture()) {
            $days = max(1, (int) ceil(now()->diffInHours($until) / 24));
            throw new RuntimeException("{$what} откроется через {$days} дн.: первую неделю после стартовых монет путеводителя ими нельзя делиться.");
        }
    }

    /** Гид — самый редкий питомец сайта прямо сейчас. */
    public function mascot(): ?array
    {
        $top = $this->pets->top('rare')[0] ?? null;

        if (! $top) {
            return null;
        }

        return [
            'species' => $top['species'],
            'name' => $top['name'],
            'traits' => $top['traits'],
            'owner' => $top['owner']['username'] ?? null,
            'chance' => $top['chance'] ?? null,
        ];
    }

    // ---- Внутренности ----

    private function row(User $user): ?object
    {
        return DB::table('user_guides')->where('user_id', $user->id)->first();
    }

    /** Строка путеводителя под замком; шаг должен быть текущим. */
    private function lockedRow(User $user, string $step): object
    {
        if (! isset(self::STEPS[$step])) {
            throw new RuntimeException('Такого шага нет.');
        }

        $row = DB::table('user_guides')->where('user_id', $user->id)->lockForUpdate()->first();

        if (! $row) {
            throw new RuntimeException('Путеводитель ещё не начат.');
        }

        $steps = json_decode($row->steps ?? '[]', true) ?: [];

        if (isset($steps[$step])) {
            throw new RuntimeException('Этот шаг уже позади.');
        }

        if ($this->current($steps) !== $step) {
            throw new RuntimeException('Сначала пройди предыдущие шаги.');
        }

        return $row;
    }

    /** Записать итог шага, выдать награду, закрыть путеводитель в конце. */
    private function mark(User $user, string $step, string $status): void
    {
        $row = DB::table('user_guides')->where('user_id', $user->id)->first();
        $steps = json_decode($row->steps ?? '[]', true) ?: [];
        $steps[$step] = $status;

        DB::table('user_guides')->where('user_id', $user->id)->update([
            'steps' => json_encode($steps),
            'finished_at' => $this->current($steps) === null ? now() : null,
            'updated_at' => now(),
        ]);

        $reward = self::STEPS[$step]['reward'];

        if ($status === 'done' && $reward > 0) {
            CoinService::credit($user, $reward, CoinTransaction::SOURCE_OTHER, "Путеводитель: шаг «{$step}»", "guide:{$user->id}:{$step}");
        }
    }

    /** Засчитать подряд все шаги, которые уже видно по базе. */
    private function advance(User $user, object $row): object
    {
        $steps = json_decode($row->steps ?? '[]', true) ?: [];

        while (($step = $this->current($steps)) && self::STEPS[$step]['check'] !== 'seen' && $this->satisfied($user, self::STEPS[$step]['check'])) {
            $this->mark($user, $step, 'done');
            $steps[$step] = 'done';
        }

        return $this->row($user);
    }

    private function current(array $steps): ?string
    {
        foreach (array_keys(self::STEPS) as $id) {
            if (! isset($steps[$id])) {
                return $id;
            }
        }

        return null;
    }

    private function satisfied(User $user, string $check): bool
    {
        return match ($check) {
            'pet' => UserInventory::where('user_id', $user->id)
                ->whereNotNull('pet_traits')
                ->whereHas('item', fn ($q) => $q->where('type', ShopItem::TYPE_PROFILE_PET))
                ->exists(),
            'expedition' => DB::table('pet_expeditions')->where('user_id', $user->id)->exists(),
            'battle' => DB::table('pet_battles')->where('user_id', $user->id)->exists(),
            'miner' => DB::table('miner_runs')->where('user_id', $user->id)->where('status', 'finished')->exists(),
            default => true,
        };
    }

    private function tradeLockUntil(?object $row): ?Carbon
    {
        if (! $row?->started_at) {
            return null;
        }

        $until = Carbon::parse($row->started_at)->addDays(self::TRADE_LOCK_DAYS);

        return $until->isFuture() ? $until : null;
    }
}
