<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Support\Dungeons;
use App\Domains\Players\Support\PetLocks;
use App\Domains\Players\Support\PetTraits;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Рейд «Разлом»: два раза в день (18:00 и 22:00) окно в 30 минут, в нём
 * собираются группы по 2–3 игрока, у каждого отряд из трёх питомцев.
 * Бой пошаговый и синхронный: у раунда 30 секунд, приёмы выбирают все
 * разом. Босс почти неуязвим (получает 15% урона), пока в раунде не
 * ударили питомцы разных стихий от разных игроков — тогда броня пробита
 * и урон идёт с надбавкой.
 *
 * Ливер (вышел из боя или молчал два раунда подряд) получает штраф:
 * бан рейдов на 6 часов, питомцы измотаны (энергия в ноль), а босс
 * усиливается на 15% за каждого ушедшего.
 */
class RaidService
{
    public const SLOTS = ['18:00', '22:00'];
    public const LOBBY_MINUTES = 30;
    public const ROUND_SECONDS = 30;
    public const MIN_LEVEL = 30;
    public const SQUAD = 3;
    public const MAX_MEMBERS = 3;
    public const MIN_MEMBERS = 2;

    public const BOSS_NAME = 'Разлом';
    public const BOSS_ELEMENT = 'shadow';
    public const BOSS_HP_PER_PLAYER = 9000;
    public const BOSS_ATK = 300;
    public const ENRAGE_PER_ROUND = 0.06;
    public const DESERTER_ATK = 0.15;
    public const PIERCE = 0.15;
    public const SYNERGY_BONUS = 1.25;
    public const SKILL_MULT = 1.7;
    public const SKILL_COOLDOWN = 2;
    public const DEFEND_FACTOR = 0.4;
    public const HP_FACTOR = 3;
    public const AFK_LIMIT = 2;
    public const BAN_HOURS = 6;
    public const MOVES = ['attack', 'skill', 'defend'];

    public const REWARD = ['shards' => 10, 'coins' => 5000];

    // ---- Расписание ----

    /** Начало открытого окна рейда или null. */
    public static function currentSlot(?Carbon $now = null): ?Carbon
    {
        $now ??= now();

        foreach (self::SLOTS as $time) {
            [$h, $m] = array_map('intval', explode(':', $time));
            $start = $now->copy()->setTime($h, $m, 0);

            if ($now->gte($start) && $now->lt($start->copy()->addMinutes(self::LOBBY_MINUTES))) {
                return $start;
            }
        }

        return null;
    }

    public static function nextSlot(?Carbon $now = null): Carbon
    {
        $now ??= now();
        $candidates = [];

        foreach ([0, 1] as $day) {
            foreach (self::SLOTS as $time) {
                [$h, $m] = array_map('intval', explode(':', $time));
                $start = $now->copy()->addDays($day)->setTime($h, $m, 0);
                if ($start->gt($now)) {
                    $candidates[] = $start;
                }
            }
        }

        return collect($candidates)->sort()->first();
    }

    // ---- Витрина ----

    public function state(User $user): array
    {
        $this->tick();

        $slot = self::currentSlot();
        $mine = $this->activeRaidOf($user);

        $lobbies = $slot ? DB::table('raids as r')
            ->join('users as u', 'u.id', '=', 'r.host_id')
            ->where('r.status', 'lobby')->where('r.slot_at', $slot)
            ->get(['r.id', 'u.username'])
            ->map(function ($r) {
                $members = DB::table('raid_members')->where('raid_id', $r->id)->whereNull('left_at')->count();

                return ['id' => $r->id, 'host' => $r->username, 'members' => $members];
            })->filter(fn ($l) => $l['members'] < self::MAX_MEMBERS)->values()->all() : [];

        // Итог последнего боя — час после окончания
        $result = $mine ? null : DB::table('raids as r')
            ->join('raid_members as m', 'm.raid_id', '=', 'r.id')
            ->where('m.user_id', $user->id)->whereIn('r.status', ['won', 'lost'])
            ->where('r.updated_at', '>=', now()->subHour())
            ->orderByDesc('r.id')->value('r.id');

        return [
            'slot' => $slot?->toIso8601String(),
            'slot_ends_at' => $slot?->copy()->addMinutes(self::LOBBY_MINUTES)->toIso8601String(),
            'next_slot' => self::nextSlot()->toIso8601String(),
            'slots' => self::SLOTS,
            'lobbies' => $lobbies,
            'mine' => $mine ? $this->present($mine, $user) : ($result ? $this->present(DB::table('raids')->find($result), $user) : null),
            'banned_until' => $user->raid_banned_until && Carbon::parse($user->raid_banned_until)->isFuture() ? Carbon::parse($user->raid_banned_until)->toIso8601String() : null,
            'shards' => (int) DB::table('users')->where('id', $user->id)->value('rift_shards'),
            'min_level' => self::MIN_LEVEL,
            'rules' => ['round_seconds' => self::ROUND_SECONDS, 'pierce' => self::PIERCE, 'synergy_bonus' => self::SYNERGY_BONUS, 'ban_hours' => self::BAN_HOURS, 'reward' => self::REWARD],
        ];
    }

