<?php

namespace App\Domains\Players\Services;

use App\Domains\Clan\Models\ClanCheckin;
use App\Domains\Games\Services\MinerService;
use App\Domains\Players\Support\PetTraits;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Services\LootCaseService;
use App\Domains\Shop\Services\ShopSettingService;
use App\Domains\Wallet\Services\CoinService;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Панель игрока на главной: что можно сделать прямо сейчас и когда
 * откроется остальное — питомцы, отметка в клане, сундук, шахта,
 * аукцион, надетая косметика.
 *
 * Все ежедневные действия сбрасываются в полночь по времени сервера.
 */
class HomeDashboardService
{
    public function __construct(
        private readonly LootCaseService $cases,
        private readonly MinerService $miner,
    ) {
    }

    public function for(User $user): array
    {
        $tomorrow = now()->addDay()->startOfDay()->toIso8601String();

        return [
            'coins' => (int) $user->apex_coins,
            'reset_at' => $tomorrow,
            'pets' => $this->pets($user),
            'clan' => $this->clan($user),
            'chest' => ['available' => $this->cases->dailyAvailable($user)],
            // Бонус за вход: null — выключен в админке
            'bonus' => ShopSettingService::getBool('daily_bonus.enabled') ? [
                'available' => CoinService::dailyBonusAvailable($user),
                'amount' => ShopSettingService::getInt('daily_bonus.amount'),
            ] : null,
            'miner' => $this->minerState($user),
            'auction' => $this->auction($user),
            'equipped' => $this->equipped($user),
        ];
    }

    /** Гуляющие питомцы: уровень и что хозяин уже сделал сегодня. */
    private function pets(User $user): array
    {
        $pets = collect($user->profile_pets ?? []);
        $ids = $pets->pluck('inventory_id')->filter()->map(fn ($id) => (int) $id)->all();
        $levels = PetService::levels($ids);

        $done = DB::table('pet_interactions')
            ->where('owner_id', $user->id)
            ->where('actor_id', $user->id)
            ->where('day', today()->toDateString())
            ->whereIn('inventory_id', $ids ?: [0])
            ->get(['inventory_id', 'action'])
            ->groupBy('inventory_id');

        return $pets->map(fn ($pet) => [
            'inventory_id' => $pet['inventory_id'] ?? null,
            'sprite' => $pet['pet'] ?? null,
            'name' => $pet['name'] ?? null,
            'traits' => PetTraits::present($pet['traits'] ?? null, $pet['serial'] ?? null),
            'level' => $levels[(int) ($pet['inventory_id'] ?? 0)] ?? 1,
            'done' => ($done[$pet['inventory_id'] ?? 0] ?? collect())->pluck('action')->values()->all(),
        ])->values()->all();
    }

    private function clan(User $user): ?array
    {
        $clan = $user->clanMember?->clan;

        if (! $clan) {
            return null;
        }

        return [
            'id' => $clan->id,
            'name' => $clan->name,
            'tag' => $clan->tag,
            'color' => $clan->banner_color,
            'checked_in' => ClanCheckin::where('user_id', $user->id)->where('day', today()->toDateString())->exists(),
        ];
    }

    private function minerState(User $user): array
    {
        // Полное состояние шахты тяжёлое (топ, задания) — главной хватает краткого
        return $this->miner->brief($user);
    }

    private function auction(User $user): array
    {
        // Три счётчика одним запросом. Псевдонимы — не зарезервированные
        // слова MySQL: «leading» там ключевое слово (TRIM LEADING), и на
        // проде запрос падал, а тесты на SQLite этого не видели
        $counts = DB::table('pet_auctions')->where('status', 'active')
            ->selectRaw('count(*) as total_count, sum(case when seller_id = ? then 1 else 0 end) as mine_count, sum(case when top_bidder_id = ? then 1 else 0 end) as leading_count', [$user->id, $user->id])
            ->first();

        return [
            'mine' => (int) $counts->mine_count,
            'leading' => (int) $counts->leading_count,
            'total' => (int) $counts->total_count,
        ];
    }

    /** Надетое: тип и название — для быстрого обзора образа. */
    private function equipped(User $user): array
    {
        return UserInventory::with('item:id,name,type,rarity')
            ->where('user_id', $user->id)
            ->whereNotNull('equipped_at')
            ->orderBy('equipped_at')
            ->get()
            ->filter(fn ($row) => $row->item)
            ->map(fn ($row) => [
                'type' => $row->item->type,
                'name' => $row->pet_name ?: $row->item->name,
                'rarity' => $row->item->rarity,
            ])
            ->values()
            ->all();
    }
}
