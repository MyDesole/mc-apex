<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Support\PetTraits;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Services\ShopService;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Питомец профиля: погладить, покормить, поиграть.
 *
 * Каждое действие — раз в день от каждого игрока (и от хозяина тоже),
 * за действия питомец копит опыт и растёт в уровне. Если его давно не
 * кормили, он голодный и просит еды; если с ним недавно возились —
 * радостный.
 */
class PetService
{
    /** Действие => сколько опыта даёт. */
    public const ACTIONS = ['pet' => 1, 'feed' => 2, 'play' => 2];

    public const MAX_LEVEL = 30;

    private const HUNGRY_AFTER_HOURS = 24;

    private const HAPPY_WITHIN_HOURS = 6;

    public function stats(User $owner, ?User $viewer = null, ?int $inventoryId = null): array
    {
        // Несколько питомцев: черты и имя — того экземпляра, на который нажали
        $instance = $inventoryId
            ? collect($owner->profile_pets ?? [])->firstWhere('inventory_id', $inventoryId)
            : null;

        // У каждого питомца свой опыт: считаем только действия с этим экземпляром
        // Опыт идёт за экземпляром: проданный или подаренный питомец сохраняет уровень
        $pet = $this->instanceKey($owner, $inventoryId);
        $scope = fn () => self::instanceScope($owner->id, $pet);

        $counts = $scope()
            ->selectRaw('action, count(*) as total')
            ->groupBy('action')
            ->pluck('total', 'action');

        // Опыт ухода + опыт из экспедиций и боёв (хранится в строке инвентаря)
        $row = $pet ? UserInventory::find($pet) : null;
        $xp = (int) ($row?->pet_xp ?? 0);
        foreach (self::ACTIONS as $action => $points) {
            $xp += (int) ($counts[$action] ?? 0) * $points;
        }

        $level = $this->levelFor($xp);

        $lastFed = $scope()->where('action', 'feed')->max('created_at');

        $lastAny = $scope()->max('created_at');

        $mood = match (true) {
            ! $lastFed || now()->diffInHours($lastFed, true) >= self::HUNGRY_AFTER_HOURS => 'hungry',
            $lastAny && now()->diffInHours($lastAny, true) < self::HAPPY_WITHIN_HOURS => 'happy',
            default => 'calm',
        };

        // Что зритель уже сделал сегодня
        $doneToday = $viewer
            ? $scope()
                ->where('actor_id', $viewer->id)
                ->where('day', today()->toDateString())
                ->pluck('action')
                ->all()
            : [];

        // Последние, кто заботился о питомце
        $friends = DB::table('pet_interactions as pi')
            ->join('users as u', 'u.id', '=', 'pi.actor_id')
            ->when($pet, fn ($q) => $q->where('pi.inventory_id', $pet), fn ($q) => $q->where('pi.owner_id', $owner->id)->where('pi.inventory_id', 0))
            ->where('pi.actor_id', '!=', $owner->id)
            ->orderByDesc('pi.created_at')
            ->limit(30)
            ->get(['u.id', 'u.username'])
            ->unique('id')
            ->take(5)
            ->values()
            ->all();

        // Черты надетого экземпляра; у админа без записи — постоянные по id
        $traits = $owner->pet_traits ?: ($owner->profile_pet ? PetTraits::roll(crc32($owner->id . ':' . $owner->profile_pet)) : null);

        if ($instance) {
            $traits = $instance['traits'] ?? $traits;
        }

        return [
            'traits' => PetTraits::present($traits, $instance['serial'] ?? ($owner->pet_traits['serial'] ?? null)),
            'name' => $instance ? ($instance['name'] ?? null) : $owner->pet_name,
            'xp' => $xp,
            'level' => $level,
            'level_xp' => $this->xpFor($level),
            'next_level_xp' => $level >= self::MAX_LEVEL ? null : $this->xpFor($level + 1),
            'mood' => $mood,
            'counts' => [
                'pet' => (int) ($counts['pet'] ?? 0),
                'feed' => (int) ($counts['feed'] ?? 0),
                'play' => (int) ($counts['play'] ?? 0),
            ],
            'done_today' => $doneToday,
            'friends' => $friends,
            'rating' => (int) ($row?->pet_rating ?? 1000),
            'wins' => (int) ($row?->pet_wins ?? 0),
            'losses' => (int) ($row?->pet_losses ?? 0),
            'trick' => $row?->pet_trick,
        ];
    }

