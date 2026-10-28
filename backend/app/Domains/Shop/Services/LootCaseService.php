<?php

namespace App\Domains\Shop\Services;

use App\Domains\Players\Support\PetTraits;
use App\Domains\Shop\Models\CaseOpening;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Support\LootCases;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Кейсы с косметикой: витрина, открытие, лента рулетки.
 *
 * Исход решает сервер; лента для анимации — декорация, в которой
 * выигрыш стоит на заранее известном месте.
 */
class LootCaseService
{
    /** Какие предметы могут выпасть: только косметика для профиля. */
    public const POOL_TYPES = [
        ShopItem::TYPE_AVATAR_FRAME,
        ShopItem::TYPE_PROFILE_EFFECT,
        ShopItem::TYPE_ACCENT_COLOR,
        ShopItem::TYPE_CARD_BACKGROUND,
        ShopItem::TYPE_BADGE,
        ShopItem::TYPE_SKIN_BACKDROP,
        ShopItem::TYPE_CARD_THEME,
        ShopItem::TYPE_PROFILE_LAYOUT,
        ShopItem::TYPE_NAME_EFFECT,
        ShopItem::TYPE_PROFILE_ENTRANCE,
        ShopItem::TYPE_CURSOR_TRAIL,
        ShopItem::TYPE_PROFILE_PET,
        ShopItem::TYPE_CHAT_BUBBLE,
        ShopItem::TYPE_PROFILE_TITLE,
        ShopItem::TYPE_NAME_ICON,
        ShopItem::TYPE_STICKER_PACK,
    ];

    public const REEL_LENGTH = 50;
    public const REEL_WIN_INDEX = 42;

    private ?Collection $pool = null;

    /** Витрина: кейсы, шансы, состояние бесплатного и гарантии. */
    public function catalog(?User $user): array
    {
        return [
            'balance' => $user ? (int) $user->apex_coins : 0,
            'cases' => collect(LootCases::all())->map(fn (array $case) => [
                'slug' => $case['slug'],
                'name' => $case['name'],
                'description' => $case['description'],
                'price' => $case['price'],
                'free_daily' => $case['free_daily'],
                'theme' => $case['theme'],
                'coins' => $case['coins'],
                'chances' => $case['chances'],
                'available' => $user ? $this->freeAvailable($user, $case) : false,
                'next_free_at' => $user && $case['free_daily'] && ! $this->freeAvailable($user, $case)
                    ? now()->addDay()->startOfDay()->toIso8601String()
                    : null,
                'pity_left' => $user && ! $case['free_daily'] ? $this->pityLeft($user, $case['slug']) : null,
                // Ключи — бесплатные открытия этого кейса (награды, подарки)
                'keys' => $user && ! $case['free_daily'] ? $this->keys($user, $case['slug']) : 0,
                // Витрина содержимого: по нескольку предметов каждой редкости
                'preview' => collect(LootCases::RARITIES)
                    ->filter(fn ($rarity) => ($case['chances'][$rarity] ?? 0) > 0)
                    ->flatMap(fn ($rarity) => $this->pool($case)->where('rarity', $rarity)->shuffle()->take(4))
                    ->map(fn (ShopItem $item) => $this->presentItem($item))
                    ->values(),
            ])->values(),
            'pool_size' => $this->pool()->count(),
            'history' => $user ? $this->history($user) : [],
        ];
    }

