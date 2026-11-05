<?php

namespace App\Domains\Minecraft\Services;

use App\Domains\Bridge\Services\BridgeService;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Данные игрока для клиентского мода APEX.
 *
 * Отдаём только то, что и так видно на публичном профиле: без почты,
 * UUID, настроек и приватного. Цвета и подписи собираются здесь, чтобы мод
 * не дублировал палитру сайта (frontend: useTier.js, playerStyling.js).
 */
class ModPlayerService
{
    /** Палитра тиров — копия TIER_COLORS из frontend/composables/players/useTier.js. */
    public const TIER_COLORS = [
        'S+' => '#fbbf24',
        'S' => '#facc15',
        'A' => '#f97316',
        'B' => '#8b5cf6',
        'C' => '#06b6d4',
        'D' => '#22c55e',
        'E' => '#6b7280',
    ];

    public const DEFAULT_COLOR = '#a78bfa';

    public const CACHE_TTL = 60;

    private const CYR_TO_LAT = [
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'E', 'Ж' => 'Z', 'З' => 'Z',
        'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R',
        'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C', 'Ч' => 'C', 'Ш' => 'S', 'Щ' => 'S',
        'Ы' => 'Y', 'Э' => 'E', 'Ю' => 'Y', 'Я' => 'Y',
    ];

    private const PVP_LABELS = [
        'block_placing' => 'Блоки', 'rotka' => 'Ротка', 'movement' => 'Движение', 'aim' => 'Аим', 'game_sense' => 'Чутьё',
    ];

    public function __construct(private readonly BridgeService $bridge)
    {
    }

    /** Полная карточка по нику из Minecraft или нику на сайте. */
    public function player(string $query): ?array
    {
        $key = 'mod:player:' . mb_strtolower($query);

        return Cache::remember($key, self::CACHE_TTL, function () use ($query) {
            $user = $this->find($query);

            return $user ? $this->full($user) : null;
        });
    }

    /**
     * Короткие данные для списка ников вокруг: только по нику Minecraft.
     *
     * @param  array<int, string>  $nicks
     */
    public function tiers(array $nicks): array
    {
        $result = [];
        $missing = [];

        foreach ($nicks as $nick) {
            $cached = Cache::get('mod:tier:' . mb_strtolower($nick));
            if ($cached === null) {
                $missing[] = $nick;
            } elseif ($cached !== false) {
                $result[] = $cached;
            }
        }

        if ($missing) {
            $lower = array_map('mb_strtolower', $missing);
            $users = User::query()
                ->where('is_banned', false)
                ->whereIn(\DB::raw('LOWER(minecraft_username)'), $lower)
                ->get();

            $found = [];
            foreach ($users as $user) {
                $short = $this->short($user);
                $found[mb_strtolower((string) $user->minecraft_username)] = true;
                Cache::put('mod:tier:' . mb_strtolower((string) $user->minecraft_username), $short, self::CACHE_TTL);
                $result[] = $short;
            }

            // Отрицательный ответ тоже кэшируем, чтобы не долбить базу никами без аккаунта
            foreach ($lower as $name) {
                if (! isset($found[$name])) {
                    Cache::put('mod:tier:' . $name, false, self::CACHE_TTL);
                }
            }
        }

        return $result;
    }

    private function find(string $query): ?User
    {
        $lower = mb_strtolower($query);

        return User::query()
            ->where('is_banned', false)
            ->whereRaw('LOWER(minecraft_username) = ?', [$lower])
            ->first()
            ?? User::query()
                ->where('is_banned', false)
                ->whereRaw('LOWER(username) = ?', [$lower])
                ->first();
    }

