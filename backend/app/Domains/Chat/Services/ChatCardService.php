<?php

namespace App\Domains\Chat\Services;

use App\Domains\Chat\Models\Message;
use App\Domains\Clan\Models\Clan;
use App\Domains\Players\Services\PetService;
use App\Domains\Players\Support\PetTraits;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Services\PetAuctionService;
use App\Domains\Shop\Services\ShopService;
use App\Domains\Users\Models\User;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Карточки «поделиться»: ссылка на игрока, питомца, клан или лот аукциона
 * в тексте сообщения разворачивается в живую карточку.
 *
 * Карточка собирается при каждой выдаче истории, а не хранится: цена лота,
 * тир игрока и состав клана всегда свежие. Ссылки всей страницы истории
 * разбираются пачкой — по запросу на вид, а не на сообщение.
 */
class ChatCardService
{
    /** Больше карточек на одно сообщение не рисуем. */
    public const MAX_PER_MESSAGE = 3;

    /**
     * Кладёт карточки в сообщения (связь linkCards).
     *
     * @param  Collection<int, Message>  $messages
     */
    public function attach(Collection $messages, ?User $viewer): void
    {
        $refsByMessage = [];
        $wanted = ['player' => [], 'pet' => [], 'clan' => [], 'lot' => []];

        foreach ($messages as $message) {
            $refs = $message->kind === Message::KIND_TEXT ? $this->parse((string) $message->body) : [];
            $refsByMessage[$message->id] = $refs;

            foreach ($refs as $ref) {
                $wanted[$ref['type']][$ref['id']] = true;

                if ($ref['type'] === 'pet') {
                    $wanted['player'][$ref['owner_id']] = true;
                }
            }
        }

        $resolved = $this->resolve(array_map('array_keys', $wanted), $viewer);

        foreach ($messages as $message) {
            $cards = [];

            foreach ($refsByMessage[$message->id] ?? [] as $ref) {
                $card = $resolved[$ref['type']][$ref['id']] ?? null;

                // Питомец по ссылке должен принадлежать игроку из той же ссылки
                if ($card && $ref['type'] === 'pet' && $card['owner']['id'] !== $ref['owner_id']) {
                    $card = null;
                }

                if ($card) {
                    $cards[] = ['url' => $ref['url'], ...$card];
                }
            }

            $message->setRelation('linkCards', collect($cards));
        }
    }

