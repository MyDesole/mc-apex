<?php

namespace App\Domains\Players\Support;

/**
 * Пошаговый бой подземелья: отряд против моба или босса.
 *
 * Правила и состояние — без базы и денег, как PetBattleEngine на арене.
 * Раунд: каждому живому питомцу выбирают приём, потом все ходят по
 * скорости. Приёмы — атака, умение стихии, защита и ультимейт (нужна
 * ярость 100).
 *
 * Стихия питомца решает, чем он полезен отряду:
 *   огонь   — урон и поджог;
 *   вода    — лечит отряд;
 *   природа — оглушает и подлечивает;
 *   тень    — крадёт здоровье и раздаёт своим;
 *   зверь   — баффает атаку всему отряду.
 * Поэтому отряд собирают под противника, а не «кого сильнее».
 *
 * Связки стихий дают супер-контру: пара стихий в отряде бьёт определённую
 * стихию врага на 35% сильнее.
 */
final class DungeonBattle
{
    public const MOVES = ['attack', 'skill', 'defend', 'ultimate'];

    public const MOVE_NAMES = [
        'attack' => 'Атака',
        'skill' => 'Умение',
        'defend' => 'Защита',
        'ultimate' => 'Ультимейт',
    ];

    public const CRIT_CHANCE = 10;
    public const CRIT_MULT = 1.5;
    public const SKILL_COOLDOWN = 3;
    public const RAGE_MAX = 100;
    public const HP_FACTOR = 1.6;

    /** Стихии: подпись и цвет для интерфейса. */
    public const ELEMENTS = [
        'fire' => ['name' => 'Огонь', 'color' => '#fb923c'],
        'water' => ['name' => 'Вода', 'color' => '#38bdf8'],
        'nature' => ['name' => 'Природа', 'color' => '#4ade80'],
        'shadow' => ['name' => 'Тень', 'color' => '#a78bfa'],
        'beast' => ['name' => 'Зверь', 'color' => '#fbbf24'],
    ];

    /**
     * Способности стихий. Умение — обычный приём, ультимейт — за ярость.
     *
     * Поля эффектов: power — множитель урона; burn — поджог; fear — страх;
     * stun / stun_chance — оглушение; heal_lowest — лечит слабейшего;
     * heal_team — лечит весь отряд; cleanse — снимает статусы; drain_team —
     * раздаёт отряду долю украденного; team_atk — бафф атаки отряду на
     * rounds раундов.
     */
    public const ABILITIES = [
        'fire' => [
            'skill' => ['name' => 'Пламя', 'power' => 1.7, 'burn' => 35],
            'ultimate' => ['name' => 'Метеор', 'power' => 2.6, 'burn' => 100],
        ],
        'water' => [
            'skill' => ['name' => 'Волна', 'power' => 1.3, 'heal_lowest' => 0.12],
            'ultimate' => ['name' => 'Цунами', 'power' => 0.6, 'heal_team' => 0.18, 'cleanse' => true],
        ],
        'nature' => [
            'skill' => ['name' => 'Шипы', 'power' => 1.4, 'stun_chance' => 40],
            'ultimate' => ['name' => 'Корни', 'power' => 1.6, 'stun' => 100, 'heal_team' => 0.06],
        ],
        'shadow' => [
            'skill' => ['name' => 'Тень', 'power' => 1.45, 'fear' => 50],
            'ultimate' => ['name' => 'Пожиратель душ', 'power' => 2.0, 'drain_team' => 0.4],
        ],
        'beast' => [
            'skill' => ['name' => 'Рывок', 'power' => 1.5, 'crit_bonus' => 20],
            'ultimate' => ['name' => 'Ярость стаи', 'power' => 1.2, 'team_atk' => 1.25, 'rounds' => 2],
        ],
    ];

