<?php

namespace App\Domains\Clan\Support;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanMember;
use Illuminate\Http\Request;

/**
 * Контекст клана для запроса.
 *
 * Часть эндпоинтов объявлена вне группы с middleware `clan.member`, поэтому
 * атрибут `clan` в запросе может отсутствовать — раньше это давало 404 вместо
 * внятного отказа. Здесь единая точка: либо клан из middleware, либо из
 * членства текущего пользователя.
 */
class ClanContext
{
    /**
     * Клан, в котором состоит пользователь. Атрибут middleware имеет приоритет.
     */
    public static function clan(Request $request): Clan
    {
        $clan = $request->attributes->get('clan');

        if ($clan instanceof Clan) {
            return $clan;
        }

        $membership = self::membership($request);

        abort_if(! $membership, 403, 'Вы не в клане.');

        return $membership->clan;
    }

    /**
     * Членство пользователя в клане.
     */
    public static function membership(Request $request): ?ClanMember
    {
        $membership = $request->attributes->get('clan_membership');

        if ($membership instanceof ClanMember) {
            return $membership;
        }

        return $request->user()?->clanMember;
    }
}