    public function show(User $user, int $raidId): array
    {
        $this->tick();
        $raid = DB::table('raids')->find($raidId);
        $member = $raid ? DB::table('raid_members')->where('raid_id', $raidId)->where('user_id', $user->id)->first() : null;

        if (! $raid || ! $member) {
            throw new RuntimeException('Рейд не найден.');
        }

        return $this->present($raid, $user);
    }

    // ---- Группа ----

    public function create(User $user, array $squadIds): array
    {
        $slot = $this->assertCanJoin($user);

        return DB::transaction(function () use ($user, $squadIds, $slot) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $units = $this->buildUnits($user, $squadIds);

            $id = DB::table('raids')->insertGetId([
                'slot_at' => $slot, 'status' => 'lobby', 'host_id' => $user->id,
                'log' => json_encode([]), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->addMember($id, $user, $units);

            return $this->present(DB::table('raids')->find($id), $user);
        });
    }

    public function join(User $user, int $raidId, array $squadIds): array
    {
        $slot = $this->assertCanJoin($user);

        return DB::transaction(function () use ($user, $raidId, $squadIds, $slot) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $raid = DB::table('raids')->where('id', $raidId)->lockForUpdate()->first();

            if (! $raid || $raid->status !== 'lobby' || ! Carbon::parse($raid->slot_at)->equalTo($slot)) {
                throw new RuntimeException('Эта группа уже закрыта.');
            }

            if (DB::table('raid_members')->where('raid_id', $raidId)->whereNull('left_at')->count() >= self::MAX_MEMBERS) {
                throw new RuntimeException('В группе уже трое.');
            }

            $this->addMember($raidId, $user, $this->buildUnits($user, $squadIds));

            return $this->present(DB::table('raids')->find($raidId), $user);
        });
    }

    /** Выйти из набора или из боя (из боя — со штрафом). */
    public function leave(User $user, int $raidId): array
    {
        return DB::transaction(function () use ($user, $raidId) {
            $raid = DB::table('raids')->where('id', $raidId)->lockForUpdate()->first();
            $member = $raid ? DB::table('raid_members')->where('raid_id', $raidId)->where('user_id', $user->id)->whereNull('left_at')->lockForUpdate()->first() : null;

            if (! $raid || ! $member || ! in_array($raid->status, ['lobby', 'active'], true)) {
                throw new RuntimeException('Ты не в этом рейде.');
            }

            if ($raid->status === 'lobby') {
                DB::table('raid_members')->where('id', $member->id)->delete();
                $rest = DB::table('raid_members')->where('raid_id', $raidId)->orderBy('id')->first();

                if (! $rest) {
                    DB::table('raids')->where('id', $raidId)->delete();
                } elseif ((int) $raid->host_id === $user->id) {
                    DB::table('raids')->where('id', $raidId)->update(['host_id' => $rest->user_id, 'updated_at' => now()]);
                }

                return ['left' => true, 'penalty' => false];
            }

            $this->desert($raid, $member, 'вышел из боя');
            $this->finishIfDead($raidId);

            return ['left' => true, 'penalty' => true];
        });
    }

