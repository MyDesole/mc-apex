<?php

namespace App\Domains\Players\Support;

/**
 * Правила раунда на арене питомцев — без базы и денег, только бой.
 *
 * Приёмы: атака (третья подряд — «серия»), защита, контратака (ловит
 * атаку или умение и возвращает урон), умение стихии и ультимейт, когда
 * накопилась ярость. Ярость копится от полученного урона. Умения и
 * ультимейты вешают статусы: поджог, кровотечение, страх, промокание,
 * оглушение. Бой идёт на случайной арене: её стихия бьёт сильнее, и у
 * каждой есть своя особенность. Иногда в раунде что-то случается само.
 *
 * Соперником управляет компьютер с характером своей стихии. Свой ход он
 * выбирает заранее, и мощные приёмы видно по нему: «готовит Пламя!».
 */
final class PetBattleEngine
{
    /** Умения стихий: множитель урона и особенности. */
    public const SKILLS = [
        'fire' => ['name' => 'Пламя', 'power' => 1.7, 'burn' => 35],
        'water' => ['name' => 'Волна', 'power' => 1.35, 'heal' => 0.12, 'cleanse' => true],
        'nature' => ['name' => 'Шипы', 'power' => 1.3, 'thorns' => 0.4],
        'shadow' => ['name' => 'Тень', 'power' => 1.45, 'pierce' => true, 'fear' => 50],
        'beast' => ['name' => 'Рывок', 'power' => 1.5, 'crit' => 30, 'bleed' => true],
    ];

    /** Ультимейты: открываются, когда ярость дошла до 100. */
    public const ULTIMATES = [
        'fire' => ['name' => 'Метеор', 'power' => 2.6, 'pierce' => true, 'about' => 'Огромный урон сквозь защиту и гарантированный поджог'],
        'water' => ['name' => 'Цунами', 'power' => 1.8, 'heal' => 0.25, 'about' => 'Урон, лечение и промокание: соперник медленнее'],
        'nature' => ['name' => 'Корни', 'power' => 1.6, 'about' => 'Урон и оглушение — соперник пропустит ход'],
        'shadow' => ['name' => 'Пожиратель душ', 'power' => 2.0, 'pierce' => true, 'drain' => 0.6, 'about' => 'Урон сквозь защиту, крадёт здоровье'],
        'beast' => ['name' => 'Ярость стаи', 'power' => 0.9, 'hits' => 3, 'about' => 'Три удара подряд, каждый может стать критом'],
    ];

    /** Арены: чья стихия сильнее (+20%) и особенность. */
    public const ARENAS = [
        'colosseum' => ['name' => 'Колизей', 'element' => null, 'about' => 'Публика ревёт: ярость копится в полтора раза быстрее'],
        'volcano' => ['name' => 'Вулкан', 'element' => 'fire', 'about' => 'Каждый четвёртый раунд извержение обжигает обоих'],
        'ocean' => ['name' => 'Океан глубин', 'element' => 'water', 'about' => 'Защита лечит вдвое сильнее'],
        'forest' => ['name' => 'Древний лес', 'element' => 'nature', 'about' => 'Шипы острее, а с кустов иногда падают ягоды'],
        'crypt' => ['name' => 'Склеп', 'element' => 'shadow', 'about' => 'Во тьме все критуют чаще'],
        'savanna' => ['name' => 'Саванна', 'element' => 'beast', 'about' => 'Серия из трёх атак бьёт ещё больнее'],
    ];

    /** Статусы: сколько раундов держатся. */
    public const STATUSES = [
        'burn' => ['name' => 'Поджог', 'rounds' => 3],
        'bleed' => ['name' => 'Кровотечение', 'rounds' => 3],
        'fear' => ['name' => 'Страх', 'rounds' => 2],
        'wet' => ['name' => 'Промок', 'rounds' => 2],
        'stun' => ['name' => 'Оглушён', 'rounds' => 1],
    ];

