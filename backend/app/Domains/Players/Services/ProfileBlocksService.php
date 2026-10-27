<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Models\GuestbookEntry;
use App\Domains\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Конструктор профиля.
 *
 * Раскладка — список блоков по порядку: тип, ширина, оформление и
 * настройки. Пишет её сам игрок, поэтому всё приходящее приводим к
 * известной форме: лишние поля отбрасываем, длины режем, ссылки
 * пропускаем только http(s), видео — только с YouTube.
 *
 * У каждого блока ещё есть оформление (style): цвет, выравнивание, высота,
 * узор, скругление, шрифт, свой заголовок. У страницы целиком — отступы,
 * вид шапки и анимация появления (users.profile_page).
 */
class ProfileBlocksService
{
    public const MAX_BLOCKS = 24;

    public const TYPES = [
        // Готовые части профиля: каждую можно поставить один раз
        'stats', 'socials', 'games', 'aspects', 'achievements', 'friends', 'recommendations',
        'showcase', 'clan', 'rank', 'rating', 'seasons',
        // Свои блоки, со своими настройками
        'text', 'quote', 'countdown', 'video', 'guestbook', 'sticker', 'divider', 'spacer',
        'facts', 'bars', 'timeline', 'players', 'poll', 'goals', 'counter',
        'setup', 'links', 'music', 'faq', 'marquee', 'schedule', 'banner', 'progress',
    ];

    /** Блоки, которые бывают в профиле только в одном экземпляре. */
    private const SINGLE = [
        'stats', 'socials', 'games', 'aspects', 'achievements', 'friends', 'recommendations',
        'showcase', 'clan', 'rank', 'rating', 'seasons', 'guestbook',
    ];

    public const WIDTHS = ['quarter', 'third', 'half', 'two_thirds', 'three_quarters', 'full'];

    public const TONES = ['default', 'accent', 'glass', 'outline', 'transparent', 'gradient', 'neon', 'paper'];

    /** Оформление блока: поле => допустимые значения (первое — по умолчанию). */
    private const STYLE = [
        'color' => ['auto', 'violet', 'blue', 'cyan', 'green', 'lime', 'yellow', 'orange', 'red', 'pink', 'white'],
        'align' => ['left', 'center', 'right'],
        'height' => ['auto', 'compact', 'tall', 'huge'],
        'pattern' => ['none', 'dots', 'grid', 'stripes', 'aurora', 'noise'],
        'radius' => ['inherit', 'sharp', 'round', 'pill'],
        'hover' => ['none', 'lift', 'glow', 'tilt'],
        'font' => ['default', 'display', 'mono', 'serif'],
        // Анимированная рамка блока
        'border' => ['none', 'shine', 'pulse', 'rainbow', 'glow'],
        'text' => ['auto', 'light', 'dark', 'accent'],
        // Внутренние отступы, размер текста, живая анимация, появление при прокрутке
        'pad' => ['normal', 'none', 's', 'l', 'xl'],
        'size' => ['m', 's', 'l', 'xl'],
        'anim' => ['none', 'float', 'sway', 'pulse', 'bounce'],
        'reveal' => ['none', 'up', 'left', 'right', 'zoom', 'flip'],
    ];

    /**
     * Настройки всей страницы. Того, что продаётся в магазине (акцент,
     * эффекты ника и профиля), здесь нет: студия не раздаёт платное.
     */
    private const PAGE = [
        // masonry — блоки встают в ближайшее свободное место, без дыр
        'flow' => ['masonry', 'rows'],
        'gap' => ['normal', 'tight', 'loose'],
        'header' => ['full', 'compact'],
        'appear' => ['rise', 'none', 'fade', 'zoom', 'cascade'],
        'radius' => ['round', 'sharp', 'soft'],
        'backdrop' => ['none', 'dim', 'blur'],
        // Студия: фон всей страницы, шрифт, тени, ширина, заголовки, плотность
        'bg' => ['none', 'gradient', 'mesh', 'aurora', 'stars', 'grid', 'spotlight', 'noise'],
        'font' => ['default', 'display', 'mono', 'serif', 'rounded'],
        'shadow' => ['soft', 'none', 'deep', 'neon'],
        'width' => ['normal', 'wide', 'full'],
        'titles' => ['default', 'caps', 'line', 'pill', 'hidden'],
        'density' => ['normal', 'compact', 'airy'],
        // Шапка профиля
        'hdr_layout' => ['classic', 'center', 'mirror', 'banner', 'minimal'],
        'hdr_height' => ['normal', 'short', 'tall'],
        'hdr_bg' => ['theme', 'accent', 'gradient', 'glass', 'none'],
        'hdr_avatar' => ['circle', 'rounded', 'square', 'hex'],
        'hdr_avatar_size' => ['m', 's', 'l'],
    ];