    public function start(User $user, int $raidId): array
    {
        return DB::transaction(function () use ($user, $raidId) {
            $raid = DB::table('raids')->where('id', $raidId)->lockForUpdate()->first();

            if (! $raid || $raid->status !== 'lobby' || (int) $raid->host_id !== $user->id) {
                throw new RuntimeException('Начать бой может только хозяин группы.');
            }

            $count = DB::table('raid_members')->where('raid_id', $raidId)->whereNull('left_at')->count();

            if ($count < self::MIN_MEMBERS) {
                throw new RuntimeException('Нужно минимум ' . self::MIN_MEMBERS . ' игрока.');
            }

            $hp = self::BOSS_HP_PER_PLAYER * $count;

            DB::table('raids')->where('id', $raidId)->update([
                'status' => 'active', 'boss_hp' => $hp, 'boss_max' => $hp, 'round' => 1,
                'round_ends_at' => now()->addSeconds(self::ROUND_SECONDS),
                'log' => json_encode([self::BOSS_NAME . ' пробуждается. Бейте разными стихиями от разных игроков — иначе броня не пробьётся.']),
                'updated_at' => now(),
            ]);

            return $this->present(DB::table('raids')->find($raidId), $user);
        });
    }

    /** Приёмы на раунд. Когда сдали все, раунд закрывается сразу. */
    public function submit(User $user, int $raidId, array $moves): array
    {
        return DB::transaction(function () use ($user, $raidId, $moves) {
            $raid = DB::table('raids')->where('id', $raidId)->lockForUpdate()->first();
            $member = $raid ? DB::table('raid_members')->where('raid_id', $raidId)->where('user_id', $user->id)->whereNull('left_at')->first() : null;

            if (! $raid || ! $member || $raid->status !== 'active') {
                throw new RuntimeException('Сейчас бой не идёт.');
            }

            $alive = collect(json_decode($member->units, true))->where('hp', '>', 0)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $clean = [];

            foreach ($moves as $id => $move) {
                if (! in_array((int) $id, $alive, true) || ! in_array($move, self::MOVES, true)) {
                    throw new RuntimeException('Приём выбран неверно.');
                }
                $clean[(int) $id] = $move;
            }

            DB::table('raid_members')->where('id', $member->id)->update(['moves' => json_encode($clean), 'afk_rounds' => 0, 'updated_at' => now()]);

            $pending = DB::table('raid_members')->where('raid_id', $raidId)->whereNull('left_at')->whereNull('moves')->count();
            if ($pending === 0) {
                $this->resolveRound($raidId);
            }

            return $this->present(DB::table('raids')->find($raidId), $user);
        });
    }

    // ---- Планировщик ----

    /** Закрыть просроченные раунды и неначатые группы. */
    public function tick(): void
    {
        DB::table('raids')->where('status', 'lobby')
            ->where('slot_at', '<=', now()->subMinutes(self::LOBBY_MINUTES))
            ->update(['status' => 'expired', 'updated_at' => now()]);

        $due = DB::table('raids')->where('status', 'active')->where('round_ends_at', '<=', now())->pluck('id');

        foreach ($due as $id) {
            DB::transaction(function () use ($id) {
                // Пока раунд просрочен — закрываем (на случай долгой паузы), но не больше пяти подряд
                for ($i = 0; $i < 5; $i++) {
                    $raid = DB::table('raids')->where('id', $id)->lockForUpdate()->first();

                    if (! $raid || $raid->status !== 'active' || ! $raid->round_ends_at || Carbon::parse($raid->round_ends_at)->isFuture()) {
                        break;
                    }

                    $this->resolveRound($id);
                }
            });
        }
    }

    // ---- Раунд ----

