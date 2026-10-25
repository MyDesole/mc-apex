<?php

use App\Domains\Achievements\Models\Achievement;
use Database\Seeders\AchievementSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Пиксельные иконки ачивок вместо эмодзи.
 *
 * Эмодзи рисуются по-разному в каждой системе и не похожи на остальной
 * интерфейс. Теперь icon — ключ пиксельной иконки.
 *
 * Меняется только иконка и только там, где всё ещё эмодзи: правки
 * админа в названиях и описаниях сохраняются. Новые ачивки каталога
 * ставит AchievementSeeder — как и раньше, отдельно от схемы.
 */
return new class extends Migration
{
    /** Эмодзи старых ачивок → ключ пиксельной иконки. */
    private const EMOJI = [
        '🛡️' => 'shield', '🛡' => 'shield', '👑' => 'crown', '⚔️' => 'swords', '⚔' => 'swords',
        '🗡️' => 'sword', '🗡' => 'sword', '🏆' => 'trophy', '🔥' => 'flame', '🌱' => 'sword',
        '⚡' => 'bolt', '💎' => 'diamond', '🌟' => 'star', '⭐' => 'star', '✨' => 'star',
        '🎯' => 'book', '📋' => 'book', '🤝' => 'head', '🌐' => 'orb', '📊' => 'star',
        '🥇' => 'crown', '🙏' => 'heart', '❤️' => 'heart', '💜' => 'heart', '🎁' => 'chest',
        '💰' => 'coin', '🪙' => 'coin', '💬' => 'chat', '📢' => 'chat', '🛏️' => 'bed',
        '💣' => 'tnt', '🚩' => 'flag', '⛏️' => 'pickaxe', '🟢' => 'emerald',
    ];

    public function up(): void
    {
        $catalog = collect((new AchievementSeeder)->catalog())->keyBy('code');

        foreach (Achievement::all() as $achievement) {
            if (! $this->isEmoji($achievement->icon)) {
                continue;
            }

            $achievement->icon = $catalog->get($achievement->code)['icon']
                ?? self::EMOJI[$achievement->icon]
                ?? 'star';

            $achievement->save();
        }
    }

    public function down(): void
    {
        // Данные: откатывать нечего — ключи иконок остаются валидными
    }

    /** Ключи иконок — латиница с подчёркиваниями, всё остальное считаем эмодзи. */
    private function isEmoji(?string $icon): bool
    {
        return $icon !== null && ! preg_match('/^[a-z_]+$/', $icon);
    }
};