    /** Характер компьютера по стихии: шансы приёмов в процентах. */
    private const TEMPER = [
        'fire' => ['name' => 'Вспыльчивый', 'skill' => 80, 'counter' => 10, 'defend' => 20],
        'water' => ['name' => 'Осторожный', 'skill' => 60, 'counter' => 20, 'defend' => 50],
        'nature' => ['name' => 'Терпеливый', 'skill' => 65, 'counter' => 25, 'defend' => 35],
        'shadow' => ['name' => 'Хитрый', 'skill' => 60, 'counter' => 35, 'defend' => 25],
        'beast' => ['name' => 'Безрассудный', 'skill' => 75, 'counter' => 10, 'defend' => 10],
    ];

    /** Кто кого бьёт сильнее: огонь > природа > вода > огонь, тень > зверь. */
    private const STRONG = ['fire' => 'nature', 'nature' => 'water', 'water' => 'fire', 'shadow' => 'beast'];

    /** Плохие статусы, которые смывает Волна. */
    private const DEBUFFS = ['burn', 'bleed', 'fear', 'wet', 'stun'];

    public const MOVES = ['attack', 'defend', 'skill', 'counter', 'ultimate'];

    public const SKILL_COOLDOWN = 3;
    public const COUNTER_COOLDOWN = 2;
    public const RAGE_MAX = 100;
    public const EVENT_CHANCE = 12;
    /** На арене здоровья больше, чем в карточке: бой длиннее и успевает развернуться. */
    public const HP_FACTOR = 1.6;
    /** Сколько ярости даёт полученный урон: доля здоровья × это число. */
    public const RAGE_PER_HP = 100;

    /** Новый бой: случайная арена и первый замысел компьютера. */
    public function begin(array $me, array $foe): array
    {
        foreach ([&$me, &$foe] as &$fighter) {
            $fighter['hp'] = $fighter['max'] = (int) round($fighter['max'] * self::HP_FACTOR);
        }
        unset($fighter);

        $state = $this->normalize([
            'me' => $me,
            'foe' => $foe,
            'round' => 0,
            'log' => [],
            'arena' => array_rand(self::ARENAS),
        ]);

        $state['log'][] = ['who' => 'system', 'text' => 'Бой на арене «' . self::ARENAS[$state['arena']]['name'] . '». ' . self::ARENAS[$state['arena']]['about'] . '.'];
        $state['plan'] = $this->plan($state);

        return $state;
    }

    /** Поля, которых нет у боёв, начатых до обновления. */
    public function normalize(array $state): array
    {
        foreach (['me', 'foe'] as $side) {
            $state[$side] += [
                'rage' => 0,
                'ccd' => 0,
                'streak' => 0,
                'fx' => [],
                'stats' => ['dealt' => 0, 'crits' => 0, 'best' => 0, 'ults' => 0],
            ];
        }

        return $state + ['arena' => 'colosseum', 'last_moves' => [], 'plan' => null];
    }

    /** Можно ли сейчас сделать этот приём. null — можно, иначе причина. */
    public function forbidden(array $fighter, string $move): ?string
    {
        return match (true) {
            ! in_array($move, self::MOVES, true) => 'Такого приёма нет.',
            $move === 'skill' && $fighter['cd'] > 0 => 'Умение ещё перезаряжается.',
            $move === 'counter' && $fighter['ccd'] > 0 => 'Контратака ещё не готова.',
            $move === 'ultimate' && $fighter['rage'] < self::RAGE_MAX => 'Ярость ещё не накопилась.',
            default => null,
        };
    }

    /** Один раунд: ход игрока против заранее выбранного хода компьютера. */
    public function round(array $state, string $myMove): array
    {
        $state = $this->normalize($state);
        $foeMove = $state['plan'] ?? 'attack';

        // Замысел мог устареть (например, умение снова на перезарядке)
        if ($this->forbidden($state['foe'], $foeMove)) {
            $foeMove = 'attack';
        }

        $state['round']++;
        $moves = ['me' => $myMove, 'foe' => $foeMove];

        foreach ($moves as $who => $move) {
            if (($state[$who]['fx']['stun'] ?? 0) > 0) {
                $moves[$who] = 'stunned';
            }
        }

        $thorns = ['me' => 0.0, 'foe' => 0.0];

        foreach ($this->order($state) as $who) {
            $other = $this->other($who);

            if ($state[$who]['hp'] <= 0 || $state[$other]['hp'] <= 0) {
                break;
            }

            $this->act($state, $who, $moves, $thorns);
        }

        $this->endOfRound($state, $moves);

        $state['last_moves'][] = $myMove;
        $state['last_moves'] = array_slice($state['last_moves'], -3);
        $state['last'] = $moves;
        $state['log'] = array_slice($state['log'], -50);
        $state['plan'] = $this->plan($state);

        return $state;
    }

