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
        // Статусы
        'vip_7' => ['group' => 'Статусы', 'name' => 'VIP на 7 дней', 'about' => 'Префикс VIP и привилегии сервера на неделю.', 'price' => 100,
            'commands' => ['lp user {player} parent addtemp vip 7d']],
        'vip_30' => ['group' => 'Статусы', 'name' => 'VIP на 30 дней', 'about' => 'Префикс VIP и привилегии на сервере на месяц.', 'price' => 300,
            'commands' => ['lp user {player} parent addtemp vip 30d']],
        'vip_90' => ['group' => 'Статусы', 'name' => 'VIP на 90 дней', 'about' => 'Три месяца VIP: выгоднее месячной подписки.', 'price' => 750,
            'commands' => ['lp user {player} parent addtemp vip 90d']],

        // Бой: дуэли и тир-тесты
        'queue_priority' => ['group' => 'Бой', 'name' => 'Приоритет очереди дуэлей', 'about' => 'Вас ставят в дуэль раньше других. 30 дней.', 'price' => 140,
            'commands' => ['lp user {player} permission settemp apex.duels.priority true 30d']],
        'map_vote' => ['group' => 'Бой', 'name' => 'Голос за арену', 'about' => 'Можно выбирать карту дуэли голосованием. 30 дней.', 'price' => 100,
            'commands' => ['lp user {player} permission settemp apex.duels.mapvote true 30d']],
        'kit_slot' => ['group' => 'Бой', 'name' => 'Дополнительный слот набора', 'about' => 'Ещё одна сохранённая раскладка набора в дуэлях. Навсегда.', 'price' => 180, 'once' => true,
            'commands' => ['lp user {player} permission set apex.duels.kitslot.extra true']],
        'kit_layout' => ['group' => 'Бой', 'name' => 'Своя раскладка инвентаря', 'about' => 'Расставляйте предметы набора по своим слотам. Навсегда.', 'price' => 120, 'once' => true,
            'commands' => ['lp user {player} permission set apex.duels.layout true']],

        // Эффекты убийств и победы
        'kill_flame' => ['group' => 'Эффекты', 'name' => 'Огненный эффект убийства', 'about' => 'Побеждённый вспыхивает пламенем. Навсегда.', 'price' => 160, 'once' => true,
            'commands' => ['lp user {player} permission set apex.cosmetic.kill.flame true']],
        'kill_lightning' => ['group' => 'Эффекты', 'name' => 'Молния при убийстве', 'about' => 'Удар молнии в месте победы. Навсегда.', 'price' => 220, 'once' => true,
            'commands' => ['lp user {player} permission set apex.cosmetic.kill.lightning true']],
        'arrow_trail' => ['group' => 'Эффекты', 'name' => 'След стрел', 'about' => 'Ваши стрелы оставляют светящийся след. Навсегда.', 'price' => 140, 'once' => true,
            'commands' => ['lp user {player} permission set apex.cosmetic.trail.arrow true']],
        'kill_message' => ['group' => 'Эффекты', 'name' => 'Свой текст убийства', 'about' => 'Своя фраза в чате, когда вы побеждаете. Навсегда.', 'price' => 120, 'once' => true,
            'commands' => ['lp user {player} permission set apex.cosmetic.killmsg true']],
        'win_dance' => ['group' => 'Эффекты', 'name' => 'Танец победителя', 'about' => 'Анимация после победы в дуэли. Навсегда.', 'price' => 100, 'once' => true,
            'commands' => ['lp user {player} permission set apex.cosmetic.win.dance true']],

        // Чат и префиксы
        'star_prefix' => ['group' => 'Чат', 'name' => 'Звёздный префикс', 'about' => 'Золотая звезда перед ником в чате сервера. Навсегда.', 'price' => 150, 'once' => true,
            'commands' => ['lp user {player} permission set prefix.star true']],
        'flame_prefix' => ['group' => 'Чат', 'name' => 'Огненный префикс', 'about' => 'Пламя перед ником в чате сервера. Навсегда.', 'price' => 150, 'once' => true,
            'commands' => ['lp user {player} permission set prefix.flame true']],
        'crown_prefix' => ['group' => 'Чат', 'name' => 'Корона перед ником', 'about' => 'Золотая корона в чате: редкий знак. Навсегда.', 'price' => 250, 'once' => true,
            'commands' => ['lp user {player} permission set prefix.crown true']],
        'chat_color' => ['group' => 'Чат', 'name' => 'Цветной чат', 'about' => 'Цветные сообщения в чате сервера. Навсегда.', 'price' => 200, 'once' => true,
            'commands' => ['lp user {player} permission set chat.color true']],
        'name_color' => ['group' => 'Чат', 'name' => 'Цвет ника', 'about' => 'Выбор цвета своего ника в чате и над головой. Навсегда.', 'price' => 180, 'once' => true,
            'commands' => ['lp user {player} permission set apex.chat.namecolor true']],

        // Лобби
        'fly_7' => ['group' => 'Лобби', 'name' => 'Полёт в лобби на 7 дней', 'about' => 'Команда /fly в лобби сервера на неделю.', 'price' => 120,
            'commands' => ['lp user {player} permission settemp essentials.fly true 7d']],
        'fly_30' => ['group' => 'Лобби', 'name' => 'Полёт в лобби на 30 дней', 'about' => 'Команда /fly в лобби сервера на месяц.', 'price' => 400,
            'commands' => ['lp user {player} permission settemp essentials.fly true 30d']],
    ];

    /** Вещи за Осколки Разлома. */
    public const SHARDS = [
        'legend_gear' => ['name' => 'Ящик легендарной экипировки', 'about' => 'Случайный легендарный предмет самой высокой ступени.', 'price' => 30],
        'throne_core' => ['name' => 'Печать владыки', 'about' => 'Ядро босса Чертога владыки для ковки.', 'price' => 40],
        'vigor' => ['name' => 'Эликсир бодрости', 'about' => 'Полностью восстанавливает энергию выбранного питомца.', 'price' => 15, 'needs_pet' => true],
        'respec_scroll' => ['name' => 'Свиток забвения', 'about' => 'Бесплатно сбрасывает все таланты выбранного питомца.', 'price' => 15, 'needs_pet' => true],
        'xp_tome' => ['name' => 'Фолиант опыта', 'about' => '+4 000 опыта выбранному питомцу.', 'price' => 20, 'needs_pet' => true],
        'epic_crate' => ['name' => 'Ящик эпической экипировки', 'about' => 'Случайная эпическая вещь 5–7 ступени.', 'price' => 18],
        'material_set' => ['name' => 'Набор материалов', 'about' => '12 материалов 8 ступени для верстака.', 'price' => 12],
        'pantheon_core' => ['name' => 'Лик забытого бога', 'about' => 'Ядро босса Пантеона для ковки.', 'price' => 40],
        'relic_chest' => ['name' => 'Сундук реликвий', 'about' => 'Случайная реликвия с двумя свойствами, любой ступени.', 'price' => 90],
    ];


    public function state(User $user): array
    {
        $recent = DB::table('privilege_orders')->where('user_id', $user->id)->where('created_at', '>=', now()->subDay())->pluck('item_key')->all();
        $owned = DB::table('privilege_orders')->where('user_id', $user->id)->pluck('item_key')->unique()->all();

        return [
            'rubles' => RubleService::balance($user),
            'shards' => (int) DB::table('users')->where('id', $user->id)->value('rift_shards'),
            'linked' => (bool) $user->minecraft_username,
            'nick' => $user->minecraft_username,
            'privileges' => collect(self::PRIVILEGES)->map(fn ($p, $key) => [
                'key' => $key, 'group' => $p['group'], 'name' => $p['name'], 'about' => $p['about'], 'price' => $p['price'],
                'permanent' => ! empty($p['once']),
                // Временные можно продлевать раз в сутки, постоянные покупаются один раз
                'cooldown' => in_array($key, $recent, true),
                'owned' => ! empty($p['once']) && in_array($key, $owned, true),
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

            // Постоянное покупается один раз, иначе Рубли ушли бы зря
            if (! empty($item['once']) && DB::table('privilege_orders')->where('user_id', $user->id)->where('item_key', $key)->exists()) {
                throw new RuntimeException('Эта привилегия у вас уже есть, и она постоянная.');
            }

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
                'throne_core' => $this->addMaterial($user, 'core_throne', 1),
                'pantheon_core' => $this->addMaterial($user, 'core_pantheon', 1),
                'material_set' => $this->addMaterial($user, Dungeons::materialForStage(8), 12),
                'vigor' => $this->restoreEnergy($user, $petId),
                'respec_scroll' => $this->forgetTalents($user, $petId),
                'xp_tome' => $this->addXp($user, $petId, 4000),
                'epic_crate' => $this->grantRandom($user, 5, 7, 'epic'),
                'relic_chest' => $this->grantRelic($user),
            };

            DB::table('users')->where('id', $user->id)->update(['rift_shards' => (int) $row->rift_shards - $item['price']]);
        });

        return $this->state($user->fresh());
    }

    private function addMaterial(User $user, ?string $material, int $quantity): void
    {
        if (! $material) {
            throw new RuntimeException('Такого материала нет.');
        }

        DB::table('dungeon_materials')->upsert(
            ['user_id' => $user->id, 'material' => $material, 'quantity' => $quantity, 'created_at' => now(), 'updated_at' => now()],
            ['user_id', 'material'],
            ['quantity' => DB::raw('quantity + ' . $quantity)],
        );
    }

    /** Случайная вещь ступеней от $from до $to; редкость задана или выпадает как в подземелье. */
    private function grantRandom(User $user, int $from, int $to, ?string $rarity): void
    {
        $pool = collect(Dungeons::GEAR)->filter(fn ($g) => empty($g['relic']) && $g['stage'] >= $from && $g['stage'] <= $to)->keys()->all();
        $slug = $pool[array_rand($pool)];

        app(PetGearService::class)->grant($user, $slug, Dungeons::clampRarity($slug, $rarity ?? Dungeons::rollRarity((int) Dungeons::GEAR[$slug]['stage'], false)));
    }

    private function grantRelic(User $user): void
    {
        $pool = collect(Dungeons::GEAR)->filter(fn ($g) => ! empty($g['relic']))->keys()->all();

        app(PetGearService::class)->grant($user, $pool[array_rand($pool)], 'legendary');
    }

    private function ownPet(User $user, ?int $petId): int
    {
        $exists = $petId && DB::table('user_inventory')->where('id', $petId)->where('user_id', $user->id)->whereNotNull('pet_traits')->exists();

        if (! $exists) {
            throw new RuntimeException('Выбери своего питомца.');
        }

        return $petId;
    }

    private function addXp(User $user, ?int $petId, int $xp): void
    {
        DB::table('user_inventory')->where('id', $this->ownPet($user, $petId))->increment('pet_xp', $xp);
    }

    private function forgetTalents(User $user, ?int $petId): void
    {
        $id = $this->ownPet($user, $petId);

        if (! DB::table('pet_talents')->where('inventory_id', $id)->delete()) {
            throw new RuntimeException('У питомца нет изученных талантов.');
        }
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
