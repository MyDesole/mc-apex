<?php

namespace App\Domains\Players\Support;

/**
 * Древо талантов питомца: три ветки по восемь талантов. Очко даёт каждый
 * уровень со второго (на 100 уровне их 99), а на всё древо нужно 108, так что
 * выбирать приходится. Таланты дают проценты к характеристикам и боевые
 * свойства тех же ключей, что у экипировки (`Dungeons::PERKS`), плюс
 * модификаторы умений (`MODS`).
 */
class PetTalents
{
    /** Очков на уровень, начиная со второго. */
    public const POINT_PER_LEVEL = 1;

    /** Сколько очков в ветке надо вложить, чтобы открылся ярус. */
    public const TIER_GATE = [1 => 0, 2 => 5, 3 => 12, 4 => 20];

    /** Сброс талантов питомца, ApexCoin. */
    public const RESPEC_COST = 5000;

    /** Свойства, общие с экипировкой: их считает DungeonBattle. */
    public const PERK_KEYS = ['vamp', 'crit', 'pierce', 'execute', 'opener', 'regen', 'thorns', 'bulwark', 'dodge', 'phoenix', 'stun', 'rage'];

    /** Модификаторы умений и характеристик (не перки). */
    public const MOD_KEYS = ['skill_power', 'ult_power', 'heal_power', 'skill_cd', 'element_power', 'sig'];

    public const STAT_KEYS = ['atk_pct', 'def_pct', 'hp_pct', 'spd'];

    public const BRANCHES = [
        'fury' => ['name' => 'Ярость', 'text' => 'Урон, криты и добивание', 'color' => '#ef4444', 'sprite' => 'gi-greatsword-blood'],
        'bastion' => ['name' => 'Бастион', 'text' => 'Здоровье, броня и стойкость', 'color' => '#60a5fa', 'sprite' => 'gi-shield-steel'],
        'spirit' => ['name' => 'Дух', 'text' => 'Умения, ультимейты и стихия', 'color' => '#c084fc', 'sprite' => 'gi-orb-void'],
    ];

    /**
     * Таланты: ветка, ярус, максимум рангов, эффект за ранг.
     *
     * @var array<string, array{branch: string, tier: int, max: int, name: string, text: string, effect: array<string, int>, sprite: string}>
     */
    public const TALENTS = [
        'w_might' => ['branch' => 'fury', 'tier' => 1, 'max' => 5, 'name' => 'Мощь', 'text' => '+2% к атаке за ранг', 'effect' => ['atk_pct' => 2], 'sprite' => 'gi-sword-iron'],
        'w_precision' => ['branch' => 'fury', 'tier' => 1, 'max' => 5, 'name' => 'Меткость', 'text' => '+2% к шансу крита за ранг', 'effect' => ['crit' => 2], 'sprite' => 'gi-eye-sun'],
        'w_pierce' => ['branch' => 'fury', 'tier' => 2, 'max' => 5, 'name' => 'Бронебой', 'text' => 'Игнорирует 4% защиты врага за ранг', 'effect' => ['pierce' => 4], 'sprite' => 'gi-spear-steel'],
        'w_opener' => ['branch' => 'fury', 'tier' => 2, 'max' => 3, 'name' => 'Натиск', 'text' => '+10% урона в первом раунде за ранг', 'effect' => ['opener' => 10], 'sprite' => 'gi-dagger-fire'],
        'w_execute' => ['branch' => 'fury', 'tier' => 3, 'max' => 5, 'name' => 'Добивание', 'text' => '+8% урона по врагу ниже 30% здоровья за ранг', 'effect' => ['execute' => 8], 'sprite' => 'gi-axe-blood'],
        'w_rage' => ['branch' => 'fury', 'tier' => 3, 'max' => 5, 'name' => 'Жажда боя', 'text' => '+3 ярости за каждый удар за ранг', 'effect' => ['rage' => 3], 'sprite' => 'gi-gauntlets-fire'],
        'w_blood' => ['branch' => 'fury', 'tier' => 4, 'max' => 5, 'name' => 'Кровожадность', 'text' => 'Лечит на 3% нанесённого урона за ранг', 'effect' => ['vamp' => 3], 'sprite' => 'gi-skull-blood'],
        'w_crush' => ['branch' => 'fury', 'tier' => 4, 'max' => 5, 'name' => 'Сокрушитель', 'text' => '+6% к урону умения за ранг', 'effect' => ['skill_power' => 6], 'sprite' => 'gi-hammer-fire'],

        'g_hide' => ['branch' => 'bastion', 'tier' => 1, 'max' => 5, 'name' => 'Крепкая шкура', 'text' => '+2% к здоровью за ранг', 'effect' => ['hp_pct' => 2], 'sprite' => 'gi-vest-iron'],
        'g_plate' => ['branch' => 'bastion', 'tier' => 1, 'max' => 5, 'name' => 'Броня', 'text' => '+3% к защите за ранг', 'effect' => ['def_pct' => 3], 'sprite' => 'gi-chest-steel'],
        'g_bulwark' => ['branch' => 'bastion', 'tier' => 2, 'max' => 5, 'name' => 'Стойкость', 'text' => '−2% получаемого урона за ранг', 'effect' => ['bulwark' => 2], 'sprite' => 'gi-shield-iron'],
        'g_regen' => ['branch' => 'bastion', 'tier' => 2, 'max' => 3, 'name' => 'Закалка', 'text' => 'Лечит 1% здоровья в конце раунда за ранг', 'effect' => ['regen' => 1], 'sprite' => 'gi-vial-toxic'],
        'g_thorns' => ['branch' => 'bastion', 'tier' => 3, 'max' => 5, 'name' => 'Шипастый', 'text' => 'Возвращает врагу 4% урона за ранг', 'effect' => ['thorns' => 4], 'sprite' => 'gi-mace-iron'],
        'g_dodge' => ['branch' => 'bastion', 'tier' => 3, 'max' => 5, 'name' => 'Увёртливость', 'text' => '+2% шанса уклониться за ранг', 'effect' => ['dodge' => 2], 'sprite' => 'gi-boots-bone'],
        'g_phoenix' => ['branch' => 'bastion', 'tier' => 4, 'max' => 3, 'name' => 'Бессмертный', 'text' => 'Раз за бой воскресает с 12% здоровья за ранг', 'effect' => ['phoenix' => 12], 'sprite' => 'gi-star-fire'],
        'g_fort' => ['branch' => 'bastion', 'tier' => 4, 'max' => 3, 'name' => 'Оплот', 'text' => '+4% к здоровью за ранг', 'effect' => ['hp_pct' => 4], 'sprite' => 'gi-pauldrons-gold'],

        'm_quick' => ['branch' => 'spirit', 'tier' => 1, 'max' => 5, 'name' => 'Проворство', 'text' => '+1 к скорости за ранг', 'effect' => ['spd' => 1], 'sprite' => 'gi-boots-sea'],
        'm_focus' => ['branch' => 'spirit', 'tier' => 1, 'max' => 5, 'name' => 'Концентрация', 'text' => '+4% к урону умения за ранг', 'effect' => ['skill_power' => 4], 'sprite' => 'gi-wand-void'],
        'm_stun' => ['branch' => 'spirit', 'tier' => 2, 'max' => 5, 'name' => 'Оглушающий удар', 'text' => '+4% шанса оглушить за ранг', 'effect' => ['stun' => 4], 'sprite' => 'gi-hammer-ice'],
        'm_cd' => ['branch' => 'spirit', 'tier' => 2, 'max' => 1, 'name' => 'Беглость', 'text' => 'Умение перезаряжается на раунд быстрее', 'effect' => ['skill_cd' => 1], 'sprite' => 'gi-key-ice'],
        'm_ult' => ['branch' => 'spirit', 'tier' => 3, 'max' => 5, 'name' => 'Мощь ультимейта', 'text' => '+8% к урону ультимейта за ранг', 'effect' => ['ult_power' => 8], 'sprite' => 'gi-crown-void'],
        'm_heal' => ['branch' => 'spirit', 'tier' => 3, 'max' => 5, 'name' => 'Целитель', 'text' => '+10% к лечению умений за ранг', 'effect' => ['heal_power' => 10], 'sprite' => 'gi-amulet-sea'],
        'm_elem' => ['branch' => 'spirit', 'tier' => 4, 'max' => 5, 'name' => 'Мастер стихии', 'text' => '+4% урона, когда стихия сильнее вражеской, за ранг', 'effect' => ['element_power' => 4], 'sprite' => 'gi-orb-crystal'],
        'm_sig' => ['branch' => 'spirit', 'tier' => 4, 'max' => 5, 'name' => 'Дар стихии', 'text' => '+12% к побочному эффекту умений стихии за ранг', 'effect' => ['sig' => 12], 'sprite' => 'gi-rune-sun'],
    ];