    /** Что компьютер готовит на следующий раунд — видно игроку. */
    public function tell(array $state): ?array
    {
        $foe = $state['foe'];

        if (($foe['fx']['stun'] ?? 0) > 0) {
            return ['kind' => 'stunned', 'text' => "{$foe['name']} оглушён и пропустит ход"];
        }

        return match ($state['plan'] ?? null) {
            'skill' => ['kind' => 'skill', 'text' => "{$foe['name']} готовит «" . self::SKILLS[$foe['element']]['name'] . '»!'],
            'ultimate' => ['kind' => 'ultimate', 'text' => "{$foe['name']} копит силу для «" . self::ULTIMATES[$foe['element']]['name'] . '»!'],
            'counter' => ['kind' => 'counter', 'text' => "{$foe['name']} выжидает и следит за каждым твоим движением"],
            default => null,
        };
    }

    public static function temper(string $element): string
    {
        return self::TEMPER[$element]['name'] ?? self::TEMPER['beast']['name'];
    }

    /** Компьютер выбирает ход заранее — по характеру и по тому, как ходит игрок. */
    public function plan(array $state): string
    {
        $foe = $state['foe'];
        $temper = self::TEMPER[$foe['element']] ?? self::TEMPER['beast'];

        // Кулдауны уменьшатся к следующему раунду только если приём не используется
        if ($foe['rage'] >= self::RAGE_MAX && random_int(1, 100) <= 90) {
            return 'ultimate';
        }

        if ($foe['cd'] === 0 && random_int(1, 100) <= $temper['skill']) {
            return 'skill';
        }

        // Игрок жмёт атаку подряд — компьютер это замечает
        $streak = array_slice($state['last_moves'] ?? [], -2);
        if ($foe['ccd'] === 0 && count($streak) === 2 && $streak === ['attack', 'attack'] && random_int(1, 100) <= $temper['counter']) {
            return 'counter';
        }

        // Игрок готовит ультимейт — самое время закрыться
        if ($state['me']['rage'] >= self::RAGE_MAX && random_int(1, 100) <= 50) {
            return 'defend';
        }

        if ($foe['hp'] / $foe['max'] < 0.35 && random_int(1, 100) <= $temper['defend']) {
            return 'defend';
        }

        return 'attack';
    }

    // ---- Внутренности раунда ----

    private function order(array $state): array
    {
        $spd = fn ($who) => $state[$who]['spd'] * (($state[$who]['fx']['wet'] ?? 0) > 0 ? 0.6 : 1);
        $me = $spd('me');
        $foe = $spd('foe');

        if ($me === $foe) {
            return random_int(0, 1) ? ['me', 'foe'] : ['foe', 'me'];
        }

        return $me > $foe ? ['me', 'foe'] : ['foe', 'me'];
    }

    private function act(array &$state, string $who, array $moves, array &$thorns): void
    {
        $other = $this->other($who);
        $move = $moves[$who];
        $name = $state[$who]['name'];

        if ($move !== 'attack') {
            $state[$who]['streak'] = 0;
        }

        switch ($move) {
            case 'stunned':
                $this->log($state, $who, 'stunned', "{$name} оглушён и пропускает ход.");

                return;

            case 'defend':
                $share = $state['arena'] === 'ocean' ? 0.12 : 0.06;
                $heal = $this->heal($state, $who, $share);
                $this->rage($state, $who, 15);
                $this->log($state, $who, 'defend', "{$name} закрывается и восстанавливает {$heal} здоровья.");

                return;

            case 'counter':
                $state[$who]['ccd'] = self::COUNTER_COOLDOWN + 1;
                if (! in_array($moves[$other], ['attack', 'skill'], true)) {
                    $this->log($state, $who, 'counter', "{$name} встаёт в контратаку, но ловит только воздух.");
                }

                return;

            case 'ultimate':
                $state[$who]['rage'] = 0;
                $state[$who]['stats']['ults']++;
                $this->ultimate($state, $who, $moves, $thorns);

                return;

            case 'skill':
                $state[$who]['cd'] = self::SKILL_COOLDOWN + 1;
                $this->skill($state, $who, $moves, $thorns);

                return;

            default:
                $state[$who]['streak']++;
                $series = $state[$who]['streak'] >= 3;
                $power = $series ? ($state['arena'] === 'savanna' ? 1.45 : 1.3) : 1.0;
                if ($series) {
                    $state[$who]['streak'] = 0;
                }

                $hit = $this->hit($state, $who, 'attack', $power, $moves, $thorns);
                $text = ($series ? "{$name} проводит серию — {$hit['damage']} урона" : "{$name} атакует — {$hit['damage']} урона") . $hit['note'];
                $this->log($state, $who, 'attack', $text . '.', $hit);
        }
    }

