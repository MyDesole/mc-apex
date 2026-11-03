<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Support\PetTraits;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Services\RubleService;
use Illuminate\Support\Facades\DB;

/**
 * Карта-мир: одна сцена, из которой открываются все игровые механики.
 * Здесь только сводка для значков на постройках — сами механики живут
 * в своих сервисах и открываются панелями поверх карты.
 */
class WorldService
{
    public function state(User $user): array
    {
        $now = now();

        $expeditions = DB::table('pet_expeditions')->where('user_id', $user->id)->whereNull('claimed_at')
            ->selectRaw('count(*) as total, sum(case when ends_at <= ? then 1 else 0 end) as ready', [$now])->first();

        $ready = fn (string $table, string $column) => DB::table($table)->where('user_id', $user->id)
            ->whereNull('claimed_at')->where($column, '<=', $now)->count();

        $personal = PersonalFarmService::has($user);

        // Питомцы бродят по карте: надетые первыми, потом самые высокие
        $rows = UserInventory::with('item')->where('user_id', $user->id)->whereNotNull('pet_traits')
            ->orderByRaw('equipped_at is null')->orderByDesc('id')->limit(6)->get();

        return [
            'coins' => (int) $user->apex_coins,
            'rubles' => RubleService::balance($user),
            'pets' => $rows->map(fn (UserInventory $row) => [
                'inventory_id' => $row->id,
                'species' => $row->item?->effect_value,
                'name' => $row->pet_name ?: $row->item?->name,
                'traits' => PetTraits::present($row->pet_traits, $row->pet_serial),
            ])->values()->all(),
            // Значки на постройках: сколько дел ждёт игрока
            'badges' => [
                'farm' => DB::table('farm_pets')->where('status', 'reserved')->where('reserved_by', $user->id)
                    ->where('reserved_until', '>', $now)->where('ready_at', '<=', $now)->count(),
                'personal' => $personal
                    ? $ready('farm_plots', 'ready_at') + DB::table('pet_breedings')->where('user_id', $user->id)
                        ->where('kind', 'mating')->whereNull('claimed_at')->where('ends_at', '<=', $now)->count()
                    : 0,
                'expeditions' => (int) ($expeditions->ready ?? 0),
                'dungeon' => DB::table('dungeon_runs')->where('user_id', $user->id)->where('status', 'active')->count(),
            ],
            'personal_unlocked' => $personal,
            'expeditions_active' => (int) ($expeditions->total ?? 0),
        ];
    }
}