    /**
     * Связки: пара стихий в отряде даёт супер-контру против третьей.
     *
     * Пара считается, если в отряде есть два живых питомца этих стихий.
     */
    public const COMBOS = [
        'steam' => [
            'name' => 'Пар',
            'pair' => ['water', 'fire'],
            'counters' => 'nature',
            'power' => 1.35,
            'text' => 'Вода и огонь дают пар — он выжигает природу.',
        ],
        'ash' => [
            'name' => 'Пепел',
            'pair' => ['fire', 'nature'],
            'counters' => 'shadow',
            'power' => 1.35,
            'text' => 'Пепел от сгоревшей листвы въедается в тень.',
        ],
        'blight' => [
            'name' => 'Тлен',
            'pair' => ['nature', 'shadow'],
            'counters' => 'beast',
            'power' => 1.35,
            'text' => 'Тлен отравляет зверя и сбивает его с ног.',
        ],
        'pack' => [
            'name' => 'Стая',
            'pair' => ['shadow', 'beast'],
            'counters' => 'water',
            'power' => 1.35,
            'text' => 'Стая теней выпивает воду.',
        ],
        'tide' => [
            'name' => 'Прилив',
            'pair' => ['beast', 'water'],
            'counters' => 'fire',
            'power' => 1.35,
            'text' => 'Зверь поднимает волну и гасит огонь.',
        ],
        'dew' => [
            'name' => 'Роса',
            'pair' => ['water', 'nature'],
            'counters' => 'fire',
            'power' => 1.35,
            'text' => 'Сырая зелень не даёт пламени разгореться.',
        ],
        'char' => [
            'name' => 'Гарь',
            'pair' => ['fire', 'shadow'],
            'counters' => 'nature',
            'power' => 1.35,
            'text' => 'Гарь и дым выжигают всё живое.',
        ],
        'hunt' => [
            'name' => 'Охота',
            'pair' => ['fire', 'beast'],
            'counters' => 'water',
            'power' => 1.35,
            'text' => 'Загнанный зверь и огонь не оставляют воде шанса.',
        ],
        'abyss' => [
            'name' => 'Глубина',
            'pair' => ['water', 'shadow'],
            'counters' => 'shadow',
            'power' => 1.35,
            'text' => 'Тёмная вода топит любую тень.',
        ],
        'thicket' => [
            'name' => 'Чаща',
            'pair' => ['nature', 'beast'],
            'counters' => 'beast',
            'power' => 1.35,
            'text' => 'Чаща путает зверю ноги и сбивает его с хода.',
        ],
    ];

    public const STATUSES = [
        'burn' => ['name' => 'Поджог', 'rounds' => 3, 'share' => 0.04],
        'stun' => ['name' => 'Оглушён', 'rounds' => 1, 'share' => 0],
        'fear' => ['name' => 'Страх', 'rounds' => 2, 'share' => 0],
    ];

    /** Стихия бьёт сильнее по этой. */
    private const STRONG = ['fire' => 'nature', 'nature' => 'water', 'water' => 'fire', 'shadow' => 'beast', 'beast' => 'shadow'];

    /* ==================== Начало боя ==================== */

    public function begin(array $members, array $enemy): array
    {
        $units = [];

        foreach ($members as $member) {
            $units[] = [
                'id' => (int) $member['inventory_id'],
                'name' => $member['name'],
                'species' => $member['species'],
                'traits' => $member['traits'] ?? null,
                'gear' => $member['gear'] ?? [],
                'element' => $member['element'],
                'hp' => max(1, (int) ($member['hp'] ?? $member['max'])),
                'max' => (int) $member['max'],
                'atk' => (int) $member['atk'],
                'def' => (int) $member['def'],
                'spd' => (int) $member['spd'],
                'rage' => 0,
                'cd' => 0,
                'guard' => false,
                'statuses' => [],
            ];
        }

        $foe = [
            'name' => $enemy['name'],
            'sprite' => $enemy['sprite'],
            'is_boss' => (bool) $enemy['is_boss'],
            'element' => $enemy['element'] ?? 'beast',
            'trait' => $enemy['trait'],
            'trait_name' => $enemy['trait_name'],
            'trait_text' => $enemy['trait_text'],
            'hp' => (int) $enemy['hp'],
            'max' => (int) $enemy['hp'],
            'atk' => (int) $enemy['atk'],
            'def' => (int) $enemy['def'],
            'spd' => (int) ($enemy['spd'] ?? 12),
            'cd' => 0,
            'guard' => false,
            'statuses' => [],
        ];

        $state = [
            'round' => 1,
            'foe' => $foe,
            'units' => $units,
            'team' => ['atk' => 1.0, 'rounds' => 0],
            'combo' => $this->combo($units, $foe['element']),
            'status' => 'active',
            'log' => [[
                'kind' => 'start',
                'text' => ($foe['is_boss'] ? 'Босс «' . $foe['name'] . '»' : $foe['name'])
                    . ' (' . self::ELEMENTS[$foe['element']]['name'] . ') преграждает путь.',
            ]],
        ];

        if ($state['combo']) {
            $combo = $state['combo'];
            $state['log'][] = [
                'kind' => 'combo',
                'text' => "Связка «{$combo['name']}»: {$combo['text']}"
                    . ($combo['counters'] === $foe['element'] ? ' Противник уязвим!' : ''),
            ];
        }

        $state['order'] = $this->order($state);

        return $state;
    }

