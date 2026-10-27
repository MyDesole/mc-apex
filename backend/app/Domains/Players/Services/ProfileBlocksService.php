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
 */
class ProfileBlocksService
{
    public const MAX_BLOCKS = 24;

    public const TYPES = [
        // Готовые блоки профиля
        'stats', 'socials', 'games', 'aspects', 'achievements', 'friends', 'recommendations',
        // Новые, со своими настройками
        'text', 'quote', 'links', 'now', 'countdown', 'video', 'guestbook', 'sticker', 'divider', 'clan',
    ];

    public const WIDTHS = ['third', 'half', 'two_thirds', 'full'];

    public const TONES = ['default', 'accent', 'glass', 'outline', 'transparent'];

    private const STICKER_SPRITES = [
        'creeper', 'bee', 'axolotl', 'tnt', 'diamond', 'heart', 'skull', 'warden', 'totem', 'cat',
        'paw', 'crown', 'flame', 'dog', 'fox', 'slime', 'ghost', 'dragon', 'robot', 'penguin',
    ];

    /**
     * Сохранить раскладку. null — вернуть профиль по умолчанию.
     *
     * @param  array<int, mixed>|null  $blocks
     */
    public function save(User $user, ?array $blocks): ?array
    {
        $clean = $blocks === null ? null : $this->normalize($blocks);

        $user->forceFill(['profile_blocks' => $clean])->save();

        return $clean;
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
        $result = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ! in_array($block['type'] ?? null, self::TYPES, true)) {
                continue;
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
            ];
        }

        return $result;
    }

    private function settings(string $type, array $s): array
    {
        return match ($type) {
            'text' => [
                'title' => $this->str($s['title'] ?? '', 80),
                'body' => $this->str($s['body'] ?? '', 2000),
            ],
            'quote' => [
                'text' => $this->str($s['text'] ?? '', 300),
                'author' => $this->str($s['author'] ?? '', 60),
            ],
            'links' => [
                'items' => collect(is_array($s['items'] ?? null) ? $s['items'] : [])
                    ->take(8)
                    ->map(fn ($item) => [
                        'label' => $this->str($item['label'] ?? '', 40),
                        'url' => $this->url($item['url'] ?? ''),
                    ])
                    ->filter(fn ($item) => $item['label'] !== '' && $item['url'] !== '')
                    ->values()
                    ->all(),
            ],
            'now' => [
                'emoji' => $this->str($s['emoji'] ?? '', 8),
                'text' => $this->str($s['text'] ?? '', 120),
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
                'style' => in_array($s['style'] ?? null, ['line', 'dots', 'glow'], true) ? $s['style'] : 'line',
            ],
            'guestbook' => [
                'title' => $this->str($s['title'] ?? '', 60),
            ],
            default => [],
        };
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
