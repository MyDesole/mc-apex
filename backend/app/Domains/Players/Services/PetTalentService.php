<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Support\DungeonBattle;
use App\Domains\Players\Support\PetLocks;
use App\Domains\Players\Support\PetNames;
use App\Domains\Players\Support\PetTalents;
use App\Domains\Players\Support\PetTraits;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Академия: очки талантов за уровни, изучение и сброс. Эффекты читают бои
 * (подземелья, арена, рейд) через `effectsFor` и `effectsForMany`.
 */
class PetTalentService
{
    /** Ранги питомцев: id => [талант => ранг]. @param array<int> $ids */
    public static function ranksForMany(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        $out = array_fill_keys($ids, []);

        if (! $ids) {
            return $out;
        }

        foreach (DB::table('pet_talents')->whereIn('inventory_id', $ids)->get(['inventory_id', 'talent', 'rank']) as $row) {
            $out[(int) $row->inventory_id][$row->talent] = (int) $row->rank;
        }

        return $out;
    }

    /** Эффекты талантов питомцев: id => [эффект => значение]. @param array<int> $ids */
    public static function effectsForMany(array $ids): array
    {
        return array_map(fn (array $ranks) => PetTalents::effects($ranks), self::ranksForMany($ids));
    }

    public static function effectsFor(int $id): array
    {
        return self::effectsForMany([$id])[$id] ?? [];
    }

    /** Питомцы игрока с числом свободных очков — для выбора в Академии. */
    public function roster(User $user): array
    {
        $pets = UserInventory::with('item')->where('user_id', $user->id)->whereNotNull('pet_traits')->get();
        $levels = PetService::levels($pets->pluck('id')->all());
        $ranks = self::ranksForMany($pets->pluck('id')->all());

        return $pets->map(function (UserInventory $pet) use ($levels, $ranks) {
            $level = $levels[$pet->id] ?? 1;
            $spent = array_sum($ranks[$pet->id] ?? []);

            return [
                'inventory_id' => $pet->id,
                'name' => $pet->pet_name ?: PetNames::species($pet->item?->effect_value),
                'species' => $pet->item?->effect_value,
                'traits' => PetTraits::present($pet->pet_traits, $pet->pet_serial),
                'level' => $level,
                'element' => PetArenaService::element($pet->item?->effect_value),
                'free' => max(0, PetTalents::totalPoints($level) - $spent),
                'spent' => $spent,
            ];
        })->sortByDesc('level')->values()->all();
    }

    /** Древо одного питомца. */
    public function tree(User $user, int $id): array
    {
        $pet = $this->ownPet($user, $id);

        return $this->present($pet);
    }

    /** Выучить (или поднять на ранг) талант. */
    public function learn(User $user, int $id, string $talent): array
    {
        $def = PetTalents::TALENTS[$talent] ?? null;

        if (! $def) {
            throw new RuntimeException('Такого таланта нет.');
        }

        return DB::transaction(function () use ($user, $id, $talent, $def) {
            $pet = $this->ownPet($user, $id, true);
            $level = PetService::levels([$pet->id])[$pet->id] ?? 1;
            $ranks = self::ranksForMany([$pet->id])[$pet->id];

            // Таланты пути стихии доступны только питомцам этой стихии
            if (isset($def['element']) && $def['element'] !== PetArenaService::element($pet->item?->effect_value)) {
                throw new RuntimeException('Этот талант — для питомцев другой стихии.');
            }

            $free = PetTalents::totalPoints($level) - array_sum($ranks);

            if ($free <= 0) {
                throw new RuntimeException('Нет свободных очков: они даются за уровни.');
            }

            $rank = $ranks[$talent] ?? 0;

            if ($rank >= $def['max']) {
                throw new RuntimeException('Талант уже изучен полностью.');
            }

            $gate = PetTalents::TIER_GATE[$def['tier']] ?? 0;

            if (PetTalents::spentIn($ranks, $def['branch']) < $gate) {
                throw new RuntimeException("Ярус откроется, когда в ветке «" . PetTalents::BRANCHES[$def['branch']]['name'] . "» будет вложено {$gate} очков.");
            }

            DB::table('pet_talents')->upsert(
                ['inventory_id' => $pet->id, 'talent' => $talent, 'rank' => $rank + 1, 'created_at' => now(), 'updated_at' => now()],
                ['inventory_id', 'talent'],
                ['rank' => $rank + 1, 'updated_at' => now()],
            );

            return $this->present($pet);
        });
    }

