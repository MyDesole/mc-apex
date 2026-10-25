/**
 * Аспекты тир-теста: ключи, подписи и максимум.
 *
 * Каждый аспект оценивается от 0 до 20, сумма пяти — процент тира.
 * Короткие подписи — для карточки игрока и радара сравнения.
 */
export const ASPECT_MAX = 20

export const ASPECTS = {
    pvp: [
        { key: 'block_placing', label: 'Блоки', short: 'БЛК' },
        { key: 'rotka', label: 'Удочка', short: 'УДЧ' },
        { key: 'movement', label: 'Мувмент', short: 'МУВ' },
        { key: 'aim', label: 'Аим', short: 'АИМ' },
        { key: 'game_sense', label: 'Понимание боя', short: 'БОЙ' },
    ],
    bedwars: [
        { key: 'pvp', label: 'PvP', short: 'PVP' },
        { key: 'game_sense', label: 'Понимание игры', short: 'ИГР' },
        { key: 'bed_play', label: 'Игра на кровати', short: 'КРВ' },
        { key: 'teamplay', label: 'Командная игра', short: 'КОМ' },
        { key: 'building', label: 'Строительство', short: 'СТР' },
    ],
}

export const MODE_LABELS = {
    pvp: 'PvP',
    bedwars: 'BedWars',
}

/**
 * Режим и значения аспектов игрока: PvP, если он его проходил, иначе BedWars.
 * @returns {{ mode: string, values: Array<{key: string, label: string, short: string, value: number}> } | null}
 */
export function playerAspects(user, preferred = 'pvp') {
    const all = user?.aspects ?? {}
    const order = preferred === 'bedwars' ? ['bedwars', 'pvp'] : ['pvp', 'bedwars']

    for (const mode of order) {
        const record = all[mode]

        if (!record) continue

        const values = ASPECTS[mode].map((aspect) => ({
            ...aspect,
            value: Number(record[aspect.key] ?? 0),
        }))

        if (values.some((aspect) => aspect.value > 0)) {
            return { mode, values }
        }
    }

    return null
}
