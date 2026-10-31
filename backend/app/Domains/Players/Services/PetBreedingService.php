<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Support\PetLocks;
use App\Domains\Players\Support\PetTraits;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Скрещивание питомцев: два свободных родителя дают потомка.
 *
 * Вид — случайный родитель, черты (окрас, фон, аксессуар) — случайно от
 * каждого родителя. Кулдаун — сутки на родителя, чтобы не штамповать
 * потомков бесконечно.
 */
class PetBreedingService
{
    public const COST = 300;
    public const COOLDOWN_HOURS = 24;

    /** @return array{offspring: array<string, mixed>, balance: int} */
    public function breed(User $user, int $parent1Id, int $parent2Id): array
    {
        if ($parent1Id === $parent2Id) {
            throw new RuntimeException('Нужны два разных питомца.');
        }

        $p1 = $this->ownPet($user, $parent1Id);
        $p2 = $this->ownPet($user, $parent2Id);

        foreach ([$p1, $p2] as $parent) {
            if ($busy = PetLocks::reason($parent->id)) {
                throw new RuntimeException($busy);
            }

            $this->assertCooledDown($parent->id);
        }

        if ((int) $user->fresh()->apex_coins < self::COST) {
            throw new RuntimeException('Нужно ' . self::COST . ' ApexCoin.');
        }

        $speciesId = random_int(0, 1) === 0 ? $p1->shop_item_id : $p2->shop_item_id;
        $traits = $this->blend($p1->pet_traits, $p2->pet_traits);

        $offspring = DB::transaction(function () use ($user, $p1, $p2, $speciesId, $traits) {
            CoinService::debit($user, self::COST, CoinTransaction::SOURCE_PURCHASE, 'Скрещивание питомцев');

            $row = UserInventory::create([
                'user_id' => $user->id,
                'shop_item_id' => $speciesId,
                'quantity' => 1,
                'pet_traits' => $traits,
                'acquired_at' => now(),
            ]);

            DB::table('pet_breedings')->insert([
                'user_id' => $user->id,
                'parent1_id' => $p1->id,
                'parent2_id' => $p2->id,
                'offspring_id' => $row->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $row;
        });

        return [
            'offspring' => [
                'inventory_id' => $offspring->id,
                ...PetTraits::present($offspring->pet_traits, $offspring->pet_serial) ?? [],
            ],
            'balance' => (int) $user->fresh()->apex_coins,
        ];
    }

    /** Черты потомка: каждый слот от случайного родителя. */
    private function blend(?array $a, ?array $b): array
    {
        $a = $a ?: [];
        $b = $b ?: [];

        $out = [];

        foreach (['model', 'backdrop', 'accessory'] as $trait) {
            $from = random_int(0, 1) === 0 ? $a : $b;

            if (isset($from[$trait])) {
                $out[$trait] = $from[$trait];
            }
        }

        // Родители без черт (старые питомцы) — потомок получает базовый набор
        return $out + ['model' => 'classic', 'backdrop' => 'none', 'accessory' => 'none'];
    }

    private function assertCooledDown(int $inventoryId): void
    {
        $recent = DB::table('pet_breedings')
            ->where(fn ($q) => $q->where('parent1_id', $inventoryId)->orWhere('parent2_id', $inventoryId))
            ->where('created_at', '>=', now()->subHours(self::COOLDOWN_HOURS))
            ->exists();

        if ($recent) {
            throw new RuntimeException('Питомец уже участвовал в скрещивании — подождите сутки.');
        }
    }

    private function ownPet(User $user, int $inventoryId): UserInventory
    {
        $row = UserInventory::with('item')->find($inventoryId);

        if (! $row || $row->user_id !== $user->id || $row->item?->type !== ShopItem::TYPE_PROFILE_PET) {
            throw new RuntimeException('Это не твой питомец.');
        }

        return $row;
    }
}
