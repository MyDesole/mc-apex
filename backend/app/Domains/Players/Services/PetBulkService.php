<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Support\PetLocks;
use App\Domains\Shop\Exceptions\NotEnoughCoins;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Всё разом за монеты: покормить всех питомцев, отправить всех свободных
 * в экспедицию, забрать добычу у всех вернувшихся.
 *
 * Плата — за каждого питомца, которого действие касается, и списывается
 * заранее одним платежом. Если с кем-то не вышло (гонка, питомца продали),
 * его доля возвращается.
 */
class PetBulkService
{
    /** Плата за одного питомца. */
    public const FEES = [
        'feed' => 20,
        'send' => 40,
        'claim' => 15,
    ];

    public function __construct(
        private readonly PetService $pets,
        private readonly PetExpeditionService $expeditions,
    ) {
    }

    /**
     * Сколько питомцев затронет каждое действие и сколько это стоит — для
     * кнопок. Из карточек сводки «Мои питомцы», чтобы не грузить всё заново.
     *
     * @param  array<int, array>  $pets  карточки из PetHubService::overview
     */
    public static function previewFrom(array $pets): array
    {
        $pets = collect($pets);
        $hungry = $pets->reject(fn ($p) => in_array('feed', $p['care'] ?? [], true))->count();
        $ready = $pets->filter(fn ($p) => ! empty($p['expedition']['ready']))->count();
        $free = $pets->filter(fn ($p) => empty($p['busy']))->count();

        return [
            'fees' => self::FEES,
            'feed' => ['count' => $hungry, 'cost' => $hungry * self::FEES['feed']],
            'claim' => ['count' => $ready, 'cost' => $ready * self::FEES['claim']],
            'send' => ['count' => $free, 'cost' => $free * self::FEES['send']],
        ];
    }

    /** Покормить всех, кого хозяин сегодня ещё не кормил. */
    public function feedAll(User $user): array
    {
        $rows = $this->unfed($user);

        return $this->run($user, 'feed', $rows, 'Покормить всех питомцев', function (UserInventory $row) use ($user) {
            $this->pets->interact($user, $user, 'feed', $row->id);
        });
    }

    /** Отправить всех свободных питомцев, которым доступна земля. */
    public function sendAll(User $user, string $zone): array
    {
        $config = PetExpeditionService::ZONES[$zone] ?? null;
        if (! $config) {
            throw new RuntimeException('Такой земли нет.');
        }

        $free = $this->freePets($user);
        $levels = PetService::levels($free->pluck('id')->all());
        $rows = $free->filter(fn ($row) => ($levels[$row->id] ?? 1) >= $config['min_level'])->values();

        if ($rows->isEmpty() && $free->isNotEmpty()) {
            throw new RuntimeException("{$config['name']} открываются с {$config['min_level']} уровня — свободных питомцев такого уровня нет.");
        }

        return $this->run($user, 'send', $rows, "Отправить всех: {$config['name']}", function (UserInventory $row) use ($user, $zone) {
            $this->expeditions->start($user, $row->id, $zone);
        });
    }

    /** Забрать добычу у всех вернувшихся. */
    public function claimAll(User $user): array
    {
        $trips = $this->readyTrips($user);
        $loot = ['coins' => 0, 'xp' => 0, 'keys' => [], 'trips' => []];

        $result = $this->run($user, 'claim', $trips, 'Забрать добычу у всех', function (object $trip) use ($user, &$loot) {
            $claimed = $this->expeditions->claim($user, $trip->id);
            $reward = $claimed['reward'] ?? [];

            $loot['coins'] += (int) ($reward['coins'] ?? 0);
            $loot['xp'] += (int) ($reward['xp'] ?? 0);
            foreach ($reward['keys'] ?? [] as $slug => $count) {
                $loot['keys'][$slug] = ($loot['keys'][$slug] ?? 0) + $count;
            }
            $loot['trips'][] = $claimed;
        });

        return $result + ['loot' => $loot, 'balance' => (int) $user->fresh()->apex_coins];
    }

    /**
     * Общая схема: плата заранее, действие с каждым, возврат доли за тех,
     * с кем не вышло.
     */
    private function run(User $user, string $kind, Collection $targets, string $title, callable $action): array
    {
        if ($targets->isEmpty()) {
            throw new RuntimeException(match ($kind) {
                'feed' => 'Все питомцы уже накормлены сегодня.',
                'send' => 'Свободных питомцев нет — все в походе, на торгах или на арене.',
                'claim' => 'Пока никто не вернулся из экспедиции.',
            });
        }

        $fee = self::FEES[$kind];
        $cost = $targets->count() * $fee;

        if (! CoinService::canAfford($user->fresh(), $cost)) {
            throw new NotEnoughCoins($cost - (int) $user->fresh()->apex_coins);
        }

        CoinService::debit($user, $cost, CoinTransaction::SOURCE_OTHER, "Питомцы: {$title}");

        $done = 0;
        $failed = 0;
        foreach ($targets as $target) {
            try {
                $action($target);
                $done++;
            } catch (Throwable) {
                $failed++;
            }
        }

        if ($failed > 0) {
            CoinService::credit($user, $failed * $fee, CoinTransaction::SOURCE_OTHER, "Питомцы: возврат за {$failed} — {$title}");
        }

        return [
            'done' => $done,
            'failed' => $failed,
            'spent' => $done * $fee,
            'balance' => (int) $user->fresh()->apex_coins,
        ];
    }

    private function ownPets(User $user): Collection
    {
        return UserInventory::query()
            ->where('user_id', $user->id)
            ->whereNotNull('pet_traits')
            ->whereHas('item', fn ($q) => $q->where('type', ShopItem::TYPE_PROFILE_PET))
            ->get();
    }

    private function unfed(User $user): Collection
    {
        $pets = $this->ownPets($user);

        $fedToday = DB::table('pet_interactions')
            ->whereIn('inventory_id', $pets->pluck('id')->all() ?: [0])
            ->where('actor_id', $user->id)
            ->where('action', 'feed')
            ->where('day', today()->toDateString())
            ->pluck('inventory_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $pets->reject(fn ($row) => in_array($row->id, $fedToday, true))->values();
    }

    private function freePets(User $user): Collection
    {
        $pets = $this->ownPets($user);
        $busy = PetLocks::reasons($pets->pluck('id')->all());

        return $pets->reject(fn ($row) => isset($busy[$row->id]))->values();
    }

    private function readyTrips(User $user): Collection
    {
        return DB::table('pet_expeditions')
            ->where('user_id', $user->id)
            ->whereNull('claimed_at')
            ->where('ends_at', '<=', now())
            ->orderBy('id')
            ->get();
    }
}
