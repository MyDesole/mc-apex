<?php

namespace App\Domains\Players\Services;

use App\Domains\Moderation\Notifications\ModerationNotification;
use App\Domains\Players\Support\PetLocks;
use App\Domains\Players\Support\PetPerks;
use App\Domains\Players\Support\PetTraits;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Арена питомцев: пошаговый бой против питомца другого игрока под
 * управлением компьютера.
 *
 * Каждый раунд игрок выбирает приём — атака, защита или умение стихии,
 * — соперник тоже. Первым бьёт тот, кто быстрее; защита действует на
 * весь раунд. Рейтинг (Эло) хранится в питомце и уходит с ним при
 * продаже. За победу — монеты и опыт, проигравший теряет только очки.
 */
class PetArenaService
{
    public const DAILY_FIGHTS = 5;
    public const MAX_ROUNDS = 30;
    private const K = 32;

    /** Стихия по виду питомца. */
    public const ELEMENTS = [
        'fire' => ['dragon', 'phoenix'],
        'water' => ['axolotl', 'penguin', 'octopus', 'jelly'],
        'nature' => ['frog', 'bee', 'shroom', 'bunny', 'slime'],
        'shadow' => ['ghost', 'owl', 'robot'],
        'beast' => ['cat', 'dog', 'fox', 'panda', 'capybara', 'unicorn'],
    ];

    /** Умения стихий: название, множитель урона, особенность. */
    public const SKILLS = [
        'fire' => ['name' => 'Пламя', 'power' => 1.7],
        'water' => ['name' => 'Волна', 'power' => 1.35, 'heal' => 0.12],
        'nature' => ['name' => 'Шипы', 'power' => 1.3, 'thorns' => 0.4],
        'shadow' => ['name' => 'Тень', 'power' => 1.45, 'pierce' => true],
        'beast' => ['name' => 'Рывок', 'power' => 1.5, 'crit' => 30],
    ];

    /** Кто кого бьёт сильнее: огонь > природа > вода > огонь, тень > зверь. */
    private const STRONG = ['fire' => 'nature', 'nature' => 'water', 'water' => 'fire', 'shadow' => 'beast'];

    private const SKILL_COOLDOWN = 3;

    private const RARITY_BONUS = ['common' => 0, 'rare' => 1, 'epic' => 2, 'legendary' => 3, 'mythic' => 4];

    public static function element(?string $species): string
    {
        foreach (self::ELEMENTS as $element => $list) {
            if (in_array($species, $list, true)) {
                return $element;
            }
        }

        return 'beast';
    }

    /** Боёв на сегодня осталось. */
    public function fightsLeft(User $user): int
    {
        $limit = self::DAILY_FIGHTS + ($this->bestLevel($user) >= 20 ? 1 : 0);
        $used = DB::table('pet_battles')->where('user_id', $user->id)->where('created_at', '>=', today())->count();

        return max(0, $limit - $used);
    }

    /** Соперники примерно своего рейтинга — питомцы других игроков. */
    public function opponents(User $user, int $inventoryId): array
    {
        $row = app(PetExpeditionService::class)->ownPet($user, $inventoryId);
        $rating = (int) $row->pet_rating;

        $base = UserInventory::with(['item', 'user:id,username,avatar'])
            ->where('user_id', '!=', $user->id)
            ->whereNotNull('pet_traits')
            ->whereHas('item', fn ($q) => $q->where('type', 'profile_pet'));

        $near = (clone $base)->whereBetween('pet_rating', [$rating - 250, $rating + 250])->inRandomOrder()->limit(6)->get();

        if ($near->count() < 6) {
            $more = (clone $base)->whereNotIn('id', $near->pluck('id'))
                ->orderByRaw('abs(pet_rating - ?)', [$rating])->limit(6 - $near->count())->get();
            $near = $near->concat($more);
        }

        $levels = PetService::levels($near->pluck('id')->all());

        return $near->map(fn ($pet) => $this->card($pet, $levels[$pet->id] ?? 1))->values()->all();
    }