    /**
     * Активная связка отряда: пара стихий живых питомцев.
     *
     * Пар в отряде из трёх питомцев может быть три, поэтому если известна
     * стихия врага, выбираем ту связку, которая её контрит: иначе отряд
     * получал бы случайную.
     */
    public function combo(array $units, ?string $foeElement = null): ?array
    {
        $elements = [];

        foreach ($units as $unit) {
            if (($unit['hp'] ?? 1) > 0) {
                $elements[$unit['element']] = true;
            }
        }

        $matched = [];

        foreach (self::COMBOS as $key => $combo) {
            [$a, $b] = $combo['pair'];

            if (isset($elements[$a], $elements[$b])) {
                $matched[] = ['key' => $key] + $combo;
            }
        }

        if (! $matched) {
            return null;
        }

        if ($foeElement !== null) {
            foreach ($matched as $combo) {
                if ($combo['counters'] === $foeElement) {
                    return $combo;
                }
            }
        }

        return $matched[0];
    }

    /* ==================== Порядок хода ==================== */

    public function order(array $state): array
    {
        $rows = [];

        foreach ($state['units'] as $unit) {
            if ($unit['hp'] > 0) {
                $rows[] = ['side' => 'me', 'key' => (string) $unit['id'], 'spd' => $unit['spd'] + random_int(0, 3)];
            }
        }

        if ($state['foe']['hp'] > 0) {
            $rows[] = ['side' => 'foe', 'key' => 'foe', 'spd' => $state['foe']['spd'] + random_int(0, 3)];
        }

        usort($rows, fn (array $a, array $b) => $b['spd'] <=> $a['spd']);

        return $rows;
    }

    /* ==================== Раунд ==================== */

    public function resolveRound(array $state, array $moves): array
    {
        $state['log'] = [];

        foreach ($state['units'] as $index => $unit) {
            $state['units'][$index]['guard'] = false;
        }

        $state['foe']['guard'] = false;

        $order = $state['order'] ?: $this->order($state);

        foreach ($order as $slot) {
            if ($state['status'] !== 'active') {
                break;
            }

            if ($slot['side'] === 'foe') {
                if ($state['foe']['hp'] > 0) {
                    $this->foeTurn($state);
                }

                continue;
            }

            $index = $this->indexOf($state, (int) $slot['key']);

            if ($index === null || $state['units'][$index]['hp'] <= 0) {
                continue;
            }

            $this->unitTurn($state, $index, $moves[$slot['key']] ?? 'attack');
        }

        if ($state['status'] === 'active') {
            $this->tickStatuses($state);

            if ($state['status'] === 'active') {
                $state['round']++;

                foreach ($state['units'] as $index => $unit) {
                    if ($unit['cd'] > 0) {
                        $state['units'][$index]['cd'] = $unit['cd'] - 1;
                    }
                }

                if ($state['foe']['cd'] > 0) {
                    $state['foe']['cd']--;
                }

                if (($state['team']['rounds'] ?? 0) > 0) {
                    $state['team']['rounds']--;

                    if ($state['team']['rounds'] === 0) {
                        $state['team']['atk'] = 1.0;
                    }
                }

                $state['order'] = $this->order($state);
            }
        }

        return $state;
    }