    /**
     * Ссылки на страницы сайта в тексте.
     *
     * @return array<int, array{type: string, id: int, url: string, owner_id?: int}>
     */
    public function parse(string $body): array
    {
        if ($body === '' || ! preg_match_all('~(?:https?://([a-z0-9.\-]+)(?::\d+)?)?(/(?:players|user|clans|auction)[^\s<>"\']*)~iu', $body, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $hosts = $this->allowedHosts();
        $refs = [];
        $seen = [];

        foreach ($matches as $match) {
            $host = strtolower($match[1] ?? '');

            // Чужой сайт с похожим путём — не наша ссылка
            if ($host !== '' && ! in_array($host, $hosts, true)) {
                continue;
            }

            // Путь без домена считаем ссылкой, только если он стоит отдельным словом
            if ($host === '' && ! preg_match('~(?:^|\s)' . preg_quote($match[2], '~') . '~u', $body)) {
                continue;
            }

            $ref = $this->refFromPath(rtrim($match[2], '.,!?)'));

            if (! $ref) {
                continue;
            }

            $key = $ref['type'] . ':' . $ref['id'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $refs[] = $ref + ['url' => $this->pathOf($ref)];

            if (count($refs) >= self::MAX_PER_MESSAGE) {
                break;
            }
        }

        return $refs;
    }

    private function refFromPath(string $path): ?array
    {
        $parts = parse_url($path);
        $route = rtrim($parts['path'] ?? '', '/');
        parse_str($parts['query'] ?? '', $query);

        if (preg_match('~^/(?:players|user)/(\d+)$~', $route, $m)) {
            $userId = (int) $m[1];
            $pet = isset($query['pet']) ? (int) $query['pet'] : 0;

            return $pet > 0
                ? ['type' => 'pet', 'id' => $pet, 'owner_id' => $userId]
                : ['type' => 'player', 'id' => $userId];
        }

        if (preg_match('~^/clans/(\d+)$~', $route, $m)) {
            return ['type' => 'clan', 'id' => (int) $m[1]];
        }

        if ($route === '/auction' && isset($query['lot']) && (int) $query['lot'] > 0) {
            return ['type' => 'lot', 'id' => (int) $query['lot']];
        }

        return null;
    }

    /** Ссылка карточки — всегда относительная: страница откроет её роутером. */
    private function pathOf(array $ref): string
    {
        return match ($ref['type']) {
            'player' => "/players/{$ref['id']}",
            'pet' => "/players/{$ref['owner_id']}?pet={$ref['id']}",
            'clan' => "/clans/{$ref['id']}",
            'lot' => "/auction?lot={$ref['id']}",
        };
    }

    /** @return array<int, string> */
    private function allowedHosts(): array
    {
        return collect([config('app.url'), config('app.frontend_url'), 'https://mc-apex.ru', 'https://www.mc-apex.ru'])
            ->map(fn ($url) => parse_url((string) $url))
            ->filter(fn ($p) => ! empty($p['host']))
            // Порт не сверяем: у сайта и API в разработке они разные
            ->map(fn ($p) => strtolower($p['host']))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array{player: int[], pet: int[], clan: int[], lot: int[]}  $ids
     */
    private function resolve(array $ids, ?User $viewer): array
    {
        $result = ['player' => [], 'pet' => [], 'clan' => [], 'lot' => []];

        if ($ids['player']) {
            User::whereIn('id', $ids['player'])
                ->get(['id', 'username', 'avatar', 'tier', 'bridge_primary', 'bridge_rank_id', 'profile_mode', 'name_effect', 'profile_title', 'name_icon', 'is_verified'])
                ->each(function (User $user) use (&$result) {
                    $result['player'][$user->id] = [
                        'type' => 'player',
                        'id' => $user->id,
                        'username' => $user->username,
                        'avatar_url' => $user->avatar_url,
                        'tier' => $user->publicTier(),
                        'display_rank' => $user->display_rank,
                        'is_verified' => (bool) $user->is_verified,
                        'name_effect' => $user->name_effect,
                        'name_icon' => $user->name_icon,
                        'profile_title' => $user->profile_title,
                    ];
                });
        }

        if ($ids['pet']) {
            $rows = UserInventory::with('item')
                ->whereIn('id', $ids['pet'])
                ->whereHas('item', fn ($q) => $q->where('type', ShopItem::TYPE_PROFILE_PET))
                ->get();

            $levels = PetService::levels($rows->pluck('id')->all());

            foreach ($rows as $row) {
                $owner = $result['player'][$row->user_id] ?? null;

                if (! $owner) {
                    continue;
                }

                $result['pet'][$row->id] = [
                    'type' => 'pet',
                    'id' => $row->id,
                    'owner' => ['id' => $owner['id'], 'username' => $owner['username']],
                    // Тот же вид, что у лота аукциона: страница рисует его ShopItemPreview
                    'pet' => [
                        ...ShopService::presentItem($row->item),
                        'pet' => [
                            ...(PetTraits::present($row->pet_traits, $row->pet_serial) ?? []),
                            'name' => $row->pet_name,
                            'level' => $levels[$row->id] ?? 1,
                        ],
                    ],
                ];
            }
        }

        if ($ids['clan']) {
            Clan::whereIn('id', $ids['clan'])
                ->withCount('members')
                ->get()
                ->each(function (Clan $clan) use (&$result) {
                    $result['clan'][$clan->id] = [
                        'type' => 'clan',
                        'id' => $clan->id,
                        'name' => $clan->name,
                        'tag' => $clan->tag,
                        'banner_color' => $clan->banner_color,
                        'avatar_url' => $clan->avatar_url,
                        'members_count' => (int) $clan->members_count,
                    ];
                });
        }

        foreach ($ids['lot'] as $lotId) {
            try {
                $lot = app(PetAuctionService::class)->show($lotId, $viewer);
            } catch (Throwable) {
                continue; // лот удалён — ссылка останется просто текстом
            }

            $result['lot'][$lotId] = [
                'type' => 'lot',
                'id' => $lot['id'],
                'status' => $lot['status'],
                'pet' => $lot['pet'],
                'seller' => $lot['seller'],
                'price' => $lot['current_price'] ?? $lot['start_price'],
                'bids_count' => $lot['bids_count'],
                'ends_at' => $lot['ends_at'],
            ];
        }

        return $result;
    }
}
