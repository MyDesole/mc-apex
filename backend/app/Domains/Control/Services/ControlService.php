<?php

namespace App\Domains\Control\Services;

use App\Domains\Core\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * «Пульт» админки: объявление на весь сайт, переключатели функций
 * и событие-множитель ApexCoin.
 *
 * Хранится в site_settings (группа control), читается через кеш на минуту:
 * переключатели проверяются на каждом запросе к закрываемым маршрутам.
 */
class ControlService
{
    private const CACHE_KEY = 'control:state';

    private const CACHE_TTL = 60;

    /** Что можно выключить одной кнопкой — ключ и подпись для админки. */
    public const FEATURES = [
        'registration' => 'Регистрация',
        'shop' => 'Покупки в магазине',
        'tier_tests' => 'Заявки на тир-тест',
        'clan_creation' => 'Создание кланов',
        'forum_posting' => 'Новые темы и ответы на форуме',
    ];

    /** Источники, на которые действует множитель события. */
    public const BOOSTED_SOURCES = ['tier_test', 'achievement', 'daily_bonus', 'referral'];

    public const MAX_MULTIPLIER = 5;

    public static function state(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $features = [];

            foreach (array_keys(self::FEATURES) as $key) {
                $features[$key] = (bool) SiteSetting::get("feature_{$key}", true);
            }

            return [
                'features' => $features,
                'announcement' => SiteSetting::get('announcement', null),
                'multiplier' => (int) SiteSetting::get('coin_multiplier', 1),
                'multiplier_until' => SiteSetting::get('coin_multiplier_until', null),
            ];
        });
    }

    public static function enabled(string $feature): bool
    {
        return self::state()['features'][$feature] ?? true;
    }

    /** Активное объявление или null — истёкшее не показываем. */
    public static function announcement(): ?array
    {
        $announcement = self::state()['announcement'];

        if (! is_array($announcement) || empty($announcement['text'])) {
            return null;
        }

        if (! empty($announcement['until']) && Carbon::parse($announcement['until'])->isPast()) {
            return null;
        }

        return $announcement;
    }

    /** Действующий множитель ApexCoin: 1, если события нет или оно кончилось. */
    public static function multiplier(): int
    {
        $state = self::state();
        $value = max(1, min(self::MAX_MULTIPLIER, (int) $state['multiplier']));

        if ($value > 1 && $state['multiplier_until'] && Carbon::parse($state['multiplier_until'])->isPast()) {
            return 1;
        }

        return $value;
    }

    /** То, что видит сайт: баннер, выключенные функции, событие. */
    public static function publicState(): array
    {
        $multiplier = self::multiplier();

        return [
            'announcement' => self::announcement(),
            'features' => self::state()['features'],
            'event' => $multiplier > 1 ? [
                'multiplier' => $multiplier,
                'until' => self::state()['multiplier_until'],
            ] : null,
        ];
    }

    public static function update(array $data): void
    {
        if (array_key_exists('announcement', $data)) {
            $announcement = $data['announcement'];
            SiteSetting::set('announcement', $announcement && ! empty($announcement['text']) ? $announcement : null, 'json', 'control');
        }

        foreach ($data['features'] ?? [] as $key => $enabled) {
            if (array_key_exists($key, self::FEATURES)) {
                SiteSetting::set("feature_{$key}", (bool) $enabled, 'bool', 'control');
            }
        }

        if (array_key_exists('multiplier', $data)) {
            SiteSetting::set('coin_multiplier', (int) $data['multiplier'], 'int', 'control');
            SiteSetting::set('coin_multiplier_until', $data['multiplier_until'] ?? '', 'string', 'control');
        }

        Cache::forget(self::CACHE_KEY);
    }
}