    /** Подпись, цвет и сокращение рядом с ником (как rankLabel/rankColor/rankShort на сайте). */
    public function badge(User $user): array
    {
        $rank = $user->display_rank;
        $tier = $user->publicTier();

        if (($rank['type'] ?? null) === 'bridge' && ! empty($rank['label'])) {
            return [
                'type' => 'bridge',
                'label' => $rank['label'],
                'short' => $this->shortLabel($rank['label']),
                'color' => $rank['color'] ?: self::DEFAULT_COLOR,
            ];
        }

        return [
            'type' => 'tier',
            'label' => $tier,
            'short' => $tier,
            'color' => $tier ? (self::TIER_COLORS[$tier] ?? self::DEFAULT_COLOR) : '#6b7280',
        ];
    }

    private function short(User $user): array
    {
        return [
            'nick' => $user->minecraft_username,
            'badge' => $this->badge($user),
        ];
    }

    private function full(User $user): array
    {
        $data = [
            'username' => $user->username,
            'minecraft_username' => $user->minecraft_username,
            'tier' => $user->publicTier(),
            'tier_score' => (float) $user->tier_score,
            'tier_color' => $user->publicTier() ? (self::TIER_COLORS[$user->publicTier()] ?? self::DEFAULT_COLOR) : null,
            'badge' => $this->badge($user),
            'profile_url' => rtrim((string) config('app.frontend_url'), '/') . '/players/' . $user->id,
            'visible' => ($user->profile_visibility ?? 'public') === 'public',
        ];

        // Закрытый профиль: наружу только то, что видно рядом с ником
        if (! $data['visible']) {
            return $data + ['aspects' => null, 'bridge' => null, 'clan' => null, 'friends_count' => null, 'achievements_count' => null];
        }

        return $data + [
            'aspects' => $this->aspects($user),
            'bridge' => $this->bridgeData($user),
            'clan' => $this->clan($user),
            'friends_count' => $user->friends()->count(),
            'achievements_count' => $user->achievements()->count(),
        ];
    }

    private function aspects(User $user): array
    {
        $out = [];

        foreach (['pvp' => $user->aspectPvp, 'bedwars' => $user->aspectBedwars] as $mode => $row) {
            if (! $row) {
                continue;
            }

            $items = [];
            foreach ($row->getAttributes() as $column => $value) {
                if (in_array($column, ['id', 'user_id', 'created_at', 'updated_at'], true)) {
                    continue;
                }
                $items[] = [
                    'key' => $column,
                    'label' => $mode === 'pvp' ? (self::PVP_LABELS[$column] ?? $column) : $column,
                    'value' => (float) $value,
                ];
            }

            $out[] = ['mode' => $mode, 'percent' => $row->percent(), 'items' => $items];
        }

        return $out;
    }

    private function bridgeData(User $user): ?array
    {
        $rows = $this->bridge->confirmedForUser($user);

        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'confirmed_count' => $rows->count(),
            'aspects_total' => (int) $rows->sum(fn ($row) => $row->total),
            'techniques' => $rows->map(fn ($row) => [
                'label' => $row->technique?->label,
                'stability' => (int) $row->stability,
                'speed' => (int) $row->speed,
                'difficulty' => (int) $row->difficulty,
            ])->values()->all(),
        ];
    }

    private function clan(User $user): ?array
    {
        $member = $user->clanMember;
        $clan = $member?->clan;

        if (! $clan || $clan->is_banned) {
            return null;
        }

        return [
            'name' => $clan->name,
            'tag' => $clan->tag,
            'role' => $member->role,
            'title' => $member->title,
        ];
    }

    /** Порт shortRankLabel из frontend/utils/playerStyling.js: BRIDGE ПРОФИ -> B.P. */
    public function shortLabel(string $label): string
    {
        $words = array_values(array_filter(preg_split('/[\s\-_]+/u', trim($label)) ?: []));

        if (! $words) {
            return '';
        }

        if (count($words) === 1) {
            $word = mb_strtoupper($words[0]);

            return mb_strlen($word) <= 6 ? $word : mb_substr($word, 0, 3);
        }

        return implode('.', array_map(function (string $word) {
            $first = mb_strtoupper(mb_substr($word, 0, 1));

            return self::CYR_TO_LAT[$first] ?? $first;
        }, $words));
    }
}