    public function open(User $user, string $slug): array
    {
        $case = LootCases::find($slug);

        if (! $case) {
            throw new RuntimeException('Такого кейса нет.');
        }

        if ($case['free_daily'] && ! $this->freeAvailable($user, $case)) {
            throw new RuntimeException('Бесплатный сундук уже открыт сегодня. Новый будет завтра.');
        }

        // Есть ключ — открываем платный кейс бесплатно
        $useKey = ! $case['free_daily'] && $this->keys($user, $slug) > 0;
        $price = $useKey ? 0 : $case['price'];

        if (! CoinService::canAfford($user, $price)) {
            throw new RuntimeException('Недостаточно ApexCoin. Не хватает ' . ($case['price'] - (int) $user->apex_coins) . '.');
        }

        $outcome = $this->roll($case['chances']);
        $pity = false;

        // Гарантия: в платном кейсе после серии без эпика выпадает эпик+
        if (! $case['free_daily'] && $this->pityLeft($user, $slug) <= 1 && ! in_array($outcome, ['epic', 'legendary'], true)) {
            $outcome = $this->roll(array_intersect_key($case['chances'], array_flip(['epic', 'legendary'])));
            $pity = true;
        }

        $item = LootCases::isItemOutcome($outcome) ? $this->pickItem($outcome, $case) : null;

        // Свой набор (сундук питомцев): нет такой редкости — любой из набора
        if (! $item && LootCases::isItemOutcome($outcome) && isset($case['pool'])) {
            $item = $this->pool($case)->shuffle()->first();
            $outcome = $item?->rarity ?? $outcome;
        }

        // Предметов такой редкости нет — отдаём монетами, а не пустотой
        if (LootCases::isItemOutcome($outcome) && ! $item) {
            $outcome = LootCases::COINS;
        }

        $result = DB::transaction(function () use ($user, $case, $outcome, $item, $useKey, $price) {
            if ($useKey) {
                DB::table('loot_case_keys')
                    ->where('user_id', $user->id)
                    ->where('case_slug', $case['slug'])
                    ->where('count', '>', 0)
                    ->decrement('count');
            } elseif ($price > 0) {
                CoinService::debit($user, $price, CoinTransaction::SOURCE_CASE, "Открытие: {$case['name']}");
            }

            $coins = 0;
            $duplicate = false;

            if ($item) {
                // Питомец — всегда новый уникальный экземпляр, а не повтор
                $isPet = $item->type === ShopItem::TYPE_PROFILE_PET;
                $owned = $isPet ? null : UserInventory::where('user_id', $user->id)->where('shop_item_id', $item->id)->first();

                if ($owned && ! $item->isStackable()) {
                    // Повтор: вместо второй копии — часть цены монетами
                    $duplicate = true;
                    $coins = max(5, (int) round($item->price * LootCases::DUPLICATE_SHARE));
                } else {
                    $inventory = $owned ?? new UserInventory(['user_id' => $user->id, 'shop_item_id' => $item->id]);
                    // Сундук питомцев: черты с удачей, редкие чаще
                    if ($isPet && isset($case['trait_luck'])) {
                        $inventory->pet_traits = PetTraits::roll(null, $case['trait_luck']);
                    }
                    $inventory->quantity = (int) ($inventory->quantity ?? 0) + 1;
                    $inventory->acquired_at = $inventory->acquired_at ?? now();
                    $inventory->save();
                    $minted = $inventory;
                }
            } elseif ($outcome === LootCases::COINS) {
                [$min, $max] = $case['coins'];
                // Круглые суммы смотрятся лучше на карточке выигрыша
                $coins = (int) (round(random_int($min, $max) / 5) * 5);
            }

            if ($coins > 0) {
                CoinService::credit(
                    $user,
                    $coins,
                    CoinTransaction::SOURCE_CASE,
                    $duplicate ? "Повтор из кейса: {$item->name}" : "Монеты из кейса: {$case['name']}",
                );
            }

            CaseOpening::create([
                'user_id' => $user->id,
                'case_slug' => $case['slug'],
                'shop_item_id' => $item?->id,
                'outcome' => $outcome,
                'price' => $price,
                'coins' => $coins,
                'duplicate' => $duplicate,
            ]);

            return [
                'coins' => $coins,
                'duplicate' => $duplicate,
                // Выпал питомец — показываем, какой именно экземпляр отчеканился
                'pet' => isset($minted) ? PetTraits::present($minted->pet_traits, $minted->pet_serial) : null,
            ];
        });

        $prize = [
            'outcome' => $outcome,
            'rarity' => $item?->rarity,
            'item' => $item ? [...$this->presentItem($item), 'pet' => $result['pet']] : null,
            'coins' => $result['coins'],
        ];

        return [
            'prize' => $prize,
            'duplicate' => $result['duplicate'],
            'pity' => $pity,
            'reel' => $this->reel($case, $prize),
            'win_index' => self::REEL_WIN_INDEX,
            'balance' => (int) $user->fresh()->apex_coins,
            'case' => [
                'slug' => $case['slug'],
                'available' => $this->freeAvailable($user, $case),
                'next_free_at' => $case['free_daily'] ? now()->addDay()->startOfDay()->toIso8601String() : null,
                'pity_left' => $case['free_daily'] ? null : $this->pityLeft($user, $case['slug']),
                'keys' => $case['free_daily'] ? 0 : $this->keys($user, $case['slug']),
            ],
            'used_key' => $useKey,
        ];
    }

    /** Сколько ключей (бесплатных открытий) этого кейса у игрока. */
    public function keys(User $user, string $slug): int
    {
        return (int) DB::table('loot_case_keys')
            ->where('user_id', $user->id)
            ->where('case_slug', $slug)
            ->value('count');
    }