    private function unitTurn(array &$state, int $index, string $move): void
    {
        $unit = &$state['units'][$index];

        if (($unit['statuses']['stun'] ?? 0) > 0) {
            $this->log($state, 'skip', "{$unit['name']} оглушён и пропускает ход.");

            return;
        }

        if (! in_array($move, self::MOVES, true)) {
            $move = 'attack';
        }

        if ($move === 'skill' && $unit['cd'] > 0) {
            $move = 'attack';
        }

        if ($move === 'ultimate' && $unit['rage'] < self::RAGE_MAX) {
            $move = 'attack';
        }

        if ($move === 'defend') {
            $unit['guard'] = true;
            $unit['rage'] = min(self::RAGE_MAX, $unit['rage'] + 15);

            $heal = (int) round($unit['max'] * 0.06);
            $unit['hp'] = min($unit['max'], $unit['hp'] + $heal);

            $this->log($state, 'defend', "{$unit['name']} закрывается — {$heal} здоровья, ярость {$unit['rage']}.", [
                'who' => $unit['name'],
                'heal' => $heal,
            ]);

            return;
        }

        $ability = self::ABILITIES[$unit['element']] ?? self::ABILITIES['beast'];
        $chosen = $move === 'ultimate' ? $ability['ultimate'] : ($move === 'skill' ? $ability['skill'] : null);

        $power = (float) ($chosen['power'] ?? 1.0);
        $label = $chosen['name'] ?? 'атакует';

        if ($move === 'skill') {
            $unit['cd'] = self::SKILL_COOLDOWN + 1;
        }

        if ($move === 'ultimate') {
            $unit['rage'] = 0;
        }

        $mult = $this->comboMultiplier($state);

        $hit = $this->damage($state, $unit, $state['foe'], $power, $mult, (int) ($chosen['crit_bonus'] ?? 0));

        $state['foe']['hp'] = max(0, $state['foe']['hp'] - $hit['damage']);
        $unit['rage'] = min(self::RAGE_MAX, $unit['rage'] + 8);

        $text = $move === 'attack'
            ? "{$unit['name']} атакует {$state['foe']['name']} — {$hit['damage']}"
            : "{$unit['name']}: «{$label}» по {$state['foe']['name']} — {$hit['damage']}";

        if ($mult > 1.0) {
            $text .= ' (связка!)';
        }

        $this->log($state, $hit['crit'] ? 'crit' : 'hit', $text . ($hit['crit'] ? ' Крит!' : ''), [
            'who' => $unit['name'],
            'damage' => $hit['damage'],
            'crit' => $hit['crit'],
            'move' => $move,
        ]);

        if ($chosen) {
            $this->applyAbility($state, $index, $unit['element'], $chosen);
        }

        if ($state['foe']['hp'] <= 0) {
            $state['status'] = 'won';
            $this->log($state, 'won', "{$state['foe']['name']} повержен.");
        }
    }

    /** Эффекты способности: урон уже нанесён, здесь лечение, статусы, баффы. */
    private function applyAbility(array &$state, int $index, string $element, array $ability): void
    {
        if (isset($ability['burn']) && random_int(1, 100) <= (int) $ability['burn']) {
            $state['foe']['statuses']['burn'] = self::STATUSES['burn']['rounds'];
            $this->log($state, 'status', "{$state['foe']['name']} горит.");
        }

        if (isset($ability['fear']) && random_int(1, 100) <= (int) $ability['fear']) {
            $state['foe']['statuses']['fear'] = self::STATUSES['fear']['rounds'];
            $this->log($state, 'status', "{$state['foe']['name']} в страхе — бьёт слабее.");
        }

        if (isset($ability['stun']) && random_int(1, 100) <= (int) $ability['stun']) {
            $state['foe']['statuses']['stun'] = self::STATUSES['stun']['rounds'];
            $this->log($state, 'status', "{$state['foe']['name']} оглушён и пропустит ход.");
        }

        if (isset($ability['stun_chance']) && random_int(1, 100) <= (int) $ability['stun_chance']) {
            $state['foe']['statuses']['stun'] = self::STATUSES['stun']['rounds'];
            $this->log($state, 'status', "{$state['foe']['name']} прибит шипами к полу.");
        }

        if (isset($ability['heal_lowest'])) {
            $target = $this->lowest($state);

            if ($target !== null) {
                $heal = (int) round($state['units'][$target]['max'] * (float) $ability['heal_lowest']);
                $state['units'][$target]['hp'] = min($state['units'][$target]['max'], $state['units'][$target]['hp'] + $heal);

                if ($heal > 0) {
                    $this->log($state, 'heal', "{$state['units'][$target]['name']} получает {$heal} здоровья.");
                }
            }
        }

        if (isset($ability['heal_team'])) {
            $total = 0;

            foreach ($state['units'] as $i => $unit) {
                if ($unit['hp'] <= 0) {
                    continue;
                }

                $heal = (int) round($unit['max'] * (float) $ability['heal_team']);
                $state['units'][$i]['hp'] = min($unit['max'], $unit['hp'] + $heal);
                $total += $heal;
            }

            if ($total > 0) {
                $this->log($state, 'heal', "Отряд восстанавливает {$total} здоровья.");
            }
        }

        if (! empty($ability['cleanse'])) {
            foreach ($state['units'] as $i => $unit) {
                $state['units'][$i]['statuses'] = [];
            }

            $this->log($state, 'cleanse', 'Вода смывает все напасти с отряда.');
        }

        if (isset($ability['drain_team'])) {
            $share = (int) round((float) $state['units'][$index]['atk'] * (float) $ability['drain_team']);
            $alive = array_keys(array_filter($state['units'], fn (array $u) => $u['hp'] > 0));

            if ($alive && $share > 0) {
                $each = max(1, intdiv($share, count($alive)));

                foreach ($alive as $i) {
                    $state['units'][$i]['hp'] = min($state['units'][$i]['max'], $state['units'][$i]['hp'] + $each);
                }

                $this->log($state, 'heal', "Украденное здоровье делится на отряд: по {$each} каждому.");
            }
        }

        if (isset($ability['team_atk'])) {
            $state['team'] = ['atk' => (float) $ability['team_atk'], 'rounds' => (int) ($ability['rounds'] ?? 1)];
            $this->log($state, 'buff', 'Ярость стаи: отряд бьёт на ' . (int) round(($ability['team_atk'] - 1) * 100) . '% сильнее.');
        }
    }