    /** Что именно усиливает «Дар стихии» у каждой стихии. */
    public const SIGNATURE = [
        'fire' => 'шанс поджога',
        'water' => 'лечение умений',
        'nature' => 'шанс оглушить',
        'shadow' => 'шанс страха',
        'beast' => 'бонус к криту и ярость стаи',
        'light' => 'лечение и страх света',
        'wind' => 'крит, страх и оглушение ультимейта',
        'earth' => 'шанс оглушить',
    ];

    /** Очков за уровни. */
    public static function totalPoints(int $level): int
    {
        return max(0, $level - 1) * self::POINT_PER_LEVEL;
    }

    /**
     * Суммарные эффекты по рангам: ключ эффекта => значение.
     *
     * @param  array<string, int>  $ranks  ключ таланта => ранг
     * @return array<string, int>
     */
    public static function effects(array $ranks): array
    {
        $sum = [];

        foreach ($ranks as $key => $rank) {
            $talent = self::TALENTS[$key] ?? null;

            if (! $talent || $rank <= 0) {
                continue;
            }

            foreach ($talent['effect'] as $effect => $value) {
                $sum[$effect] = ($sum[$effect] ?? 0) + $value * min($rank, $talent['max']);
            }
        }

        return $sum;
    }

    /** Очков вложено в ветку. */
    public static function spentIn(array $ranks, string $branch): int
    {
        $spent = 0;

        foreach ($ranks as $key => $rank) {
            if ((self::TALENTS[$key]['branch'] ?? null) === $branch) {
                $spent += (int) $rank;
            }
        }

        return $spent;
    }

    /** Характеристики с процентами талантов: на вход базовые, на выход итоговые. */
    public static function applyStats(array $stats, array $effects): array
    {
        $stats['atk'] = (int) round($stats['atk'] * (1 + ($effects['atk_pct'] ?? 0) / 100));
        $stats['def'] = (int) round($stats['def'] * (1 + ($effects['def_pct'] ?? 0) / 100));
        $stats['hp'] = (int) round($stats['hp'] * (1 + ($effects['hp_pct'] ?? 0) / 100));
        $stats['spd'] += (int) ($effects['spd'] ?? 0);

        return $stats;
    }
}