    private function skill(array &$state, string $who, array $moves, array &$thorns): void
    {
        $element = $state[$who]['element'];
        $skill = self::SKILLS[$element];
        $other = $this->other($who);
        $name = $state[$who]['name'];

        if (isset($skill['thorns'])) {
            $thorns[$who] = $state['arena'] === 'forest' ? 0.55 : $skill['thorns'];
        }

        $hit = $this->hit($state, $who, 'skill', $skill['power'], $moves, $thorns, $skill['pierce'] ?? false, $skill['crit'] ?? null);
        $text = "{$name} применяет «{$skill['name']}» — {$hit['damage']} урона" . $hit['note'];

        if (isset($skill['heal'])) {
            $text .= ' и лечится на ' . $this->heal($state, $who, $skill['heal']);
        }

        if (($skill['cleanse'] ?? false) && array_intersect(array_keys(array_filter($state[$who]['fx'])), self::DEBUFFS)) {
            $state[$who]['fx'] = [];
            $text .= ', смывая с себя все напасти';
        }

        if ($state[$other]['hp'] > 0 && ! $hit['countered']) {
            if (isset($skill['burn']) && random_int(1, 100) <= $skill['burn']) {
                $text .= $this->inflict($state, $other, 'burn');
            }
            if (isset($skill['fear']) && random_int(1, 100) <= $skill['fear']) {
                $text .= $this->inflict($state, $other, 'fear');
            }
            if (($skill['bleed'] ?? false) && $hit['crit']) {
                $text .= $this->inflict($state, $other, 'bleed');
            }
        }

        $this->log($state, $who, 'skill', $text . '.', $hit);
    }

    private function ultimate(array &$state, string $who, array $moves, array &$thorns): void
    {
        $element = $state[$who]['element'];
        $ult = self::ULTIMATES[$element];
        $other = $this->other($who);
        $name = $state[$who]['name'];

        $total = 0;
        $crit = false;
        $hits = $ult['hits'] ?? 1;

        for ($i = 0; $i < $hits && $state[$other]['hp'] > 0; $i++) {
            $hit = $this->hit($state, $who, 'ultimate', $ult['power'], $moves, $thorns, $ult['pierce'] ?? false, $element === 'beast' ? 30 : null);
            $total += $hit['damage'];
            $crit = $crit || $hit['crit'];
        }

        $text = "{$name} обрушивает «{$ult['name']}» — {$total} урона" . ($hits > 1 ? " за {$hits} удара" : '') . ($crit ? ', критический удар!' : '');

        if (isset($ult['heal'])) {
            $text .= ' и лечится на ' . $this->heal($state, $who, $ult['heal']);
        }

        if (isset($ult['drain'])) {
            $stolen = (int) round($total * $ult['drain']);
            $state[$who]['hp'] = min($state[$who]['max'], $state[$who]['hp'] + $stolen);
            $text .= ", крадёт {$stolen} здоровья";
        }

        if ($state[$other]['hp'] > 0) {
            $text .= match ($element) {
                'fire' => $this->inflict($state, $other, 'burn'),
                'water' => $this->inflict($state, $other, 'wet'),
                'nature' => $this->inflict($state, $other, 'stun'),
                default => '',
            };
        }

        $this->log($state, $who, 'ultimate', $text . '.', ['damage' => $total, 'crit' => $crit]);
    }