    private function resolveRound(int $raidId): void
    {
        $raid = DB::table('raids')->where('id', $raidId)->lockForUpdate()->first();
        $members = DB::table('raid_members')->where('raid_id', $raidId)->whereNull('left_at')->get();
        $log = json_decode((string) $raid->log, true) ?: [];

        // Молчуны: два раунда без приёмов — это уже ливер
        foreach ($members as $member) {
            if ($member->moves === null) {
                $afk = (int) $member->afk_rounds + 1;
                DB::table('raid_members')->where('id', $member->id)->update(['afk_rounds' => $afk]);

                if ($afk >= self::AFK_LIMIT) {
                    $this->desert(DB::table('raids')->find($raidId), $member, 'пропал из боя');
                }
            }
        }

        $raid = DB::table('raids')->find($raidId);
        $members = DB::table('raid_members')->where('raid_id', $raidId)->whereNull('left_at')->get();

        $units = [];
        foreach ($members as $member) {
            $moves = json_decode((string) $member->moves, true) ?: [];
            foreach (json_decode($member->units, true) as $unit) {
                $unit['move'] = $unit['hp'] > 0 ? ($moves[$unit['id']] ?? ($member->moves === null ? 'defend' : 'attack')) : null;
                $unit['owner'] = (int) $member->user_id;
                $unit['member_id'] = $member->id;
                $units[] = $unit;
            }
        }

        // Синергия: в одном раунде бьют разные стихии от разных игроков
        $acting = array_filter($units, fn ($u) => $u['hp'] > 0 && in_array($u['move'], ['attack', 'skill'], true));
        $elements = array_unique(array_column($acting, 'element'));
        $owners = array_unique(array_column($acting, 'owner'));
        $synergy = count($elements) >= 2 && count($owners) >= 2;

        $dealt = 0;
        foreach ($units as &$unit) {
            if ($unit['hp'] <= 0 || ! in_array($unit['move'], ['attack', 'skill'], true)) {
                continue;
            }

            $hit = $unit['atk'] * random_int(90, 110) / 100;

            if ($unit['move'] === 'skill' && $unit['cd'] <= 0) {
                $hit *= self::SKILL_MULT;
                $unit['cd'] = self::SKILL_COOLDOWN + 1;
            }

            $dealt += (int) round($hit * ($synergy ? self::SYNERGY_BONUS : self::PIERCE));
        }
        unset($unit);

        $bossHp = max(0, (int) $raid->boss_hp - $dealt);
        $hits = [];

        if ($bossHp > 0) {
            $alive = array_keys(array_filter($units, fn ($u) => $u['hp'] > 0));
            shuffle($alive);

            $atk = self::BOSS_ATK * (1 + self::ENRAGE_PER_ROUND * ((int) $raid->round - 1)) * (1 + self::DESERTER_ATK * (int) $raid->deserters);

            foreach (array_slice($alive, 0, 3) as $index) {
                $defended = $units[$index]['move'] === 'defend';
                $damage = max(1, (int) round($atk * random_int(85, 115) / 100 - $units[$index]['def'] * 0.5));
                $damage = (int) round($damage * ($defended ? self::DEFEND_FACTOR : 1));

                $units[$index]['hp'] = max(0, $units[$index]['hp'] - $damage);
                $hits[] = ['unit' => $units[$index]['id'], 'name' => $units[$index]['name'], 'damage' => $damage, 'defended' => $defended, 'down' => $units[$index]['hp'] <= 0];
            }
        }

        // Записать здоровье и снять приёмы
        foreach ($members as $member) {
            $mine = [];
            foreach (json_decode($member->units, true) as $unit) {
                $found = collect($units)->firstWhere(fn ($u) => $u['id'] === $unit['id'] && $u['member_id'] === $member->id);
                $unit['hp'] = $found['hp'] ?? $unit['hp'];
                $unit['cd'] = max(0, ($found['cd'] ?? $unit['cd']) - 1);
                $mine[] = $unit;
            }

            DB::table('raid_members')->where('id', $member->id)->update(['units' => json_encode($mine), 'moves' => null, 'updated_at' => now()]);
        }

        $log[] = "Раунд {$raid->round}: " . ($synergy ? 'броня пробита связкой стихий' : 'броня держится') . ", урон по боссу {$dealt}.";
        foreach ($hits as $hit) {
            $log[] = "{$hit['name']} получает {$hit['damage']}" . ($hit['defended'] ? ' (в защите)' : '') . ($hit['down'] ? ' и падает.' : '.');
        }

        $update = [
            'boss_hp' => $bossHp,
            'log' => json_encode(array_slice($log, -40)),
            'last_round' => json_encode(['round' => (int) $raid->round, 'dealt' => $dealt, 'synergy' => $synergy, 'hits' => $hits]),
            'updated_at' => now(),
        ];

        if ($bossHp <= 0) {
            DB::table('raids')->where('id', $raidId)->update($update + ['status' => 'won', 'round_ends_at' => null]);
            $this->reward($raidId);

            return;
        }

        $update['round'] = (int) $raid->round + 1;
        $update['round_ends_at'] = now()->addSeconds(self::ROUND_SECONDS);
        DB::table('raids')->where('id', $raidId)->update($update);

        $this->finishIfDead($raidId);
    }