    public function start(User $user, int $inventoryId, int $opponentId): array
    {
        $row = app(PetExpeditionService::class)->ownPet($user, $inventoryId);

        if (DB::table('pet_battles')->where('user_id', $user->id)->where('status', 'active')->exists()) {
            throw new RuntimeException('Сначала закончи начатый бой.');
        }

        if ($busy = PetLocks::reason($row->id)) {
            throw new RuntimeException($busy);
        }

        if ($this->fightsLeft($user) <= 0) {
            throw new RuntimeException('Бои на сегодня закончились — питомцам нужен отдых. Приходи завтра.');
        }

        $foe = UserInventory::with('item')->find($opponentId);
        if (! $foe || $foe->user_id === $user->id || ! $foe->pet_traits) {
            throw new RuntimeException('Соперник не найден.');
        }

        $levels = PetService::levels([$row->id, $foe->id]);

        $state = [
            'me' => $this->fighter($row, $levels[$row->id] ?? 1),
            'foe' => $this->fighter($foe, $levels[$foe->id] ?? 1),
            'round' => 0,
            'log' => [],
        ];

        $id = DB::table('pet_battles')->insertGetId([
            'user_id' => $user->id,
            'inventory_id' => $row->id,
            'opponent_inventory_id' => $foe->id,
            'status' => 'active',
            'state' => json_encode($state),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->show($user, $id);
    }

    public function show(User $user, int $id): array
    {
        $battle = DB::table('pet_battles')->find($id);

        if (! $battle || $battle->user_id !== $user->id) {
            throw new RuntimeException('Бой не найден.');
        }

        return $this->present($battle);
    }

    public function active(User $user): ?array
    {
        $battle = DB::table('pet_battles')->where('user_id', $user->id)->where('status', 'active')->first();

        return $battle ? $this->present($battle) : null;
    }

    /** Ход игрока: attack | defend | skill. */
    public function move(User $user, int $id, string $move): array
    {
        if (! in_array($move, ['attack', 'defend', 'skill'], true)) {
            throw new RuntimeException('Такого приёма нет.');
        }

        return DB::transaction(function () use ($user, $id, $move) {
            $battle = DB::table('pet_battles')->lockForUpdate()->find($id);

            if (! $battle || $battle->user_id !== $user->id || $battle->status !== 'active') {
                throw new RuntimeException('Бой уже закончен.');
            }

            $state = json_decode($battle->state, true);

            if ($move === 'skill' && $state['me']['cd'] > 0) {
                throw new RuntimeException('Умение ещё перезаряжается.');
            }

            $state = $this->round($state, $move, $this->aiMove($state['foe']));

            $winner = null;
            if ($state['foe']['hp'] <= 0) {
                $winner = 'me';
            } elseif ($state['me']['hp'] <= 0) {
                $winner = 'foe';
            } elseif ($state['round'] >= self::MAX_ROUNDS) {
                // Ничьей нет: побеждает тот, у кого больше здоровья в процентах
                $winner = $state['me']['hp'] / $state['me']['max'] >= $state['foe']['hp'] / $state['foe']['max'] ? 'me' : 'foe';
                $state['log'][] = ['who' => 'system', 'text' => 'Время вышло — судьи считают здоровье.'];
            }

            DB::table('pet_battles')->where('id', $id)->update(['state' => json_encode($state), 'updated_at' => now()]);

            if ($winner) {
                $this->finish($user, $battle->id, $winner === 'me');
            }

            return $this->present(DB::table('pet_battles')->find($id)) + ['balance' => (int) $user->fresh()->apex_coins];
        });
    }

    public function forfeit(User $user, int $id): array
    {
        return DB::transaction(function () use ($user, $id) {
            $battle = DB::table('pet_battles')->lockForUpdate()->find($id);

            if (! $battle || $battle->user_id !== $user->id || $battle->status !== 'active') {
                throw new RuntimeException('Бой уже закончен.');
            }

            $state = json_decode($battle->state, true);
            $state['log'][] = ['who' => 'system', 'text' => 'Ты сдался.'];
            DB::table('pet_battles')->where('id', $id)->update(['state' => json_encode($state)]);

            $this->finish($user, $id, false);

            return $this->present(DB::table('pet_battles')->find($id));
        });
    }

    /** Топ арены: питомцы с самым высоким рейтингом. */
    public function leaderboard(int $limit = 20): array
    {
        $rows = UserInventory::with(['item', 'user:id,username,avatar'])
            ->whereNotNull('pet_traits')
            ->where(fn ($q) => $q->where('pet_wins', '>', 0)->orWhere('pet_losses', '>', 0))
            ->orderByDesc('pet_rating')
            ->limit($limit)
            ->get();

        $levels = PetService::levels($rows->pluck('id')->all());

        return $rows->map(fn ($pet) => $this->card($pet, $levels[$pet->id] ?? 1))->values()->all();
    }

    /**
     * Конец недели: тройке лучших — призы, рейтинги всех — наполовину
     * к 1000, чтобы новый сезон был честным.
     */
    public function closeSeason(): array
    {
        $prizes = [20000, 10000, 5000];
        $top = UserInventory::with('user')->whereNotNull('pet_traits')
            ->where('pet_wins', '>', 0)
            ->orderByDesc('pet_rating')->limit(3)->get();

        $paid = [];
        foreach ($top as $place => $pet) {
            if (! $pet->user) {
                continue;
            }
            $amount = $prizes[$place];
            $week = now()->format('o-W');
            CoinService::credit($pet->user, $amount, CoinTransaction::SOURCE_OTHER, 'Арена питомцев: ' . ($place + 1) . ' место недели', "pet-arena:{$week}:{$pet->id}");
            $pet->user->notify(new ModerationNotification(
                'pet_arena_prize',
                'Твой питомец занял ' . ($place + 1) . " место на арене за неделю! +{$amount} ApexCoin.",
                ['link' => '/pets?tab=arena'],
            ));
            $paid[] = $pet->id;
        }

        DB::table('user_inventory')->whereNotNull('pet_traits')->where('pet_rating', '!=', 1000)
            ->get(['id', 'pet_rating'])
            ->each(fn ($pet) => DB::table('user_inventory')->where('id', $pet->id)
                ->update(['pet_rating' => 1000 + intdiv((int) $pet->pet_rating - 1000, 2)]));

        return $paid;
    }

    // ---- Бой ----

    private function fighter(UserInventory $pet, int $level): array
    {
        $species = $pet->item?->effect_value;
        $traits = PetTraits::present($pet->pet_traits, $pet->pet_serial);
        $r = self::RARITY_BONUS[$traits['rarity'] ?? 'common'] ?? 0;
        $element = self::element($species);
        $hp = 60 + $level * 6 + $r * 8;

        return [
            'inventory_id' => $pet->id,
            'species' => $species,
            'name' => $pet->pet_name ?: (\App\Domains\Players\Support\PetNames::species($species)),
            'traits' => $traits,
            'level' => $level,
            'element' => $element,
            'hp' => $hp,
            'max' => $hp,
            'atk' => (int) round(10 + $level * 1.2 + $r * 1.5),
            'def' => (int) round(5 + $level * 0.6 + $r),
            'spd' => (int) round(10 + $level * 0.4 + ($element === 'shadow' ? 2 : 0) + ($element === 'beast' ? 1 : 0)),
            'cd' => 0,
        ];
    }

    /** Компьютер: умение, когда готово; защита, когда мало здоровья. */
    private function aiMove(array $foe): string
    {
        if ($foe['cd'] === 0 && random_int(1, 100) <= 65) {
            return 'skill';
        }

        if ($foe['hp'] / $foe['max'] < 0.35 && random_int(1, 100) <= 35) {
            return 'defend';
        }

        return 'attack';
    }

    private function round(array $state, string $myMove, string $foeMove): array
    {
        $state['round']++;
        $moves = ['me' => $myMove, 'foe' => $foeMove];

        // Быстрее — бьёт первым; при равенстве — монетка
        $order = $state['me']['spd'] === $state['foe']['spd']
            ? (random_int(0, 1) ? ['me', 'foe'] : ['foe', 'me'])
            : ($state['me']['spd'] > $state['foe']['spd'] ? ['me', 'foe'] : ['foe', 'me']);

        $thorns = ['me' => 0.0, 'foe' => 0.0];

        foreach ($order as $who) {
            $other = $who === 'me' ? 'foe' : 'me';

            if ($state[$who]['hp'] <= 0 || $state[$other]['hp'] <= 0) {
                break;
            }

            $move = $moves[$who];
            $attacker = $state[$who];
            $name = $attacker['name'];

            if ($move === 'defend') {
                $heal = (int) round($attacker['max'] * 0.06);
                $state[$who]['hp'] = min($attacker['max'], $attacker['hp'] + $heal);
                $state['log'][] = ['who' => $who, 'move' => 'defend', 'text' => "{$name} закрывается и восстанавливает {$heal} здоровья."];
                continue;
            }

            $skill = $move === 'skill' ? self::SKILLS[$attacker['element']] : null;
            $power = $skill['power'] ?? 1.0;

            $damage = $attacker['atk'] * $power * random_int(90, 110) / 100;
            $damage -= ($skill['pierce'] ?? false) ? 0 : $state[$other]['def'] * 0.6;

            if ((self::STRONG[$attacker['element']] ?? null) === $state[$other]['element']) {
                $damage *= 1.25;
            } elseif ((self::STRONG[$state[$other]['element']] ?? null) === $attacker['element']) {
                $damage *= 0.8;
            }

            $crit = random_int(1, 100) <= ($skill['crit'] ?? 8);
            if ($crit) {
                $damage *= 1.5;
            }

            if ($moves[$other] === 'defend') {
                $damage *= 0.4;
            }

            $damage = max(1, (int) round($damage));
            $state[$other]['hp'] = max(0, $state[$other]['hp'] - $damage);

            $text = $skill
                ? "{$name} применяет «{$skill['name']}» — {$damage} урона"
                : "{$name} атакует — {$damage} урона";
            if ($crit) {
                $text .= ', критический удар!';
            }

            if ($skill) {
                $state[$who]['cd'] = self::SKILL_COOLDOWN;

                if (isset($skill['heal'])) {
                    $heal = (int) round($attacker['max'] * $skill['heal']);
                    $state[$who]['hp'] = min($attacker['max'], $state[$who]['hp'] + $heal);
                    $text .= " и лечится на {$heal}";
                }

                if (isset($skill['thorns'])) {
                    $thorns[$who] = $skill['thorns'];
                }
            }

            // Шипы противника: часть урона возвращается
            if ($thorns[$other] > 0 && $state[$who]['hp'] > 0) {
                $back = max(1, (int) round($damage * $thorns[$other]));
                $state[$who]['hp'] = max(0, $state[$who]['hp'] - $back);
                $text .= ", шипы возвращают {$back}";
            }

            $state['log'][] = ['who' => $who, 'move' => $move, 'damage' => $damage, 'crit' => $crit, 'text' => $text . '.'];
        }

        // Перезарядка: умение, применённое в этом раунде, ждёт ещё SKILL_COOLDOWN раундов
        foreach (['me', 'foe'] as $who) {
            if ($moves[$who] !== 'skill' && $state[$who]['cd'] > 0) {
                $state[$who]['cd']--;
            }
        }

        $state['log'] = array_slice($state['log'], -40);
        $state['last'] = $moves;

        return $state;
    }

    private function finish(User $user, int $battleId, bool $won): void
    {
        $battle = DB::table('pet_battles')->find($battleId);
        $mine = UserInventory::find($battle->inventory_id);
        $foe = UserInventory::find($battle->opponent_inventory_id);

        $myRating = (int) ($mine?->pet_rating ?? 1000);
        $foeRating = (int) ($foe?->pet_rating ?? 1000);
        $expected = 1 / (1 + 10 ** (($foeRating - $myRating) / 400));
        $change = (int) round(self::K * (($won ? 1 : 0) - $expected));

        // Защищался не хозяин, а компьютер — соперник меняется вдвое слабее
        $foeChange = -intdiv($change, 2);

        $level = PetService::levels([$battle->inventory_id])[$battle->inventory_id] ?? 1;
        $coins = 0;

        if ($mine) {
            $mine->forceFill([
                'pet_rating' => max(0, $myRating + $change),
                'pet_wins' => $mine->pet_wins + ($won ? 1 : 0),
                'pet_losses' => $mine->pet_losses + ($won ? 0 : 1),
                'pet_xp' => $mine->pet_xp + ($won ? 4 : 1),
            ])->save();
        }

        if ($foe) {
            $foe->forceFill([
                'pet_rating' => max(0, $foeRating + $foeChange),
                'pet_wins' => $foe->pet_wins + ($won ? 0 : 1),
                'pet_losses' => $foe->pet_losses + ($won ? 1 : 0),
            ])->save();
        }

        if ($won) {
            $coins = (int) round((40 + $level * 3) * (PetPerks::arenaBonus($level) ? 1.25 : 1));
            CoinService::credit($user, $coins, CoinTransaction::SOURCE_OTHER, 'Победа на арене питомцев', "pet-battle:{$battleId}");
        }

        DB::table('pet_battles')->where('id', $battleId)->update([
            'status' => $won ? 'won' : 'lost',
            'rating_change' => $change,
            'coins' => $coins,
            'updated_at' => now(),
        ]);
    }

    private function present(object $battle): array
    {
        $state = json_decode($battle->state, true);

        return [
            'id' => $battle->id,
            'status' => $battle->status,
            'round' => $state['round'],
            'me' => $state['me'],
            'foe' => $state['foe'] + ['owner' => $this->ownerOf($battle->opponent_inventory_id)],
            'log' => $state['log'],
            'last' => $state['last'] ?? null,
            'rating_change' => (int) $battle->rating_change,
            'coins' => (int) $battle->coins,
            'skills' => self::SKILLS,
        ];
    }

    private function ownerOf(int $inventoryId): ?array
    {
        $user = User::find(UserInventory::whereKey($inventoryId)->value('user_id'), ['id', 'username', 'avatar']);

        return $user ? ['id' => $user->id, 'username' => $user->username, 'avatar_url' => $user->avatar_url] : null;
    }

    /** Карточка питомца для списков (соперники, топ). */
    public function card(UserInventory $pet, int $level): array
    {
        $species = $pet->item?->effect_value;

        return [
            'inventory_id' => $pet->id,
            'species' => $species,
            'name' => $pet->pet_name ?: \App\Domains\Players\Support\PetNames::species($species),
            'traits' => PetTraits::present($pet->pet_traits, $pet->pet_serial),
            'level' => $level,
            'rating' => (int) $pet->pet_rating,
            'wins' => (int) $pet->pet_wins,
            'losses' => (int) $pet->pet_losses,
            'element' => self::element($species),
            'owner' => $pet->user ? ['id' => $pet->user->id, 'username' => $pet->user->username, 'avatar_url' => $pet->user->avatar_url] : null,
        ];
    }

    private function bestLevel(User $user): int
    {
        $ids = UserInventory::where('user_id', $user->id)->whereNotNull('pet_traits')->pluck('id')->all();

        return max([1, ...array_values(PetService::levels($ids))]);
    }
}