    private function foeTurn(array &$state): void
    {
        $foe = &$state['foe'];

        if (($foe['statuses']['stun'] ?? 0) > 0) {
            $this->log($state, 'skip', "{$foe['name']} оглушён и пропускает ход.", ['who' => $foe['name']]);

            return;
        }

        $alive = array_filter($state['units'], fn (array $u) => $u['hp'] > 0);

        if (! $alive) {
            return;
        }

        usort($alive, fn (array $a, array $b) => $a['hp'] <=> $b['hp']);

        $index = $this->indexOf($state, (int) $alive[0]['id']);

        if ($index === null) {
            return;
        }

        $target = &$state['units'][$index];
        $hurt = $foe['hp'] / max(1, $foe['max']) < 0.3;

        if ($hurt && random_int(1, 100) <= 30 && ! $foe['guard']) {
            $foe['guard'] = true;
            $this->log($state, 'defend', "{$foe['name']} заслоняется.", ['who' => $foe['name']]);

            return;
        }

        $useSkill = $foe['cd'] === 0 && random_int(1, 100) <= 55;
        $power = $useSkill ? 1.5 : 1.0;

        if ($useSkill) {
            $foe['cd'] = self::SKILL_COOLDOWN;
        }

        $hit = $this->damage($state, $foe, $target, $power, 1.0, 0);

        $target['hp'] = max(0, $target['hp'] - $hit['damage']);
        $target['rage'] = min(self::RAGE_MAX, $target['rage'] + (int) round($hit['damage'] / max(1, $target['max']) * 60));

        $text = "{$foe['name']} бьёт {$target['name']} — {$hit['damage']}"
            . ($useSkill ? ' (сильный удар)' : '')
            . ($hit['crit'] ? ' Крит!' : '');

        $this->log($state, $hit['crit'] ? 'crit' : 'foe', $text, [
            'who' => $foe['name'],
            'target' => $target['name'],
            'damage' => $hit['damage'],
            'crit' => $hit['crit'],
        ]);

        if ($target['hp'] <= 0) {
            $this->log($state, 'down', "{$target['name']} падает.");

            if (! array_filter($state['units'], fn (array $u) => $u['hp'] > 0)) {
                $state['status'] = 'lost';
                $this->log($state, 'lost', 'Отряд пал.');
            }
        }
    }

    /* ==================== Урон ==================== */

    private function damage(array $state, array $attacker, array $target, float $power, float $comboMult, int $critBonus): array
    {
        $atk = (float) $attacker['atk'];

        if ($attacker['side'] ?? null) {
            // у врага нет баффов отряда
        }

        if (($attacker['statuses']['fear'] ?? 0) > 0) {
            $atk *= 0.75;
        }

        $atk *= (float) ($state['team']['atk'] ?? 1.0);

        $raw = max(1.0, $atk * $power - (float) $target['def'] * 0.5);
        $raw *= $this->elementMultiplier((string) $attacker['element'], (string) $target['element']);
        $raw *= $comboMult;

        if ($target['guard'] ?? false) {
            $raw *= 0.5;
        }

        $crit = random_int(1, 100) <= (self::CRIT_CHANCE + $critBonus);

        if ($crit) {
            $raw *= self::CRIT_MULT;
        }

        $raw *= random_int(90, 110) / 100;

        return ['damage' => max(1, (int) round($raw)), 'crit' => $crit];
    }