    /** Сбросить все таланты питомца за ApexCoin. */
    public function respec(User $user, int $id): array
    {
        return DB::transaction(function () use ($user, $id) {
            $pet = $this->ownPet($user, $id, true);

            if (! DB::table('pet_talents')->where('inventory_id', $pet->id)->exists()) {
                throw new RuntimeException('У питомца нет изученных талантов.');
            }

            CoinService::debit($user, PetTalents::RESPEC_COST, CoinTransaction::SOURCE_PURCHASE, 'Академия: сброс талантов');
            DB::table('pet_talents')->where('inventory_id', $pet->id)->delete();

            return $this->present($pet);
        });
    }

    private function ownPet(User $user, int $id, bool $forUpdate = false): UserInventory
    {
        $query = UserInventory::with('item')->where('user_id', $user->id)->whereKey($id)->whereNotNull('pet_traits');

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $pet = $query->first();

        if (! $pet) {
            throw new RuntimeException('Это не твой питомец.');
        }

        if ($forUpdate && ($busy = PetLocks::reason($pet->id))) {
            throw new RuntimeException($busy);
        }

        return $pet;
    }

    private function present(UserInventory $pet): array
    {
        $level = PetService::levels([$pet->id])[$pet->id] ?? 1;
        $ranks = self::ranksForMany([$pet->id])[$pet->id];
        $spent = array_sum($ranks);
        $element = PetArenaService::element($pet->item?->effect_value);

        $branches = [];

        foreach (PetTalents::BRANCHES as $key => $branch) {
            $inBranch = PetTalents::spentIn($ranks, $key);
            $talents = [];

            // Ветка стихии получает имя и цвет стихии питомца
            if ($key === 'element') {
                $info = DungeonBattle::ELEMENTS[$element] ?? ['name' => $element, 'color' => $branch['color']];
                $branch['name'] = 'Путь стихии: ' . $info['name'];
                $branch['color'] = $info['color'];
            }

            foreach (PetTalents::TALENTS as $talentKey => $def) {
                if ($def['branch'] !== $key || (isset($def['element']) && $def['element'] !== $element)) {
                    continue;
                }

                $gate = PetTalents::TIER_GATE[$def['tier']];
                $text = $def['text'];

                if ($talentKey === 'm_sig') {
                    $text = '+12% к эффекту умений стихии за ранг: ' . (PetTalents::SIGNATURE[$element] ?? 'побочный эффект');
                }

                $talents[] = [
                    'key' => $talentKey,
                    'name' => $def['name'],
                    'text' => $text,
                    'tier' => $def['tier'],
                    'max' => $def['max'],
                    'rank' => $ranks[$talentKey] ?? 0,
                    'sprite' => $def['sprite'],
                    'gate' => $gate,
                    'unlocked' => $inBranch >= $gate,
                ];
            }

            $branches[] = ['key' => $key, 'name' => $branch['name'], 'text' => $branch['text'], 'color' => $branch['color'], 'sprite' => $branch['sprite'], 'spent' => $inBranch, 'talents' => $talents];
        }

        return [
            'pet' => [
                'inventory_id' => $pet->id,
                'name' => $pet->pet_name ?: PetNames::species($pet->item?->effect_value),
                'species' => $pet->item?->effect_value,
                'traits' => PetTraits::present($pet->pet_traits, $pet->pet_serial),
                'level' => $level,
                'element' => $element,
            ],
            'points' => ['total' => PetTalents::totalPoints($level), 'spent' => $spent, 'free' => max(0, PetTalents::totalPoints($level) - $spent)],
            'branches' => $branches,
            'effects' => PetTalents::effects($ranks),
            'respec_cost' => PetTalents::RESPEC_COST,
            'gates' => PetTalents::TIER_GATE,
        ];
    }
}
