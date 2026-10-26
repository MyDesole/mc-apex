<?php

namespace App\Domains\Clan\Support;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanCheckin;

/**
 * Уровень клана и его бонусы.
 *
 * Опыт: отметки участников и победы в КВ. Уровень хранится в clans.level,
 * чтобы список кланов мог считать свободные места одним запросом;
 * пересчитывается после отметки и после итога войны.
 */
final class ClanLevel
{
    public const XP_PER_CHECKIN = 10;
    public const XP_PER_WIN = 100;

    // Каждый уровень сверх первого добавляет места в клане
    public const SLOTS_PER_LEVEL = 2;

    // Скидка на клановые предметы при оплате из казны: уровень => процент
    private const DISCOUNTS = [3 => 5, 5 => 10, 7 => 15, 10 => 20];

    /** Опыт, нужный для уровня: 0, 200, 600, 1200, 2000… */
    public static function xpFor(int $level): int
    {
        return 100 * $level * ($level - 1);
    }

    public static function levelFor(int $xp): int
    {
        $level = 1;
        while (self::xpFor($level + 1) <= $xp) {
            $level++;
        }

        return $level;
    }

    public static function bonusSlots(int $level): int
    {
        return self::SLOTS_PER_LEVEL * max(0, $level - 1);
    }

    /** Сколько участников вмещает клан с учётом уровня. */
    public static function memberCap(Clan $clan): int
    {
        return (int) $clan->max_members + self::bonusSlots((int) ($clan->level ?: 1));
    }

    /** ApexCoin в казну за отметку участника. */
    public static function checkinCoins(int $level): int
    {
        return max(1, $level);
    }

    /** Скидка в процентах на клановые предметы из казны. */
    public static function treasuryDiscount(int $level): int
    {
        $discount = 0;
        foreach (self::DISCOUNTS as $from => $percent) {
            if ($level >= $from) {
                $discount = $percent;
            }
        }

        return $discount;
    }

    public static function discounted(int $price, int $level): int
    {
        $discount = self::treasuryDiscount($level);

        return $discount > 0 ? (int) ceil($price * (100 - $discount) / 100) : $price;
    }

    /** Пересчитать опыт и уровень клана по фактическим данным. */
    public static function sync(Clan $clan): Clan
    {
        $xp = ClanCheckin::where('clan_id', $clan->id)->count() * self::XP_PER_CHECKIN
            + (int) $clan->wins * self::XP_PER_WIN;

        $level = self::levelFor($xp);

        if ((int) $clan->xp !== $xp || (int) $clan->level !== $level) {
            $clan->forceFill(['xp' => $xp, 'level' => $level])->saveQuietly();
        }

        return $clan;
    }

    public static function progress(Clan $clan): array
    {
        $level = (int) ($clan->level ?: 1);

        return [
            'level' => $level,
            'xp' => (int) $clan->xp,
            'current' => self::xpFor($level),
            'next' => self::xpFor($level + 1),
            'perks' => self::perks($level),
            'bonuses' => [
                'slots' => self::bonusSlots($level),
                'member_cap' => self::memberCap($clan),
                'checkin_coins' => self::checkinCoins($level),
                'treasury_discount' => self::treasuryDiscount($level),
            ],
        ];
    }

    /** Лестница бонусов: что уже открыто и что впереди. */
    public static function perks(int $level): array
    {
        $perks = [];

        foreach ([2, 3, 4, 5, 7, 10] as $at) {
            $parts = ['+' . self::bonusSlots($at) . ' мест в клане', self::checkinCoins($at) . ' ApexCoin в казну за отметку'];

            if (isset(self::DISCOUNTS[$at])) {
                $parts[] = '−' . self::DISCOUNTS[$at] . '% на клановые предметы из казны';
            }

            $perks[] = [
                'level' => $at,
                'text' => implode(' · ', $parts),
                'unlocked' => $level >= $at,
            ];
        }

        return $perks;
    }
}