    /** Выдать ключи кейса: награды недели, подарки персонала. */
    public static function grantKeys(User $user, string $slug, int $count = 1): void
    {
        if (! LootCases::find($slug) || $count < 1) {
            return;
        }

        DB::table('loot_case_keys')->upsert(
            [['user_id' => $user->id, 'case_slug' => $slug, 'count' => $count, 'created_at' => now(), 'updated_at' => now()]],
            ['user_id', 'case_slug'],
            ['count' => DB::raw('count + ' . $count), 'updated_at' => now()],
        );
    }

    public function dailyAvailable(User $user): bool
    {
        return $this->freeAvailable($user, LootCases::find('daily'));
    }

    /** Бесплатный кейс доступен, если сегодня его ещё не открывали. */
    private function freeAvailable(User $user, array $case): bool
    {
        if (! $case['free_daily']) {
            return true;
        }

        return ! CaseOpening::where('user_id', $user->id)
            ->where('case_slug', $case['slug'])
            ->where('created_at', '>=', now()->startOfDay())
            ->exists();
    }

    /** Сколько открытий осталось до гарантированного эпика. */
    private function pityLeft(User $user, string $slug): int
    {
        $recent = CaseOpening::where('user_id', $user->id)
            ->where('case_slug', $slug)
            ->latest('id')
            ->limit(LootCases::PITY_AFTER)
            ->pluck('outcome');

        $streak = 0;
        foreach ($recent as $outcome) {
            if (in_array($outcome, ['epic', 'legendary'], true)) {
                break;
            }
            $streak++;
        }

        return max(1, LootCases::PITY_AFTER - $streak);
    }

    private function roll(array $chances): string
    {
        $chances = array_filter($chances, fn ($weight) => $weight > 0);
        $total = array_sum($chances);
        $point = random_int(1, (int) round($total * 100)) / 100;

        foreach ($chances as $outcome => $weight) {
            $point -= $weight;
            if ($point <= 0) {
                return $outcome;
            }
        }

        return array_key_last($chances);
    }

    private function pickItem(string $rarity, ?array $case = null): ?ShopItem
    {
        return $this->pool($case)->where('rarity', $rarity)->shuffle()->first();
    }

    /** Что может выпасть: общий набор косметики или свой у кейса (pool). */
    private function pool(?array $case = null): Collection
    {
        $all = $this->pool ??= ShopItem::active()
            ->whereIn('type', self::POOL_TYPES)
            ->get();

        return isset($case['pool']) ? $all->whereIn('type', $case['pool'])->values() : $all;
    }

    /**
     * Лента рулетки: случайные исходы по тем же шансам, выигрыш —
     * на месте REEL_WIN_INDEX. Перед выигрышем иногда ставим «почти
     * легендарку», чтобы прокрутка была живой.
     */
    private function reel(array $case, array $prize): array
    {
        $reel = [];

        for ($i = 0; $i < self::REEL_LENGTH; $i++) {
            if ($i === self::REEL_WIN_INDEX) {
                $reel[] = $prize;
                continue;
            }

            $outcome = $this->roll($case['chances']);
            $item = LootCases::isItemOutcome($outcome) ? ($this->pickItem($outcome, $case) ?? (isset($case['pool']) ? $this->pool($case)->shuffle()->first() : null)) : null;

            $reel[] = [
                'outcome' => $item ? $outcome : ($outcome === LootCases::NOTHING ? LootCases::NOTHING : LootCases::COINS),
                'rarity' => $item?->rarity,
                'item' => $item ? $this->presentItem($item) : null,
                'coins' => $item || $outcome === LootCases::NOTHING ? 0 : (int) (round(random_int(...$case['coins']) / 5) * 5),
            ];
        }

        return $reel;
    }

    private function history(User $user): Collection
    {
        return CaseOpening::where('user_id', $user->id)
            ->with('item')
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn (CaseOpening $o) => [
                'id' => $o->id,
                'case' => LootCases::find($o->case_slug)['name'] ?? $o->case_slug,
                'outcome' => $o->outcome,
                'item' => $o->item ? ['name' => $o->item->name, 'rarity' => $o->item->rarity, 'type' => $o->item->type] : null,
                'coins' => $o->coins,
                'duplicate' => $o->duplicate,
                'created_at' => $o->created_at?->toIso8601String(),
            ]);
    }

    private function presentItem(ShopItem $item): array
    {
        return ShopService::presentItem($item);
    }
}