    /** Кем тиммейт приходится игроку: подпись и цвет карточки. */
    private const TEAMMATE_ROLES = ['duo', 'squad', 'coach', 'student', 'rival', 'bestie'];

    /** Сетап: что за строка (иконка и подпись на странице). */
    private const SETUP_KINDS = ['mouse', 'keyboard', 'headset', 'monitor', 'mousepad', 'pc', 'dpi', 'sens', 'fps', 'other'];

    /** Иконки кнопок-ссылок. */
    private const LINK_ICONS = ['link', 'discord', 'youtube', 'twitch', 'telegram', 'tiktok', 'vk', 'star', 'heart', 'game'];

    /** Откуда можно поставить «Сейчас слушаю». */
    private const MUSIC_HOSTS = [
        'open.spotify.com', 'music.yandex.ru', 'music.yandex.com', 'youtube.com', 'www.youtube.com',
        'music.youtube.com', 'youtu.be', 'soundcloud.com', 'vk.com',
    ];

    private const WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    private const STICKER_SPRITES = [
        'creeper', 'bee', 'axolotl', 'tnt', 'diamond', 'heart', 'skull', 'warden', 'totem', 'cat',
        'paw', 'crown', 'flame', 'dog', 'fox', 'slime', 'ghost', 'dragon', 'robot', 'penguin',
    ];

    /**
     * Сохранить раскладку и настройки страницы. blocks = null — вернуть
     * профиль по умолчанию (настройки страницы тогда тоже сбрасываются).
     *
     * @param  array<int, mixed>|null  $blocks
     */
    public function save(User $user, ?array $blocks, ?array $page = null): ?array
    {
        $clean = $blocks === null ? null : $this->normalize($blocks);

        $user->forceFill([
            'profile_blocks' => $clean,
            'profile_page' => $clean === null ? null : $this->page($page ?? []),
        ])->save();

        return $clean;
    }

    public function page(array $page): array
    {
        return $this->pick(self::PAGE, $page) + [
            'bg_from' => $this->hex($page['bg_from'] ?? null) ?? '#2e1065',
            'bg_to' => $this->hex($page['bg_to'] ?? null) ?? '#0b0912',
            'glass' => $this->clampInt($page['glass'] ?? 100, 20, 100),
            'blur' => $this->clampInt($page['blur'] ?? 0, 0, 24),
            'hdr_items' => $this->headerItems($page['hdr_items'] ?? []),
        ];
    }

    /** Части шапки: порядок, видимость и оформление каждой. */
    private const HEADER_ITEMS = ['name', 'role', 'stats', 'progress'];

    private const HEADER_ITEM_STYLE = [
        'size' => ['m', 's', 'l', 'xl'],
        'font' => ['default', 'display', 'mono', 'serif', 'rounded'],
        'weight' => ['default', 'light', 'bold', 'black'],
        'spacing' => ['normal', 'tight', 'wide'],
        'case' => ['none', 'upper', 'lower'],
    ];

    /**
     * Все части по одному разу: сначала в присланном порядке, недостающие —
     * в конце по умолчанию.
     */
    private function headerItems(mixed $items): array
    {
        $result = [];

        foreach (is_array($items) ? $items : [] as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : null;

            if (in_array($id, self::HEADER_ITEMS, true) && ! isset($result[$id])) {
                $result[$id] = ['id' => $id, 'hidden' => (bool) ($item['hidden'] ?? false)] + $this->pick(self::HEADER_ITEM_STYLE, $item);
            }
        }

        foreach (self::HEADER_ITEMS as $id) {
            $result[$id] ??= ['id' => $id, 'hidden' => false] + $this->pick(self::HEADER_ITEM_STYLE, []);
        }

        return array_values($result);
    }