    /**
     * Удар по сопернику со всеми множителями. Контратака соперника ловит
     * атаку и умение (но не ультимейт), защита режет урон.
     *
     * @return array{damage: int, crit: bool, countered: bool, note: string}
     */
    private function hit(array &$state, string $who, string $kind, float $power, array $moves, array $thorns, bool $pierce = false, ?int $critChance = null): array
    {
        $other = $this->other($who);
        $attacker = $state[$who];
        $target = $state[$other];

        $atk = $attacker['atk'] * (($attacker['fx']['fear'] ?? 0) > 0 ? 0.75 : 1);
        $damage = $atk * $power * random_int(90, 110) / 100;
        $damage -= $pierce ? 0 : $target['def'] * 0.6;

        if ((self::STRONG[$attacker['element']] ?? null) === $target['element']) {
            $damage *= 1.25;
        } elseif ((self::STRONG[$target['element']] ?? null) === $attacker['element']) {
            $damage *= 0.8;
        }

        if (self::ARENAS[$state['arena']]['element'] === $attacker['element']) {
            $damage *= 1.2;
        }

        $chance = ($critChance ?? 8) + ($state['arena'] === 'crypt' ? 7 : 0);
        $crit = random_int(1, 100) <= $chance;
        if ($crit) {
            $damage *= 1.5;
        }

        $note = $crit ? ', критический удар!' : '';
        $countered = false;
        $reflect = 0;

        if ($moves[$other] === 'defend') {
            $damage *= $kind === 'ultimate' ? 0.5 : 0.4;
        } elseif ($moves[$other] === 'counter' && $kind !== 'ultimate') {
            $countered = true;
            $reflect = max(1, (int) round($damage * ($kind === 'attack' ? 0.7 : 0.4)));
            $damage *= $kind === 'attack' ? 0.4 : 0.7;
        }

        $damage = max(1, (int) round($damage));
        $state[$other]['hp'] = max(0, $state[$other]['hp'] - $damage);

        $state[$who]['stats']['dealt'] += $damage;
        $state[$who]['stats']['best'] = max($state[$who]['stats']['best'], $damage);
        $state[$who]['stats']['crits'] += $crit ? 1 : 0;

        $this->rage($state, $other, $damage / $target['max'] * self::RAGE_PER_HP);
        $this->rage($state, $who, 6);

        if ($countered && $state[$who]['hp'] > 0) {
            $state[$who]['hp'] = max(0, $state[$who]['hp'] - $reflect);
            $state[$other]['stats']['dealt'] += $reflect;
            $note .= ", {$target['name']} парирует и возвращает {$reflect}";
        }

        // Шипы соперника: часть урона возвращается
        if ($thorns[$other] > 0 && $state[$who]['hp'] > 0) {
            $back = max(1, (int) round($damage * $thorns[$other]));
            $state[$who]['hp'] = max(0, $state[$who]['hp'] - $back);
            $note .= ", шипы возвращают {$back}";
        }

        return ['damage' => $damage, 'crit' => $crit, 'countered' => $countered, 'note' => $note];
    }

    /** Конец раунда: статусы, особенность арены, случайное событие, перезарядки. */
    private function endOfRound(array &$state, array $moves): void
    {
        foreach (['me', 'foe'] as $who) {
            if ($state[$who]['hp'] <= 0) {
                continue;
            }

            foreach (['burn' => [0.04, 'горит'], 'bleed' => [0.03, 'истекает кровью']] as $fx => [$share, $verb]) {
                if (($state[$who]['fx'][$fx] ?? 0) > 0 && $state[$who]['hp'] > 0) {
                    $tick = max(1, (int) round($state[$who]['max'] * $share));
                    $state[$who]['hp'] = max(0, $state[$who]['hp'] - $tick);
                    $this->log($state, 'system', 'status', "{$state[$who]['name']} {$verb}: −{$tick}.", ['target' => $who, 'damage' => $tick]);
                }
            }
        }

        if ($state['arena'] === 'volcano' && $state['round'] % 4 === 0) {
            $this->both($state, 0.04, 'Извержение! Лава обжигает обоих');
        }

        if ($state['me']['hp'] > 0 && $state['foe']['hp'] > 0 && random_int(1, 100) <= self::EVENT_CHANCE) {
            $this->event($state);
        }

        foreach (['me', 'foe'] as $who) {
            foreach ($state[$who]['fx'] as $fx => $left) {
                $state[$who]['fx'][$fx] = max(0, $left - 1);
            }
            $state[$who]['fx'] = array_filter($state[$who]['fx']);

            // Перезарядки: приём, применённый в раунде, ставит счётчик на 1 больше
            $state[$who]['cd'] = max(0, $state[$who]['cd'] - 1);
            $state[$who]['ccd'] = max(0, $state[$who]['ccd'] - 1);
        }
    }