    /** Все бойцы пали — рейд проигран. */
    private function finishIfDead(int $raidId): void
    {
        $alive = DB::table('raid_members')->where('raid_id', $raidId)->whereNull('left_at')->get()
            ->flatMap(fn ($m) => json_decode($m->units, true))->where('hp', '>', 0)->count();

        if ($alive === 0) {
            DB::table('raids')->where('id', $raidId)->where('status', 'active')->update(['status' => 'lost', 'round_ends_at' => null, 'updated_at' => now()]);
        }
    }

    // ---- Ливеры и награды ----

    /** Ливер: бан рейдов, питомцы измотаны, босс сильнее. */
    private function desert(object $raid, object $member, string $why): void
    {
        $units = collect(json_decode($member->units, true))->map(fn ($u) => [...$u, 'hp' => 0])->all();

        DB::table('raid_members')->where('id', $member->id)->update(['left_at' => now(), 'units' => json_encode($units), 'moves' => null]);
        DB::table('raids')->where('id', $raid->id)->update(['deserters' => (int) $raid->deserters + 1]);
        DB::table('users')->where('id', $member->user_id)->update(['raid_banned_until' => now()->addHours(self::BAN_HOURS)]);
        DB::table('user_inventory')->whereIn('id', array_column($units, 'id'))->update(['pet_energy' => 0, 'pet_energy_at' => now()]);

        $log = json_decode((string) $raid->log, true) ?: [];
        $name = DB::table('users')->where('id', $member->user_id)->value('username');
        $log[] = "{$name} {$why}: босс усилился на " . (int) (self::DESERTER_ATK * 100) . '%.';
        DB::table('raids')->where('id', $raid->id)->update(['log' => json_encode(array_slice($log, -40))]);
    }

    private function reward(int $raidId): void
    {
        $gear = collect(Dungeons::GEAR)->filter(fn ($g) => empty($g['relic']) && $g['stage'] === 8)->keys()->all();

        foreach (DB::table('raid_members')->where('raid_id', $raidId)->whereNull('left_at')->whereNull('rewarded_at')->get() as $member) {
            $user = User::find($member->user_id);

            CoinService::credit($user, self::REWARD['coins'], CoinTransaction::SOURCE_OTHER, 'Рейд «Разлом»', "raid-coins:{$raidId}:{$user->id}");
            DB::table('users')->where('id', $user->id)->increment('rift_shards', self::REWARD['shards']);
            app(PetGearService::class)->grant($user, $gear[array_rand($gear)], 'legendary');

            QuestService::track($user, 'raid_win');
            DB::table('raid_members')->where('id', $member->id)->update(['rewarded_at' => now()]);
        }
    }

    // ---- Состав ----

    private function assertCanJoin(User $user): Carbon
    {
        $slot = self::currentSlot();

        if (! $slot) {
            throw new RuntimeException('Рейд открывается в ' . implode(' и ', self::SLOTS) . ' и ждёт 30 минут.');
        }

        $banned = DB::table('users')->where('id', $user->id)->value('raid_banned_until');
        if ($banned && Carbon::parse($banned)->isFuture()) {
            throw new RuntimeException('Ты бросил группу: рейды закрыты до ' . Carbon::parse($banned)->format('H:i d.m') . '.');
        }

        if ($this->activeRaidOf($user)) {
            throw new RuntimeException('Ты уже в рейде.');
        }

        $played = DB::table('raid_members as m')->join('raids as r', 'r.id', '=', 'm.raid_id')
            ->where('m.user_id', $user->id)->where('r.slot_at', $slot)->whereIn('r.status', ['won', 'lost'])->exists();
        if ($played) {
            throw new RuntimeException('В этом окне ты уже ходил в рейд — приходи в следующее.');
        }

        return $slot;
    }

