<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Support\PetQuests;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use App\Support\GameDay;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Доска заданий: прогресс копится сам, награду игрок забирает кнопкой.
 * Событие `track` дёргают механики (подземелья, арена, экспедиции…), оно
 * дешёвое и ничего не бросает.
 */
class QuestService
{
    private const BONUS_KEY = 'd_bonus';

    /** Засчитать событие во все задания дня и недели, которым оно подходит. */
    public static function track(User|int $user, string $event, int $amount = 1): void
    {
        if ($amount <= 0) {
            return;
        }

        $userId = $user instanceof User ? $user->id : $user;

        foreach ([PetQuests::dayKey() => PetQuests::today(), PetQuests::weekKey() => PetQuests::week()] as $period => $quests) {
            foreach ($quests as $key => $quest) {
                if ($quest['event'] !== $event) {
                    continue;
                }

                DB::table('pet_quests')->insertOrIgnore([
                    'user_id' => $userId, 'quest' => $key, 'period' => $period, 'progress' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                $row = DB::table('pet_quests')->where('user_id', $userId)->where('quest', $key)->where('period', $period)->first();

                if (! $row || $row->claimed_at || (int) $row->progress >= $quest['target']) {
                    continue;
                }

                DB::table('pet_quests')->where('id', $row->id)->update([
                    'progress' => min($quest['target'], (int) $row->progress + $amount),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /** Доска: суточные и недельные задания, бонус дня, жетоны, время сбросов. */
    public function board(User $user): array
    {
        $day = PetQuests::dayKey();
        $week = PetQuests::weekKey();

        $rows = DB::table('pet_quests')->where('user_id', $user->id)->whereIn('period', [$day, $week])->get()
            ->keyBy(fn ($row) => $row->period . '|' . $row->quest);

        $present = function (array $quests, string $period) use ($rows) {
            $out = [];

            foreach ($quests as $key => $quest) {
                $row = $rows->get($period . '|' . $key);
                $progress = (int) ($row->progress ?? 0);

                $out[] = [
                    'key' => $key,
                    'title' => $quest['title'],
                    'text' => $quest['text'],
                    'sprite' => $quest['sprite'],
                    'target' => $quest['target'],
                    'progress' => min($progress, $quest['target']),
                    'coins' => $quest['coins'],
                    'tokens' => $quest['tokens'],
                    'done' => $progress >= $quest['target'],
                    'claimed' => (bool) ($row->claimed_at ?? false),
                ];
            }

            return $out;
        };

        $daily = $present(PetQuests::today(), $day);

        return [
            'daily' => $daily,
            'weekly' => $present(PetQuests::week(), $week),
            'bonus' => [
                ...PetQuests::DAILY_BONUS,
                'ready' => collect($daily)->every(fn ($q) => $q['claimed']),
                'claimed' => (bool) ($rows->get($day . '|' . self::BONUS_KEY)->claimed_at ?? false),
            ],
            'tokens' => (int) DB::table('users')->where('id', $user->id)->value('pet_tokens'),
            'resets' => [
                'daily' => GameDay::local()->addDay()->toIso8601String(),
                'weekly' => GameDay::local()->next('Monday')->toIso8601String(),
            ],
            'claimable' => collect($daily)->concat($present(PetQuests::week(), $week))->filter(fn ($q) => $q['done'] && ! $q['claimed'])->count(),
        ];
    }

    /** Забрать награду за выполненное задание. @return array<string, mixed> */
    public function claim(User $user, string $key): array
    {
        $day = PetQuests::dayKey();
        $week = PetQuests::weekKey();

        $quest = PetQuests::today()[$key] ?? null;
        $period = $day;

        if (! $quest) {
            $quest = PetQuests::week()[$key] ?? null;
            $period = $week;
        }

        if (! $quest) {
            throw new RuntimeException('Такого задания сегодня нет.');
        }

        return DB::transaction(function () use ($user, $key, $quest, $period) {
            $row = DB::table('pet_quests')->where('user_id', $user->id)->where('quest', $key)->where('period', $period)->lockForUpdate()->first();

            if (! $row || (int) $row->progress < $quest['target']) {
                throw new RuntimeException('Задание ещё не выполнено.');
            }

            if ($row->claimed_at) {
                throw new RuntimeException('Награда уже получена.');
            }

            DB::table('pet_quests')->where('id', $row->id)->update(['claimed_at' => now(), 'updated_at' => now()]);

            return $this->pay($user, $quest['coins'], $quest['tokens'], "quest:{$user->id}:{$key}:{$period}", 'Задание питомцев: ' . $quest['title']);
        });
    }

    /** Бонус за все суточные задания дня. @return array<string, mixed> */
    public function claimBonus(User $user): array
    {
        $day = PetQuests::dayKey();

        return DB::transaction(function () use ($user, $day) {
            $claimed = DB::table('pet_quests')->where('user_id', $user->id)->where('period', $day)
                ->whereIn('quest', array_keys(PetQuests::today()))->whereNotNull('claimed_at')->count();

            if ($claimed < PetQuests::DAILY_COUNT) {
                throw new RuntimeException('Сначала забери награды за все задания дня.');
            }

            DB::table('pet_quests')->insertOrIgnore([
                'user_id' => $user->id, 'quest' => self::BONUS_KEY, 'period' => $day, 'progress' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $row = DB::table('pet_quests')->where('user_id', $user->id)->where('quest', self::BONUS_KEY)->where('period', $day)->lockForUpdate()->first();

            if ($row->claimed_at) {
                throw new RuntimeException('Бонус дня уже получен.');
            }

            DB::table('pet_quests')->where('id', $row->id)->update(['claimed_at' => now(), 'updated_at' => now()]);

            return $this->pay($user, PetQuests::DAILY_BONUS['coins'], PetQuests::DAILY_BONUS['tokens'], "quest-bonus:{$user->id}:{$day}", 'Бонус дня: все задания выполнены');
        });
    }

    /** Монеты и жетоны разом; ключ монет защищает от двойной выдачи. */
    private function pay(User $user, int $coins, int $tokens, string $key, string $description): array
    {
        CoinService::credit($user, $coins, CoinTransaction::SOURCE_OTHER, $description, $key);
        DB::table('users')->where('id', $user->id)->increment('pet_tokens', $tokens);

        return [
            'reward' => ['coins' => $coins, 'tokens' => $tokens],
            'board' => $this->board($user->fresh()),
            'balance' => (int) $user->fresh()->apex_coins,
        ];
    }
}
