<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Переводит полиморфные типы на короткие имена.
 *
 * Поля хранили полное имя класса (App\Models\ShopItem). Модели переехали
 * в домены (App\Domains\Shop\Models\ShopItem), поэтому без этой миграции
 * старые записи перестали бы находить свои модели. Теперь в базе короткое
 * имя из morphMap, и оно не зависит от расположения класса.
 */
return new class extends Migration
{
    /**
     * Что заменить: полное имя класса -> короткое имя из morphMap.
     *
     * Указаны оба варианта пространства имён: старый (App\Models) и новый
     * (App\Domains\...), чтобы миграция работала на любой базе.
     */
    private const MAP = [
        'User' => 'user',
        'Clan' => 'clan',
        'ClanApplication' => 'clan_application',
        'ClanEvent' => 'clan_event',
        'ClanResource' => 'clan_resource',
        'ClanWar' => 'clan_war',
        'ForumTopic' => 'forum_topic',
        'ForumReply' => 'forum_reply',
        'ForumCategory' => 'forum_category',
        'ForumAttachment' => 'forum_attachment',
        'Message' => 'message',
        'Conversation' => 'conversation',
        'ShopItem' => 'shop_item',
        'ShopSetting' => 'shop_setting',
        'UserInventory' => 'user_inventory',
        'Achievement' => 'achievement',
        'UserAchievement' => 'user_achievement',
        'TierTest' => 'tier_test',
        'Tournament' => 'tournament',
        'TournamentMatch' => 'tournament_match',
        'TournamentParticipant' => 'tournament_participant',
        'News' => 'news',
        'Friendship' => 'friendship',
        'CoinTransaction' => 'coin_transaction',
    ];

    /** Таблицы и колонки с полиморфным типом. */
    private const COLUMNS = [
        ['coin_transactions', 'reference_type'],
        ['notifications', 'notifiable_type'],
        ['personal_access_tokens', 'tokenable_type'],
    ];

    /** Домены моделей: нужны, чтобы собрать новые имена классов. */
    private const DOMAINS = [
        'User' => 'Users', 'Clan' => 'Clan', 'ClanApplication' => 'Clan',
        'ClanEvent' => 'Clan', 'ClanResource' => 'Clan', 'ClanWar' => 'Clan',
        'ForumTopic' => 'Forum', 'ForumReply' => 'Forum', 'ForumCategory' => 'Forum',
        'ForumAttachment' => 'Forum', 'Message' => 'Chat', 'Conversation' => 'Chat',
        'ShopItem' => 'Shop', 'ShopSetting' => 'Shop', 'UserInventory' => 'Shop',
        'Achievement' => 'Achievements', 'UserAchievement' => 'Achievements',
        'TierTest' => 'Tiers', 'Tournament' => 'Tournaments',
        'TournamentMatch' => 'Tournaments', 'TournamentParticipant' => 'Tournaments',
        'News' => 'News', 'Friendship' => 'Friends', 'CoinTransaction' => 'Wallet',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as [$table, $column]) {
            foreach (self::MAP as $class => $alias) {
                $candidates = [
                    "App\\Models\\{$class}",
                    'App\\Domains\\' . self::DOMAINS[$class] . "\\Models\\{$class}",
                ];

                DB::table($table)
                    ->whereIn($column, $candidates)
                    ->update([$column => $alias]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as [$table, $column]) {
            foreach (self::MAP as $class => $alias) {
                DB::table($table)
                    ->where($column, $alias)
                    ->update([$column => "App\\Models\\{$class}"]);
            }
        }
    }
};