    private function addMember(int $raidId, User $user, array $units): void
    {
        DB::table('raid_members')->insert(['raid_id' => $raidId, 'user_id' => $user->id, 'units' => json_encode($units), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Три своих свободных питомца → бойцы с характеристиками. */
    private function buildUnits(User $user, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (count($ids) !== self::SQUAD) {
            throw new RuntimeException('В отряд нужно ровно ' . self::SQUAD . ' питомца.');
        }

        $pets = UserInventory::with('item')->where('user_id', $user->id)->whereNotNull('pet_traits')->whereIn('id', $ids)->get();

        if ($pets->count() !== self::SQUAD || $pets->contains(fn ($p) => $p->item?->type !== ShopItem::TYPE_PROFILE_PET)) {
            throw new RuntimeException('Это не твои питомцы.');
        }

        $levels = PetService::levels($ids);
        $arena = app(PetArenaService::class);
        $units = [];

        foreach ($pets as $pet) {
            if ($busy = PetLocks::reason($pet->id)) {
                throw new RuntimeException(($pet->pet_name ?: $pet->item->name) . ': ' . $busy);
            }

            $level = $levels[$pet->id] ?? 1;

            if ($level < self::MIN_LEVEL) {
                throw new RuntimeException('В рейд берут питомцев от ' . self::MIN_LEVEL . ' уровня.');
            }

            $card = $arena->fighterFor($pet, $level);
            $hp = (int) $card['hp'] * self::HP_FACTOR;

            $units[] = [
                'id' => $pet->id,
                'name' => $pet->pet_name ?: $pet->item->name,
                'species' => $pet->item->effect_value,
                'traits' => PetTraits::present($pet->pet_traits, $pet->pet_serial),
                'element' => $card['element'],
                'level' => $level,
                'hp' => $hp, 'max' => $hp,
                'atk' => (int) $card['atk'], 'def' => (int) $card['def'],
                'cd' => 0,
            ];
        }

        return $units;
    }

    private function activeRaidOf(User $user): ?object
    {
        return DB::table('raids as r')->join('raid_members as m', 'm.raid_id', '=', 'r.id')
            ->where('m.user_id', $user->id)->whereNull('m.left_at')->whereIn('r.status', ['lobby', 'active'])
            ->orderByDesc('r.id')->first(['r.*']);
    }

    /** @return array<string, mixed> */
    private function present(object $raid, User $viewer): array
    {
        $members = DB::table('raid_members as m')->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.raid_id', $raid->id)->orderBy('m.id')->get(['m.*', 'u.username']);

        $mine = $members->firstWhere('user_id', $viewer->id);

        return [
            'id' => $raid->id,
            'status' => $raid->status,
            'host' => (int) $raid->host_id === $viewer->id,
            'boss' => ['name' => self::BOSS_NAME, 'element' => self::BOSS_ELEMENT, 'hp' => (int) $raid->boss_hp, 'max' => (int) $raid->boss_max],
            'round' => (int) $raid->round,
            'round_ends_at' => $raid->round_ends_at ? Carbon::parse($raid->round_ends_at)->toIso8601String() : null,
            'deserters' => (int) $raid->deserters,
            'members' => $members->map(fn ($m) => [
                'user' => ['id' => (int) $m->user_id, 'username' => $m->username],
                'me' => (int) $m->user_id === $viewer->id,
                'ready' => $m->moves !== null,
                'left' => (bool) $m->left_at,
                'units' => json_decode($m->units, true),
            ])->values()->all(),
            'my_moves' => $mine ? (json_decode((string) $mine->moves, true) ?: null) : null,
            'last_round' => $raid->last_round ? json_decode($raid->last_round, true) : null,
            'log' => array_slice(json_decode((string) $raid->log, true) ?: [], -12),
            'won' => $raid->status === 'won' && $mine && $mine->rewarded_at ? self::REWARD : null,
        ];
    }
}