    /** Цвет только вида #rrggbb, иначе null. */
    private function hex(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : null;
    }

    private function clampInt(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }

    /** Каждое поле — только из своего списка, иначе значение по умолчанию. */
    private function pick(array $schema, array $input): array
    {
        $result = [];

        foreach ($schema as $key => $allowed) {
            $result[$key] = in_array($input[$key] ?? null, $allowed, true) ? $input[$key] : $allowed[0];
        }

        return $result;
    }

    private function style(array $style): array
    {
        return $this->pick(self::STYLE, $style) + [
            'title' => $this->str($style['title'] ?? '', 60),
            // Эмодзи перед заголовком
            'icon' => mb_substr(trim(strip_tags((string) ($style['icon'] ?? ''))), 0, 4),
            // Свой цвет блока пипеткой (вместо палитры), наклон и прозрачность
            'hex' => $this->hex($style['hex'] ?? null) ?? '',
            'tilt' => $this->clampInt($style['tilt'] ?? 0, -4, 4),
            'opacity' => $this->clampInt($style['opacity'] ?? 100, 30, 100),
            // Свой градиент фона: два цвета и угол; пустые цвета — без градиента
            'grad_from' => $this->hex($style['grad_from'] ?? null) ?? '',
            'grad_to' => $this->hex($style['grad_to'] ?? null) ?? '',
            'grad_angle' => $this->clampInt($style['grad_angle'] ?? 135, 0, 360),
            // Толщина рамки цветом блока; 0 — обычная
            'bw' => $this->clampInt($style['bw'] ?? 0, 0, 6),
            'hide_title' => (bool) ($style['hide_title'] ?? false),
            'hide_mobile' => (bool) ($style['hide_mobile'] ?? false),
        ];
    }

    /** @param array<int, mixed> $blocks */
    public function normalize(array $blocks): array
    {
        if (count($blocks) > self::MAX_BLOCKS) {
            throw ValidationException::withMessages([
                'blocks' => ['Не больше ' . self::MAX_BLOCKS . ' блоков в профиле.'],
            ]);
        }

        $seen = [];
        $builtins = [];
        $result = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ! in_array($block['type'] ?? null, self::TYPES, true)) {
                continue;
            }

            // Готовая часть профиля — только один раз
            if (in_array($block['type'], self::SINGLE, true)) {
                if (isset($builtins[$block['type']])) {
                    continue;
                }
                $builtins[$block['type']] = true;
            }

            $id = preg_replace('/[^a-z0-9]/', '', strtolower((string) ($block['id'] ?? '')));
            $id = substr($id, 0, 16) ?: substr(md5(uniqid('', true)), 0, 8);

            // Повторный id ломает перетаскивание на странице — делаем уникальным
            while (isset($seen[$id])) {
                $id = substr(md5($id . uniqid('', true)), 0, 8);
            }
            $seen[$id] = true;