    public function interact(User $owner, User $actor, string $action, ?int $inventoryId = null): array
    {
        if (! isset(self::ACTIONS[$action])) {
            throw ValidationException::withMessages(['action' => ['Такого с питомцем сделать нельзя.']]);
        }

        if (! $owner->profile_pet) {
            throw ValidationException::withMessages(['action' => ['У игрока нет питомца.']]);
        }

        $pet = $this->instanceKey($owner, $inventoryId);
        $levelBefore = $this->stats($owner, null, $pet ?: null)['level'];

        try {
            DB::table('pet_interactions')->insert([
                'owner_id' => $owner->id,
                'inventory_id' => $pet,
                'actor_id' => $actor->id,
                'action' => $action,
                'day' => today()->toDateString(),
                'created_at' => now(),
            ]);
        } catch (QueryException) {
            // Уникальный индекс: сегодня это уже делали
            throw ValidationException::withMessages(['action' => ['Сегодня ты уже это делал — приходи завтра.']]);
        }

        $stats = $this->stats($owner, $actor, $pet ?: null);
        $stats['level_up'] = $stats['level'] > $levelBefore;

        return $stats;
    }

    /**
     * Какой экземпляр: из гуляющих питомцев хозяина (чужой id — нельзя),
     * без id — первый; 0 — питомец без записи в инвентаре.
     */
    private function instanceKey(User $owner, ?int $inventoryId): int
    {
        $walking = collect($owner->profile_pets ?? [])->pluck('inventory_id')->filter()->map(fn ($id) => (int) $id);

        if ($inventoryId && $walking->contains($inventoryId)) {
            return $inventoryId;
        }

        return (int) ($walking->first() ?? 0);
    }

    /**
     * Имя конкретного экземпляра питомца (по строке инвентаря), без
     * номера — первого надетого.
     */
    public function rename(User $owner, ?string $name, ?int $inventoryId = null): ?string
    {
        $name = mb_substr(trim(strip_tags((string) $name)), 0, 20);
        $name = $name !== '' ? $name : null;

        $row = $inventoryId
            ? UserInventory::where('user_id', $owner->id)->whereKey($inventoryId)->first()
            : UserInventory::where('user_id', $owner->id)
                ->whereNotNull('equipped_at')
                ->whereHas('item', fn ($q) => $q->where('type', ShopItem::TYPE_PROFILE_PET))
                ->orderBy('equipped_at')
                ->first();

        if ($row) {
            $row->forceFill(['pet_name' => $name])->save();
            ShopService::syncPets($owner);
        }

        // Старое поле — для питомца без записи в инвентаре (админ)
        $owner->forceFill(['pet_name' => $name])->save();

        return $name;
    }

    /** Действия с экземпляром; 0 — питомец без записи в инвентаре, он у каждого хозяина свой. */
    private static function instanceScope(int $ownerId, int $pet): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('pet_interactions')->where('inventory_id', $pet);

        return $pet ? $query : $query->where('owner_id', $ownerId);
    }

    /**
     * Уровни питомцев по строкам инвентаря — для инвентаря и аукциона.
     *
     * @param  array<int>  $inventoryIds
     * @return array<int, int>  inventory_id => уровень
     */
    public static function levels(array $inventoryIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $inventoryIds)));
        if (! $ids) {
            return [];
        }

        return array_map(fn ($points) => self::levelFor($points), self::xpTotals($ids));
    }

    /**
     * Весь опыт питомцев: уход + экспедиции и бои.
     *
     * @param  array<int>  $ids
     * @return array<int, int>  inventory_id => опыт
     */
    public static function xpTotals(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (! $ids) {
            return [];
        }

        $xp = DB::table('user_inventory')->whereIn('id', $ids)->pluck('pet_xp', 'id')->map(fn ($v) => (int) $v)->all()
            + array_fill_keys($ids, 0);

        DB::table('pet_interactions')
            ->whereIn('inventory_id', $ids)
            ->selectRaw('inventory_id, action, count(*) as total')
            ->groupBy('inventory_id', 'action')
            ->get()
            ->each(function ($row) use (&$xp) {
                $xp[(int) $row->inventory_id] += (int) $row->total * (self::ACTIONS[$row->action] ?? 0);
            });

        return $xp;
    }

    /** Публично: уровень по опыту (топ, арена). */
    public static function levelOf(int $xp): int
    {
        return self::levelFor($xp);
    }

    /** Опыт на уровень растёт: 0, 5, 15, 30, 50… (5 · n(n−1)/2). */
    private static function xpFor(int $level): int
    {
        return intdiv(5 * $level * ($level - 1), 2);
    }

    private static function levelFor(int $xp): int
    {
        $level = 1;

        while ($level < self::MAX_LEVEL && $xp >= self::xpFor($level + 1)) {
            $level++;
        }

        return $level;
    }
}