    private function elementMultiplier(string $mine, string $theirs): float
    {
        if ((self::STRONG[$mine] ?? null) === $theirs) {
            return 1.25;
        }

        if ((self::STRONG[$theirs] ?? null) === $mine) {
            return 0.8;
        }

        return 1.0;
    }

    /** Множитель связки, если она контрит стихию врага. */
    private function comboMultiplier(array $state): float
    {
        $combo = $state['combo'] ?? null;

        if (! $combo) {
            return 1.0;
        }

        return $combo['counters'] === $state['foe']['element'] ? (float) $combo['power'] : 1.0;
    }

    /* ==================== Статусы ==================== */

    private function tickStatuses(array &$state): void
    {
        if (($state['foe']['statuses']['burn'] ?? 0) > 0) {
            $damage = (int) round($state['foe']['max'] * self::STATUSES['burn']['share']);
            $state['foe']['hp'] = max(0, $state['foe']['hp'] - $damage);
            $state['foe']['statuses']['burn']--;

            if ($state['foe']['statuses']['burn'] <= 0) {
                unset($state['foe']['statuses']['burn']);
            }

            $this->log($state, 'burn', "{$state['foe']['name']} теряет {$damage} от поджога.");

            if ($state['foe']['hp'] <= 0) {
                $state['status'] = 'won';
                $this->log($state, 'won', "{$state['foe']['name']} сгорает.");

                return;
            }
        }

        foreach (['stun', 'fear'] as $key) {
            if (($state['foe']['statuses'][$key] ?? 0) > 0) {
                $state['foe']['statuses'][$key]--;

                if ($state['foe']['statuses'][$key] <= 0) {
                    unset($state['foe']['statuses'][$key]);
                }
            }
        }

        foreach ($state['units'] as $index => $unit) {
            foreach (['stun', 'fear', 'burn'] as $key) {
                if (($unit['statuses'][$key] ?? 0) <= 0) {
                    continue;
                }

                if ($key === 'burn' && $unit['hp'] > 0) {
                    $damage = (int) round($unit['max'] * self::STATUSES['burn']['share']);
                    $state['units'][$index]['hp'] = max(0, $unit['hp'] - $damage);
                }

                $state['units'][$index]['statuses'][$key]--;

                if ($state['units'][$index]['statuses'][$key] <= 0) {
                    unset($state['units'][$index]['statuses'][$key]);
                }
            }
        }

        if (! array_filter($state['units'], fn (array $u) => $u['hp'] > 0)) {
            $state['status'] = 'lost';
            $this->log($state, 'lost', 'Отряд пал.');
        }
    }

    /* ==================== Служебное ==================== */

    private function lowest(array $state): ?int
    {
        $best = null;
        $bestShare = 2.0;

        foreach ($state['units'] as $index => $unit) {
            if ($unit['hp'] <= 0) {
                continue;
            }

            $share = $unit['hp'] / max(1, $unit['max']);

            if ($share < $bestShare) {
                $bestShare = $share;
                $best = $index;
            }
        }

        return $best;
    }

    private function indexOf(array $state, int $id): ?int
    {
        foreach ($state['units'] as $index => $unit) {
            if ((int) $unit['id'] === $id) {
                return $index;
            }
        }

        return null;
    }

    private function log(array &$state, string $kind, string $text, array $extra = []): void
    {
        $state['log'][] = ['kind' => $kind, 'text' => $text] + $extra;

        if (count($state['log']) > 60) {
            $state['log'] = array_slice($state['log'], -60);
        }
    }

    /** Что нужно интерфейсу: приёмы, стихии, способности, связки. */
    public function reference(): array
    {
        return [
            'moves' => self::MOVES,
            'move_names' => self::MOVE_NAMES,
            'elements' => self::ELEMENTS,
            'abilities' => self::ABILITIES,
            'combos' => self::COMBOS,
            'statuses' => self::STATUSES,
        ];
    }
}