            $result[] = [
                'id' => $id,
                'type' => $block['type'],
                'width' => in_array($block['width'] ?? null, self::WIDTHS, true) ? $block['width'] : 'full',
                'tone' => in_array($block['tone'] ?? null, self::TONES, true) ? $block['tone'] : 'default',
                'settings' => $this->settings($block['type'], is_array($block['settings'] ?? null) ? $block['settings'] : []),
                'style' => $this->style(is_array($block['style'] ?? null) ? $block['style'] : []),
            ];
        }

        return $result;
    }

    private function settings(string $type, array $s): array
    {
        return match ($type) {
            'text' => [
                'body' => $this->str($s['body'] ?? '', 2000),
                'size' => in_array($s['size'] ?? null, ['s', 'm', 'l', 'xl'], true) ? $s['size'] : 'm',
            ],
            'quote' => [
                'text' => $this->str($s['text'] ?? '', 300),
                'author' => $this->str($s['author'] ?? '', 60),
            ],
            'facts' => [
                'items' => $this->items($s, 10, fn ($item) => [
                    'label' => $this->str($item['label'] ?? '', 30),
                    'value' => $this->str($item['value'] ?? '', 80),
                ], 'label'),
            ],
            'bars' => [
                'items' => $this->items($s, 8, fn ($item) => [
                    'label' => $this->str($item['label'] ?? '', 30),
                    'value' => max(0, min(100, (int) ($item['value'] ?? 0))),
                ], 'label'),
            ],
            'timeline' => [
                'items' => $this->items($s, 10, fn ($item) => [
                    'when' => $this->str($item['when'] ?? '', 20),
                    'text' => $this->str($item['text'] ?? '', 120),
                ], 'text'),
            ],
            // Тиммейт — игрок APEX по id; ник храним на случай, если аккаунт удалят
            'players' => [
                'items' => collect($this->items($s, 8, fn ($item) => [
                    'user_id' => max(0, (int) ($item['user_id'] ?? 0)),
                    'username' => preg_replace('/[^A-Za-z0-9_]/', '', mb_substr((string) ($item['username'] ?? ''), 0, 32)),
                    'note' => $this->str($item['note'] ?? '', 30),
                    'role' => in_array($item['role'] ?? null, self::TEAMMATE_ROLES, true) ? $item['role'] : 'duo',
                ], 'username'))
                    ->unique(fn ($item) => $item['user_id'] ?: $item['username'])
                    ->values()
                    ->all(),
            ],
            'spacer' => [
                'size' => in_array($s['size'] ?? null, ['s', 'm', 'l'], true) ? $s['size'] : 'm',
            ],
            // Опрос: вопрос и 2–4 варианта, гости голосуют (голоса — в profile_poll_votes)
            'poll' => [
                'question' => $this->str($s['question'] ?? '', 120),
                'options' => collect(is_array($s['options'] ?? null) ? $s['options'] : [])
                    ->map(fn ($o) => $this->str($o, 60))
                    ->take(4)
                    ->values()
                    ->all(),
            ],
            'goals' => [
                'items' => $this->items($s, 10, fn ($item) => [
                    'text' => $this->str($item['text'] ?? '', 80),
                    'done' => (bool) ($item['done'] ?? false),
                ], 'text'),
            ],
            'counter' => [
                'value' => mb_substr(preg_replace('/[^0-9.,kKкК+%\s]/u', '', (string) ($s['value'] ?? '')), 0, 12),
                'label' => $this->str($s['label'] ?? '', 40),
                'emoji' => mb_substr(trim(strip_tags((string) ($s['emoji'] ?? ''))), 0, 4),
            ],
            'countdown' => [
                'title' => $this->str($s['title'] ?? '', 80),
                'date' => $this->date($s['date'] ?? ''),
            ],
            'video' => [
                'url' => $this->youtube($s['url'] ?? ''),
            ],
            'sticker' => [
                'sprite' => in_array($s['sprite'] ?? null, self::STICKER_SPRITES, true) ? $s['sprite'] : 'creeper',
                'caption' => $this->str($s['caption'] ?? '', 40),
            ],
            'divider' => [
                'style' => in_array($s['style'] ?? null, ['line', 'dots', 'glow', 'zigzag', 'blocks'], true) ? $s['style'] : 'line',
            ],
            'setup' => [
                'items' => $this->items($s, 10, fn ($item) => [
                    'kind' => in_array($item['kind'] ?? null, self::SETUP_KINDS, true) ? $item['kind'] : 'other',
                    'value' => $this->str($item['value'] ?? '', 60),
                ], 'value'),
            ],
            'links' => [
                'style' => in_array($s['style'] ?? null, ['solid', 'outline', 'glass'], true) ? $s['style'] : 'solid',
                'items' => $this->items($s, 6, fn ($item) => [
                    'label' => $this->str($item['label'] ?? '', 30),
                    'url' => $this->url($item['url'] ?? ''),
                    'icon' => in_array($item['icon'] ?? null, self::LINK_ICONS, true) ? $item['icon'] : 'link',
                    'color' => $this->hex($item['color'] ?? null) ?? '',
                ], 'url'),
            ],
            'music' => [
                'url' => $this->musicUrl($s['url'] ?? ''),
                'title' => $this->str($s['title'] ?? '', 60),
                'artist' => $this->str($s['artist'] ?? '', 60),
            ],
            'faq' => [
                'items' => $this->items($s, 8, fn ($item) => [
                    'q' => $this->str($item['q'] ?? '', 100),
                    'a' => $this->str($item['a'] ?? '', 400),
                ], 'q'),
            ],
            'marquee' => [
                'text' => $this->str($s['text'] ?? '', 120),
                'speed' => in_array($s['speed'] ?? null, ['normal', 'slow', 'fast'], true) ? $s['speed'] : 'normal',
                'direction' => in_array($s['direction'] ?? null, ['left', 'right'], true) ? $s['direction'] : 'left',
                'size' => in_array($s['size'] ?? null, ['m', 'l', 'xl'], true) ? $s['size'] : 'm',
            ],
            'schedule' => [
                'items' => $this->items($s, 14, fn ($item) => [
                    'day' => in_array($item['day'] ?? null, self::WEEKDAYS, true) ? $item['day'] : 'mon',
                    'time' => $this->str($item['time'] ?? '', 20),
                    'text' => $this->str($item['text'] ?? '', 60),
                ], 'text'),
            ],
            'banner' => [
                'title' => $this->str($s['title'] ?? '', 60),
                'text' => $this->str($s['text'] ?? '', 140),
                'button' => $this->str($s['button'] ?? '', 30),
                'url' => $this->url($s['url'] ?? ''),
            ],
            'progress' => [
                'title' => $this->str($s['title'] ?? '', 60),
                'current' => max(0, min(1_000_000_000, (int) ($s['current'] ?? 0))),
                'target' => max(1, min(1_000_000_000, (int) ($s['target'] ?? 100))),
                'unit' => $this->str($s['unit'] ?? '', 12),
            ],
            default => [],
        };
    }

    /** Ссылка на трек — только с известных музыкальных сервисов. */
    private function musicUrl(mixed $value): string
    {
        $url = $this->url($value);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, self::MUSIC_HOSTS, true) ? $url : '';
    }

    /** Список строк блока: не больше $max, пустые (без $required) выкидываем. */
    private function items(array $s, int $max, callable $map, string $required): array
    {
        return collect(is_array($s['items'] ?? null) ? $s['items'] : [])
            ->filter(fn ($item) => is_array($item))
            ->take($max)
            ->map($map)
            ->filter(fn ($item) => $item[$required] !== '')
            ->values()
            ->all();
    }

    private function str(mixed $value, int $max): string
    {
        return mb_substr(trim(strip_tags((string) $value)), 0, $max);
    }

    private function url(mixed $value): string
    {
        $url = trim((string) $value);

        return mb_strlen($url) <= 300 && preg_match('#^https?://[^\s<>"]+$#i', $url) ? $url : '';
    }

    /** Только ролики YouTube: страница встраивает их через youtube-nocookie. */
    private function youtube(mixed $value): string
    {
        $url = $this->url($value);

        return preg_match('#^https?://(www\.|m\.)?(youtube\.com|youtu\.be)/#i', $url) ? $url : '';
    }

    private function date(mixed $value): string
    {
        $time = strtotime((string) $value);

        return $time ? date('c', $time) : '';
    }

    /* -------------------------------- Опросы -------------------------------- */

    /** Блок опроса из раскладки игрока или 404. */
    private function pollBlock(User $owner, string $blockId): array
    {
        $block = collect($owner->profile_blocks ?? [])->first(
            fn ($b) => ($b['type'] ?? null) === 'poll' && ($b['id'] ?? null) === $blockId,
        );

        abort_unless($block && count($block['settings']['options'] ?? []) >= 2, 404, 'Опрос не найден.');

        return $block;
    }

    /** Результаты: голоса по вариантам и мой выбор. */
    public function pollResults(User $owner, string $blockId, ?User $viewer): array
    {
        $block = $this->pollBlock($owner, $blockId);
        $options = $block['settings']['options'];

        $counts = DB::table('profile_poll_votes')
            ->where('owner_id', $owner->id)
            ->where('block_id', $blockId)
            ->selectRaw('`option`, count(*) as total')
            ->groupBy('option')
            ->pluck('total', 'option');

        $mine = $viewer
            ? DB::table('profile_poll_votes')
                ->where('owner_id', $owner->id)
                ->where('block_id', $blockId)
                ->where('user_id', $viewer->id)
                ->value('option')
            : null;

        return [
            'votes' => collect($options)->keys()->map(fn ($i) => (int) ($counts[$i] ?? 0))->all(),
            'total' => (int) $counts->sum(),
            'mine' => $mine === null ? null : (int) $mine,
        ];
    }

    public function vote(User $owner, User $actor, string $blockId, int $option): array
    {
        $block = $this->pollBlock($owner, $blockId);

        if ($option < 0 || $option >= count($block['settings']['options'])) {
            throw ValidationException::withMessages(['option' => ['Такого варианта нет.']]);
        }

        DB::table('profile_poll_votes')->updateOrInsert(
            ['owner_id' => $owner->id, 'block_id' => $blockId, 'user_id' => $actor->id],
            ['option' => $option, 'updated_at' => now(), 'created_at' => now()],
        );

        return $this->pollResults($owner, $blockId, $actor);
    }

    /* ------------------------------- Тиммейты ------------------------------- */

    /**
     * Живые данные тиммейтов для блока: в раскладке хранится только id,
     * аватар, тир и косметика ника берутся свежие. Порядок — как в $ids.
     *
     * @param  array<int, int>  $ids
     */
    public function teammates(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        $users = User::query()
            ->with('clanMember.clan:id,tag,banner_color')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn (int $id) => $users->get($id))
            ->filter()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'username' => $u->username,
                'minecraft_username' => $u->minecraft_username,
                'avatar_url' => $u->avatar_url,
                'tier' => $u->tier,
                'display_rank' => $u->display_rank,
                'name_effect' => $u->name_effect,
                'name_icon' => $u->name_icon,
                'accent_color' => $u->accent_color,
                'clan_tag' => $u->clanMember?->clan?->tag,
                'clan_color' => $u->clanMember?->clan?->banner_color,
            ])
            ->values()
            ->all();
    }

    /* ----------------------------- Гостевая книга ----------------------------- */

    public function hasGuestbook(User $owner): bool
    {
        return collect($owner->profile_blocks ?? [])->contains('type', 'guestbook');
    }

    public function guestbook(User $owner, int $limit = 30): Collection
    {
        return GuestbookEntry::where('owner_id', $owner->id)
            ->with('author:id,username,avatar,tier,name_effect,name_icon,profile_title')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (GuestbookEntry $entry) => $this->presentEntry($entry));
    }

    public function write(User $owner, User $author, string $body): array
    {
        if (! $this->hasGuestbook($owner)) {
            throw ValidationException::withMessages(['body' => ['У игрока нет гостевой книги.']]);
        }

        $body = $this->str($body, 200);

        if ($body === '') {
            throw ValidationException::withMessages(['body' => ['Напишите что-нибудь.']]);
        }

        $entry = GuestbookEntry::create([
            'owner_id' => $owner->id,
            'author_id' => $author->id,
            'body' => $body,
        ]);

        return $this->presentEntry($entry->load('author:id,username,avatar,tier,name_effect,name_icon,profile_title'));
    }

    /** Удалить может хозяин профиля, автор записи или модератор. */
    public function remove(GuestbookEntry $entry, User $actor): void
    {
        $allowed = $actor->id === $entry->owner_id
            || $actor->id === $entry->author_id
            || $actor->isStaff();

        abort_unless($allowed, 403, 'Удалить запись может хозяин профиля или её автор.');

        $entry->delete();
    }

    private function presentEntry(GuestbookEntry $entry): array
    {
        $author = $entry->author;

        return [
            'id' => $entry->id,
            'body' => $entry->body,
            'created_at' => $entry->created_at?->toIso8601String(),
            'author' => $author ? [
                'id' => $author->id,
                'username' => $author->username,
                'avatar_url' => $author->avatar_url,
                'name_effect' => $author->name_effect,
                'name_icon' => $author->name_icon,
            ] : null,
        ];
    }
}
