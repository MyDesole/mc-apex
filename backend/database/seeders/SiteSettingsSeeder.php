<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Hero
            ['key' => 'hero_title', 'value' => 'APEX TIERS', 'type' => 'string', 'group' => 'hero'],
            ['key' => 'hero_subtitle', 'value' => 'Рейтинг игроков и кланов Minecraft PvP', 'type' => 'string', 'group' => 'hero'],
            ['key' => 'hero_badge', 'value' => 'Season 1 · Live', 'type' => 'string', 'group' => 'hero'],
            ['key' => 'hero_primary_text', 'value' => 'Смотреть игроков', 'type' => 'string', 'group' => 'hero'],
            ['key' => 'hero_primary_url', 'value' => '/players', 'type' => 'string', 'group' => 'hero'],
            ['key' => 'hero_secondary_text', 'value' => 'Все кланы', 'type' => 'string', 'group' => 'hero'],
            ['key' => 'hero_secondary_url', 'value' => '/clans', 'type' => 'string', 'group' => 'hero'],

            // Соцсети
            ['key' => 'social_discord', 'value' => '', 'type' => 'string', 'group' => 'socials'],
            ['key' => 'social_telegram', 'value' => '', 'type' => 'string', 'group' => 'socials'],
            ['key' => 'social_youtube', 'value' => '', 'type' => 'string', 'group' => 'socials'],
            ['key' => 'social_vk', 'value' => '', 'type' => 'string', 'group' => 'socials'],
            ['key' => 'social_twitch', 'value' => '', 'type' => 'string', 'group' => 'socials'],
            ['key' => 'social_twitter', 'value' => '', 'type' => 'string', 'group' => 'socials'],

            // Footer
            ['key' => 'footer_text', 'value' => '© 2026 APEX TIERS. Все права защищены.', 'type' => 'string', 'group' => 'footer'],

            // Stats (для отображения на главной)
            ['key' => 'stats_show', 'value' => true, 'type' => 'bool', 'group' => 'stats'],
        ];

        foreach ($defaults as $s) {
            SiteSetting::updateOrCreate(['key' => $s['key']], $s);
        }
    }
}
