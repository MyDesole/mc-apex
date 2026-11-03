<?php

namespace App\Domains\Players\Services;

use App\Domains\Minecraft\Models\MinecraftGrant;
use App\Domains\Players\Support\Dungeons;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Services\RubleService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Лавка: за Рубли — привилегии Minecraft-сервера, за Осколки Разлома —
 * уникальные вещи игры. Рубли привязаны к аккаунту и тратятся только
 * здесь и на ускорения; вывода и передачи нет.
 *
 * Привилегии выдаёт плагин сервера: покупка кладёт команды в очередь
 * minecraft_grants (только `lp …`, ник проверен), сами команды живут
 * в CATALOG и при необходимости правятся администрацией.
 */
class LavkaService
{
    /** Привилегии за Рубли. */
    public const PRIVILEGES = [
        'vip_30' => [
            'name' => 'VIP на 30 дней',
            'about' => 'Префикс VIP и привилегии на сервере на месяц.',
            'price' => 300,
            'commands' => ['lp user {player} parent addtemp vip 30d'],
        ],
        'fly_7' => [
            'name' => 'Полёт на 7 дней',
            'about' => 'Команда /fly на лобби и выживании на неделю.',
            'price' => 120,
            'commands' => ['lp user {player} permission settemp essentials.fly true 7d'],
        ],
        'star_prefix' => [
            'name' => 'Звёздный префикс',
            'about' => 'Золотая звезда перед ником в чате сервера. Навсегда.',
            'price' => 150,
            'commands' => ['lp user {player} permission set prefix.star true'],
        ],
    ];

    /** Вещи за Осколки Разлома. */
    public const SHARDS = [
        'legend_gear' => ['name' => 'Ящик легендарной экипировки', 'about' => 'Случайный легендарный предмет самой высокой ступени.', 'price' => 30],
        'throne_core' => ['name' => 'Печать владыки', 'about' => 'Ядро босса Чертога владыки для ковки.', 'price' => 40],
        'vigor' => ['name' => 'Эликсир бодрости', 'about' => 'Полностью восстанавливает энергию выбранного питомца.', 'price' => 15, 'needs_pet' => true],
    ];

    public function state(User $user): array
    {
        $recent = DB::table('privilege_orders')->where('user_id', $user->id)->where('created_at', '>=', now()->subDay())->pluck('item_key')->all();

        return [
            'rubles' => RubleService::balance($user),
            'shards' => (int) DB::table('users')->where('id', $user->id)->value('rift_shards'),
            'linked' => (bool) $user->minecraft_username,
            'nick' => $user->minecraft_username,
            'privileges' => collect(self::PRIVILEGES)->map(fn ($p, $key) => [
                'key' => $key, 'name' => $p['name'], 'about' => $p['about'], 'price' => $p['price'], 'cooldown' => in_array($key, $recent, true),
            ])->values()->all(),
            'shard_items' => collect(self::SHARDS)->map(fn ($p, $key) => ['key' => $key, ...$p])->values()->all(),
            'orders' => DB::table('privilege_orders as o')->leftJoin('minecraft_grants as g', 'g.id', '=', 'o.grant_id')
                ->where('o.user_id', $user->id)->orderByDesc('o.id')->limit(8)
                ->get(['o.item_key', 'o.price', 'o.created_at', 'g.status'])
                ->map(fn ($o) => ['name' => self::PRIVILEGES[$o->item_key]['name'] ?? $o->item_key, 'price' => (int) $o->price, 'status' => $o->status ?? 'pending', 'at' => $o->created_at])->all(),
        ];
    }

    /** Купить привилегию за Рубли. */
    public function buyPrivilege(User $user, string $key): array
    {
        $item = self::PRIVILEGES[$key] ?? null;

        if (! $item) {
            throw new RuntimeException('Такой привилегии нет.');
        }

        $nick = (string) $user->minecraft_username;

        if (! preg_match('/^[A-Za-z0-9_]{3,16}$/', $nick)) {
            throw new RuntimeException('Привяжи ник Minecraft в профиле: привилегии выдаются на сервере по нику.');
        }

        DB::transaction(function () use ($user, $key, $item, $nick) {
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();

            // Раз в сутки на одну привилегию: нажатие дважды не купит её дважды
            if (DB::table('privilege_orders')->where('user_id', $user->id)->where('item_key', $key)->where('created_at', '>=', now()->subDay())->exists()) {
                throw new RuntimeException('Эту привилегию уже покупали сегодня.');
            }

            RubleService::spend($user, $item['price'], 'Привилегия: ' . $item['name'], null, RubleService::SOURCE_PRIVILEGE);

            $grant = MinecraftGrant::create([
                'user_id' => $user->id,
                'shop_item_id' => null,
                'minecraft_username' => $nick,
                'commands' => array_map(fn (string $c) => str_replace('{player}', $nick, $c), $item['commands']),
                'status' => MinecraftGrant::PENDING,
            ]);

            DB::table('privilege_orders')->insert([
                'user_id' => $user->id, 'item_key' => $key, 'price' => $item['price'], 'grant_id' => $grant->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return $this->state($user->fresh());
    }

    /** Купить вещь за Осколки Разлома. */
    public function buyWithShards(User $user, string $key, ?int $petId = null): array
    {
        $item = self::SHARDS[$key] ?? null;

        if (! $item) {
            throw new RuntimeException('Такой вещи нет.');
        }

        DB::transaction(function () use ($user, $key, $item, $petId) {
            $row = DB::table('users')->where('id', $user->id)->lockForUpdate()->first(['id', 'rift_shards']);

            if ((int) $row->rift_shards < $item['price']) {
                throw new RuntimeException('Не хватает Осколков Разлома: нужно ' . $item['price'] . '.');
            }

            match ($key) {
                'legend_gear' => $this->grantGear($user),
                'throne_core' => DB::table('dungeon_materials')->upsert(
                    ['user_id' => $user->id, 'material' => 'core_throne', 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()],
                    ['user_id', 'material'],
                    ['quantity' => DB::raw('quantity + 1')],
                ),
                'vigor' => $this->restoreEnergy($user, $petId),
            };

            DB::table('users')->where('id', $user->id)->update(['rift_shards' => (int) $row->rift_shards - $item['price']]);
        });

        return $this->state($user->fresh());
    }

    private function grantGear(User $user): void
    {
        $pool = collect(Dungeons::GEAR)->filter(fn ($g) => empty($g['relic']) && $g['stage'] === 8)->keys()->all();
        app(PetGearService::class)->grant($user, $pool[array_rand($pool)], 'legendary');
    }

    private function restoreEnergy(User $user, ?int $petId): void
    {
        $updated = $petId ? DB::table('user_inventory')->where('id', $petId)->where('user_id', $user->id)->whereNotNull('pet_traits')
            ->update(['pet_energy' => PetBreedingService::ENERGY_MAX, 'pet_energy_at' => null]) : 0;

        if (! $updated) {
            throw new RuntimeException('Выбери своего питомца.');
        }
    }
}
