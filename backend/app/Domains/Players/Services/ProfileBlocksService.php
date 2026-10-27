<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Models\GuestbookEntry;
use App\Domains\Users\Models\User;
use Illuminate\Support\Collection;
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
        'facts', 'bars', 'timeline', 'players',
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
    ];

    /** Настройки всей страницы. */
    private const PAGE = [
        // masonry — блоки встают в ближайшее свободное место, без дыр
        'flow' => ['masonry', 'rows'],
        'gap' => ['normal', 'tight', 'loose'],
        'header' => ['full', 'compact'],
        'appear' => ['rise', 'none', 'fade', 'zoom', 'cascade'],
        'radius' => ['round', 'sharp', 'soft'],
        'backdrop' => ['none', 'dim', 'blur'],
    ];

    /** Кем тиммейт приходится игроку: подпись и цвет карточки. */
    private const TEAMMATE_ROLES = ['duo', 'squad', 'coach', 'student', 'rival', 'bestie'];

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
        return $this->pick(self::PAGE, $page);
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
            default => [],
        };
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
