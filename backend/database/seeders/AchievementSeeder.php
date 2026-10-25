<?php

namespace Database\Seeders;

use App\Domains\Achievements\Models\Achievement;
use Illuminate\Database\Seeder;

/**
 * Каталог ачивок.
 *
 * icon — ключ пиксельной иконки из frontend/src/data/achievements/pixelIcons.js,
 * условия выдачи — в AchievementRules. Сидер идемпотентен: повторный
 * запуск обновляет существующие ачивки по коду и добавляет новые.
 */
class AchievementSeeder extends Seeder
{
    /** Цвета групп: ачивки одной темы узнаются по цвету. */
    private const CLAN = '#a78bfa';
    private const TIER = '#facc15';
    private const TEST = '#22d3ee';
    private const SKILL = '#fb923c';
    private const SOCIAL = '#4ade80';
    private const FORUM = '#60a5fa';
    private const SHOP = '#f472b6';
    private const EVENT = '#f87171';
    private const MISC = '#94a3b8';

    public function run(): void
    {
        foreach ($this->catalog() as $achievement) {
            Achievement::updateOrCreate(['code' => $achievement['code']], $achievement);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalog(): array
    {
        return [
            // ===================== КЛАН =====================
            $this->make('clan_joined', 'Командный игрок', 'Вступить в клан', 'shield', self::CLAN, 'common', 10),
            $this->make('clan_officer', 'Офицер', 'Получить роль офицера в клане', 'swords', self::CLAN, 'common', 20),
            $this->make('clan_leader', 'Основатель', 'Создать свой клан', 'flag', self::CLAN, 'rare', 50),
            $this->make('clan_squad', 'Отряд', 'Состоять в клане из 10 и более игроков', 'head', self::CLAN, 'rare', 30),
            $this->make('clan_war_win', 'Первая победа', 'Выиграть клановую войну', 'swords', self::EVENT, 'rare', 30),
            $this->make('clan_war_5', 'Ветеран войн', 'Выиграть 5 клановых войн', 'flame', self::EVENT, 'epic', 80),
            $this->make('clan_war_10', 'Завоеватель', 'Выиграть 10 клановых войн', 'crown', self::EVENT, 'legendary', 200),
            $this->make('clan_power_1000', 'Сила клана', 'Состоять в клане с силой 1000+', 'bolt', self::CLAN, 'epic', 100),

            // ===================== ТИРЫ =====================
            $this->make('tier_e', 'Первые шаги', 'Получить первый тир', 'sword', '#94a3b8', 'common', 5),
            $this->make('tier_d', 'Ученик', 'Достичь тира D', 'sword', '#4ade80', 'common', 10),
            $this->make('tier_c', 'Боец', 'Достичь тира C', 'sword', '#22d3ee', 'common', 25),
            $this->make('tier_b', 'Ветеран', 'Достичь тира B', 'diamond', '#a78bfa', 'rare', 50),
            $this->make('tier_a', 'Элита', 'Достичь тира A', 'emerald', '#fb923c', 'epic', 100),
            $this->make('tier_s', 'Легенда', 'Достичь тира S', 'crown', self::TIER, 'legendary', 250),
            $this->make('tier_s_plus', 'Абсолют', 'Достичь тира S+', 'star', self::TIER, 'legendary', 400),

            // ===================== ТИР-ТЕСТЫ =====================
            $this->make('tier_test_first', 'Дебют', 'Пройти первый тир-тест', 'book', self::TEST, 'common', 10),
            $this->make('tier_test_5', 'Испытуемый', 'Пройти 5 тир-тестов', 'book', self::TEST, 'rare', 40),
            $this->make('tier_test_10', 'Завсегдатай арены', 'Пройти 10 тир-тестов', 'trophy', self::TEST, 'epic', 90),
            $this->make('tier_comeback', 'Рост', 'Улучшить результат тир-теста в том же режиме', 'bolt', self::TEST, 'rare', 40),

            // ===================== НАВЫКИ =====================
            $this->make('block_master', 'Мастер блоков', 'Получить 18+ за блоки', 'pickaxe', self::SKILL, 'rare', 40),
            $this->make('rod_master', 'Удочник', 'Получить 18+ за удочку', 'orb', self::SKILL, 'rare', 40),
            $this->make('movement_master', 'Неуловимый', 'Получить 18+ за мувмент', 'bolt', self::SKILL, 'rare', 40),
            $this->make('aim_master', 'Снайпер', 'Получить 18+ за аим', 'sword', self::SKILL, 'rare', 40),
            $this->make('tactician', 'Тактик', 'Получить 18+ за понимание боя', 'book', self::SKILL, 'rare', 40),
            $this->make('all_rounder', 'Универсал', 'Получить 15+ за каждый аспект PvP', 'star', self::SKILL, 'epic', 120),
            $this->make('aspect_perfect', 'Идеал', 'Получить максимальные 20 за любой аспект', 'diamond', self::SKILL, 'epic', 100),
            $this->make('bedwars_player', 'Защитник кровати', 'Пройти тир-тест по BedWars', 'bed', self::SKILL, 'common', 15),
            $this->make('dual_mode', 'Двойная угроза', 'Пройти тир-тесты и в PvP, и в BedWars', 'tnt', self::SKILL, 'rare', 50),

            // ===================== ТУРНИРЫ =====================
            $this->make('tournament_join', 'На арене', 'Принять участие в турнире', 'flag', self::EVENT, 'common', 15),
            $this->make('tournament_first_win', 'Чемпион', 'Выиграть турнир', 'trophy', self::TIER, 'epic', 150),
            $this->make('tournament_win_3', 'Династия', 'Выиграть 3 турнира', 'crown', self::TIER, 'legendary', 400),

            // ===================== РЕЙТИНГ =====================
            $this->make('top_10', 'В десятке', 'Попасть в топ-10 игроков', 'star', self::TIER, 'rare', 50),
            $this->make('top_3', 'Пьедестал', 'Попасть в топ-3 игроков', 'trophy', self::TIER, 'epic', 150),
            $this->make('top_1', 'Первый', 'Занять первое место в рейтинге', 'crown', self::TIER, 'legendary', 500),

            // ===================== СОЦИАЛКА =====================
            $this->make('friends_5', 'Душа компании', 'Завести 5 друзей', 'head', self::SOCIAL, 'common', 20),
            $this->make('friends_20', 'Свой человек', 'Завести 20 друзей', 'heart', self::SOCIAL, 'rare', 60),
            $this->make('friends_50', 'Знаменитость', 'Завести 50 друзей', 'star', self::SOCIAL, 'epic', 120),
            $this->make('recommended', 'Рекомендован', 'Получить рекомендацию в профиль', 'heart', self::SOCIAL, 'common', 15),
            $this->make('recommended_5', 'Авторитет', 'Получить 5 рекомендаций', 'shield', self::SOCIAL, 'rare', 50),
            $this->make('referral_1', 'Проводник', 'Пригласить игрока по реферальной ссылке', 'orb', self::SOCIAL, 'common', 20),
            $this->make('referral_5', 'Вербовщик', 'Пригласить 5 игроков', 'flag', self::SOCIAL, 'epic', 100),

            // ===================== ФОРУМ И ЧАТ =====================
            $this->make('forum_topic_first', 'Голос сообщества', 'Создать тему на форуме', 'book', self::FORUM, 'common', 10),
            $this->make('forum_replies_10', 'Собеседник', 'Оставить 10 ответов на форуме', 'chat', self::FORUM, 'common', 20),
            $this->make('forum_replies_50', 'Старожил форума', 'Оставить 50 ответов на форуме', 'chat', self::FORUM, 'rare', 60),
            $this->make('forum_liked_25', 'Полезный', 'Собрать 25 лайков на форуме', 'heart', self::FORUM, 'rare', 50),
            $this->make('chat_100', 'Разговорчивый', 'Отправить 100 сообщений', 'chat', self::FORUM, 'common', 15),
            $this->make('chat_1000', 'Не умолкает', 'Отправить 1000 сообщений', 'chat', self::FORUM, 'epic', 80),

            // ===================== МАГАЗИН =====================
            $this->make('shop_first', 'Первая покупка', 'Купить предмет в магазине', 'chest', self::SHOP, 'common', 10),
            $this->make('shop_collector', 'Коллекционер', 'Собрать 10 предметов', 'chest', self::SHOP, 'rare', 50),
            $this->make('big_spender', 'Транжира', 'Потратить 5000 ApexCoin', 'coin', self::SHOP, 'epic', 100),
            $this->make('gift_giver', 'Щедрый', 'Подарить предмет другу', 'heart', self::SHOP, 'rare', 30),

            // ===================== ПРОЧЕЕ =====================
            $this->make('minecraft_linked', 'Связь с игрой', 'Привязать Minecraft-аккаунт', 'emerald', self::SOCIAL, 'common', 15),
            $this->make('profile_complete', 'Полный профиль', 'Заполнить аватар, описание, обложку и соцсети', 'head', self::MISC, 'common', 15),
            $this->make('account_30d', 'Свой', 'Пробыть на APEX 30 дней', 'orb', self::MISC, 'common', 10),
            $this->make('account_365d', 'Старожил', 'Пробыть на APEX год', 'emerald', self::MISC, 'epic', 150),
            $this->make('bridge_ranked', 'Бриджер', 'Получить звание бриджера', 'pickaxe', self::TEST, 'rare', 40),
            $this->make('bridge_techniques_5', 'Мастер мостов', 'Подтвердить 5 видов бриджа', 'pickaxe', self::TEST, 'epic', 100),
        ];
    }

    private function make(
        string $code,
        string $name,
        string $description,
        string $icon,
        string $color,
        string $rarity,
        int $points,
    ): array {
        return compact('code', 'name', 'description', 'icon', 'color', 'rarity', 'points');
    }
}