    /** Случайное событие раунда — на любой арене. */
    private function event(array &$state): void
    {
        $roll = random_int(1, $state['arena'] === 'forest' ? 4 : 3);
        $lucky = random_int(0, 1) ? 'me' : 'foe';

        match ($roll) {
            1 => $this->log($state, 'event', 'event', "Зритель кидает на арену яблоко — {$state[$lucky]['name']} ловит его и восстанавливает " . $this->heal($state, $lucky, 0.08) . '.', ['target' => $lucky]),
            2 => $this->both($state, 0.05, 'Молния бьёт в арену и задевает обоих'),
            3 => $this->underdog($state),
            default => $this->log($state, 'event', 'event', "С куста падают ягоды — {$state[$lucky]['name']} подкрепляется на " . $this->heal($state, $lucky, 0.1) . '.', ['target' => $lucky]),
        };
    }

    /** Кот-судья подбадривает отстающего: ярость к ультимейту. */
    private function underdog(array &$state): void
    {
        $who = $state['me']['hp'] / $state['me']['max'] <= $state['foe']['hp'] / $state['foe']['max'] ? 'me' : 'foe';
        $this->rage($state, $who, 25);
        $this->log($state, 'event', 'event', "Кот-судья подмигивает {$state[$who]['name']}: +25 ярости.", ['target' => $who]);
    }

    private function both(array &$state, float $share, string $text): void
    {
        $parts = [];
        foreach (['me', 'foe'] as $who) {
            $tick = max(1, (int) round($state[$who]['max'] * $share));
            $state[$who]['hp'] = max(0, $state[$who]['hp'] - $tick);
            $parts[] = "{$state[$who]['name']} −{$tick}";
        }

        $this->log($state, 'event', 'event', $text . ': ' . implode(', ', $parts) . '.', ['target' => 'both']);
    }

    private function inflict(array &$state, string $who, string $fx): string
    {
        // Оглушение и промокание работают со следующего раунда: конец текущего их не съедает
        $extra = in_array($fx, ['stun', 'wet'], true) ? 1 : 0;
        $state[$who]['fx'][$fx] = max($state[$who]['fx'][$fx] ?? 0, self::STATUSES[$fx]['rounds'] + $extra);

        return match ($fx) {
            'burn' => ', поджигая соперника',
            'bleed' => ', рана кровоточит',
            'fear' => ', соперник в страхе',
            'wet' => ', соперник промок и замедлился',
            'stun' => ', корни сковывают соперника',
        };
    }

    private function heal(array &$state, string $who, float $share): int
    {
        $before = $state[$who]['hp'];
        $state[$who]['hp'] = min($state[$who]['max'], $before + (int) round($state[$who]['max'] * $share));

        return $state[$who]['hp'] - $before;
    }

    private function rage(array &$state, string $who, float $amount): void
    {
        $amount *= $state['arena'] === 'colosseum' ? 1.5 : 1;
        $state[$who]['rage'] = min(self::RAGE_MAX, (int) round($state[$who]['rage'] + $amount));
    }

    private function log(array &$state, string $who, string $move, string $text, array $extra = []): void
    {
        // «критический удар!.» → «критический удар!»
        $entry = ['who' => $who, 'move' => $move, 'text' => str_replace('!.', '!', $text)];

        if (isset($extra['damage'])) {
            $entry['damage'] = $extra['damage'];
            $entry['crit'] = $extra['crit'] ?? false;
        }

        if (isset($extra['target'])) {
            $entry['target'] = $extra['target'];
        }

        $state['log'][] = $entry;
    }

    private function other(string $who): string
    {
        return $who === 'me' ? 'foe' : 'me';
    }

}
