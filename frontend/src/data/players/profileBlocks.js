/**
 * Конструктор профиля: какие бывают блоки, как их можно оформить и
 * готовые шаблоны раскладки. Значения совпадают с ProfileBlocksService.
 */

/**
 * group — раздел палитры. single — блок бывает в профиле один раз
 * (готовые части профиля и гостевая книга). title — заголовок блока по
 * умолчанию; null — у блока свой заголовок внутри или он не нужен.
 */
export const BLOCK_TYPES = {
    // Части профиля
    stats: { group: 'profile', label: 'Статистика', hint: 'Тир, ачивки, друзья, рекомендации', icon: '◆', single: true, width: 'full', title: null },
    showcase: { group: 'profile', label: 'Minecraft-карточка', hint: '3D-скин и карточка игрока', icon: '⬢', single: true, width: 'two_thirds', title: null },
    clan: { group: 'profile', label: 'Клан', hint: 'Клан и роль в нём', icon: '⛨', single: true, width: 'third', title: 'Клан' },
    rank: { group: 'profile', label: 'Место в топе', hint: 'Позиция в рейтинге', icon: '#', single: true, width: 'third', title: 'Место в топе' },
    rating: { group: 'profile', label: 'Разбор рейтинга', hint: 'Из чего сложились очки', icon: '∑', single: true, width: 'third', title: null },
    seasons: { group: 'profile', label: 'Сезоны', hint: 'Награды прошлых сезонов', icon: '❄', single: true, width: 'third', title: null },
    socials: { group: 'profile', label: 'Соцсети', hint: 'Ссылки на твои соцсети', icon: '@', single: true, width: 'half', title: 'Соцсети' },
    games: { group: 'profile', label: 'История тиров / виды бриджа', hint: 'График тир-тестов или подтверждённые виды', icon: '↗', single: true, width: 'full', title: null },
    aspects: { group: 'profile', label: 'Аспекты', hint: 'Оценки навыков PvP', icon: '✧', single: true, width: 'full', title: 'Аспекты' },
    achievements: { group: 'profile', label: 'Ачивки', hint: 'Витрина достижений', icon: '✦', single: true, width: 'half', title: null },
    friends: { group: 'profile', label: 'Друзья', hint: 'Список друзей', icon: '♢', single: true, width: 'half', title: null },
    recommendations: { group: 'profile', label: 'Рекомендации', hint: 'Отзывы друзей о тебе', icon: '❝', single: true, width: 'full', title: null },

    // Свои блоки
    text: { group: 'content', label: 'Свой текст', hint: 'Абзац о чём угодно: правила, истории, планы', icon: '¶', width: 'half', title: 'Заметка', settings: { body: '', size: 'm' } },
    quote: { group: 'content', label: 'Цитата', hint: 'Крупная цитата с автором', icon: '“', width: 'full', title: null, settings: { text: '', author: '' } },
    facts: { group: 'content', label: 'Факты', hint: 'Таблица: девайс, пинг, любимый кит…', icon: '☰', width: 'third', title: 'Факты', settings: { items: [{ label: 'Мышь', value: '' }, { label: 'Пинг', value: '' }] } },
    bars: { group: 'content', label: 'Навыки', hint: 'Полоски от 0 до 100', icon: '▤', width: 'third', title: 'Навыки', settings: { items: [{ label: 'Бридж', value: 80 }, { label: 'Клатчи', value: 60 }] } },
    timeline: { group: 'content', label: 'Мой путь', hint: 'Лента событий: когда и что случилось', icon: '⋮', width: 'half', title: 'Мой путь', settings: { items: [{ when: '', text: '' }] } },
    players: { group: 'content', label: 'Тиммейты', hint: 'Ники игроков со ссылками на профили', icon: '☺', width: 'third', title: 'Тиммейты', settings: { items: [] } },
    countdown: { group: 'content', label: 'Обратный отсчёт', hint: 'До турнира, стрима или дня рождения', icon: '⏱', width: 'third', title: null, settings: { title: '', date: '' } },
    video: { group: 'content', label: 'Видео с YouTube', hint: 'Твой лучший хайлайт прямо в профиле', icon: '▶', width: 'two_thirds', title: null, settings: { url: '' } },
    guestbook: { group: 'content', label: 'Гостевая книга', hint: 'Гости оставляют тебе записи', icon: '✎', single: true, width: 'half', title: 'Гостевая книга' },

    // Украшения
    sticker: { group: 'decor', label: 'Пиксель-арт', hint: 'Крупная анимированная картинка с подписью', icon: '▣', width: 'third', title: null, settings: { sprite: 'creeper', caption: '' } },
    divider: { group: 'decor', label: 'Разделитель', hint: 'Линия между частями профиля', icon: '—', width: 'full', title: null, settings: { style: 'line' } },
    spacer: { group: 'decor', label: 'Пустое место', hint: 'Отступ, чтобы раздвинуть блоки', icon: '␣', width: 'full', title: null, settings: { size: 'm' } },
}

export const GROUPS = [
    { id: 'profile', label: 'Части профиля' },
    { id: 'content', label: 'Свои блоки' },
    { id: 'decor', label: 'Украшения' },
]

export const WIDTHS = [
    { id: 'quarter', label: '¼', span: 3 },
    { id: 'third', label: '⅓', span: 4 },
    { id: 'half', label: '½', span: 6 },
    { id: 'two_thirds', label: '⅔', span: 8 },
    { id: 'three_quarters', label: '¾', span: 9 },
    { id: 'full', label: '1', span: 12 },
]

export const TONES = [
    { id: 'default', label: 'Обычный' },
    { id: 'accent', label: 'Акцент' },
    { id: 'glass', label: 'Стекло' },
    { id: 'gradient', label: 'Градиент' },
    { id: 'neon', label: 'Неон' },
    { id: 'paper', label: 'Бумага' },
    { id: 'outline', label: 'Контур' },
    { id: 'transparent', label: 'Без фона' },
]

/** Цвет блока: auto — акцент профиля. */
export const COLORS = [
    { id: 'auto', label: 'Акцент профиля', hex: null },
    { id: 'violet', hex: '#8b5cf6' },
    { id: 'blue', hex: '#3b82f6' },
    { id: 'cyan', hex: '#06b6d4' },
    { id: 'green', hex: '#22c55e' },
    { id: 'lime', hex: '#84cc16' },
    { id: 'yellow', hex: '#eab308' },
    { id: 'orange', hex: '#f97316' },
    { id: 'red', hex: '#ef4444' },
    { id: 'pink', hex: '#ec4899' },
    { id: 'white', hex: '#e5e7eb' },
]

/** Остальное оформление блока: поле → варианты (первый — по умолчанию). */
export const STYLE_OPTIONS = {
    align: { label: 'Выравнивание', options: [['left', 'Слева'], ['center', 'По центру'], ['right', 'Справа']] },
    height: { label: 'Высота', options: [['auto', 'Авто'], ['compact', 'Низкий'], ['tall', 'Высокий'], ['huge', 'Огромный']] },
    pattern: { label: 'Узор', options: [['none', 'Нет'], ['dots', 'Точки'], ['grid', 'Сетка'], ['stripes', 'Полосы'], ['aurora', 'Сияние'], ['noise', 'Шум']] },
    radius: { label: 'Углы', options: [['inherit', 'Как у страницы'], ['sharp', 'Острые'], ['round', 'Скруглённые'], ['pill', 'Сильно']] },
    hover: { label: 'При наведении', options: [['none', 'Ничего'], ['lift', 'Подъём'], ['glow', 'Свечение'], ['tilt', 'Наклон']] },
    font: { label: 'Шрифт', options: [['default', 'Обычный'], ['display', 'Заголовочный'], ['mono', 'Моно'], ['serif', 'С засечками']] },
}

/** Настройки всей страницы. */
export const PAGE_OPTIONS = {
    gap: { label: 'Отступы', options: [['normal', 'Обычные'], ['tight', 'Плотно'], ['loose', 'Просторно']] },
    header: { label: 'Шапка', options: [['full', 'Полная'], ['compact', 'Компактная']] },
    appear: { label: 'Появление', options: [['rise', 'Снизу'], ['none', 'Сразу'], ['fade', 'Проявление'], ['zoom', 'Приближение'], ['cascade', 'Каскад']] },
    radius: { label: 'Углы блоков', options: [['round', 'Скруглённые'], ['sharp', 'Острые'], ['soft', 'Мягкие']] },
    backdrop: { label: 'Фон под блоками', options: [['none', 'Нет'], ['dim', 'Затемнить'], ['blur', 'Размыть']] },
}

export const STICKER_SPRITES = [
    'creeper', 'bee', 'axolotl', 'tnt', 'diamond', 'heart', 'skull', 'warden', 'totem', 'cat',
    'paw', 'crown', 'flame', 'dog', 'fox', 'slime', 'ghost', 'dragon', 'robot', 'penguin',
]

export function widthSpan(width) {
    return WIDTHS.find((w) => w.id === width)?.span ?? 12
}

export function defaultStyle() {
    const style = { color: 'auto', title: '', hide_title: false, hide_mobile: false }
    for (const [key, def] of Object.entries(STYLE_OPTIONS)) style[key] = def.options[0][0]
    return style
}

export function defaultPage() {
    const page = {}
    for (const [key, def] of Object.entries(PAGE_OPTIONS)) page[key] = def.options[0][0]
    return page
}

let counter = 0

export function newBlockId() {
    counter += 1
    return (Date.now().toString(36) + counter.toString(36)).slice(-12)
}

export function makeBlock(type, overrides = {}) {
    const def = BLOCK_TYPES[type]

    return {
        id: newBlockId(),
        type,
        width: def?.width ?? 'full',
        tone: 'default',
        settings: JSON.parse(JSON.stringify(def?.settings ?? {})),
        ...overrides,
        style: { ...defaultStyle(), ...(overrides.style ?? {}) },
    }
}

/** Блок из старой раскладки: дописываем недостающее оформление. */
export function withDefaults(block) {
    return { ...block, settings: block.settings ?? {}, style: { ...defaultStyle(), ...(block.style ?? {}) } }
}

/** Заголовок блока: свой, по умолчанию или никакого. */
export function blockTitle(block) {
    if (block.style?.hide_title) return ''
    return block.style?.title || BLOCK_TYPES[block.type]?.title || ''
}

/**
 * Раскладка «как сейчас»: с неё начинается конструктор, если игрок
 * ещё ничего не настраивал.
 */
export function defaultBlocks() {
    return [
        makeBlock('stats'),
        makeBlock('socials'),
        makeBlock('achievements'),
        makeBlock('games'),
        makeBlock('aspects'),
        makeBlock('friends'),
        makeBlock('recommendations'),
        makeBlock('showcase'),
        makeBlock('clan'),
        makeBlock('rank', { width: 'third' }),
        makeBlock('rating', { width: 'half' }),
        makeBlock('seasons', { width: 'half' }),
    ]
}

/** Готовые шаблоны: раскладка и настройки страницы одним нажатием. */
export const PRESETS = [
    {
        id: 'classic',
        color: '#8b5cf6',
        label: 'Классика',
        hint: 'Как обычный профиль, только всё двигается',
        blocks: defaultBlocks,
        page: { gap: 'normal', appear: 'rise', radius: 'round' },
    },
    {
        id: 'pro',
        color: '#ef4444',
        label: 'Соревновательный',
        hint: 'Цифры, место в топе, тиры и аспекты наверху',
        blocks: () => [
            makeBlock('rank', { width: 'quarter', tone: 'neon' }),
            makeBlock('stats', { width: 'three_quarters' }),
            makeBlock('games', { width: 'two_thirds', tone: 'accent' }),
            makeBlock('bars', { width: 'third' }),
            makeBlock('aspects', { width: 'full' }),
            makeBlock('rating', { width: 'half' }),
            makeBlock('seasons', { width: 'half' }),
            makeBlock('achievements', { width: 'full' }),
        ],
        page: { gap: 'tight', appear: 'cascade', radius: 'sharp' },
    },
    {
        id: 'streamer',
        color: '#ec4899',
        label: 'Стример',
        hint: 'Видео, отсчёт до стрима, соцсети и гостевая',
        blocks: () => [
            makeBlock('video', { width: 'two_thirds', tone: 'glass' }),
            makeBlock('countdown', { width: 'third', tone: 'gradient', settings: { title: 'До стрима', date: '' } }),
            makeBlock('socials', { width: 'third' }),
            makeBlock('quote', { width: 'two_thirds', tone: 'transparent' }),
            makeBlock('guestbook', { width: 'half' }),
            makeBlock('friends', { width: 'half' }),
            makeBlock('showcase', { width: 'full' }),
        ],
        page: { gap: 'loose', appear: 'zoom', radius: 'soft', header: 'compact' },
    },
    {
        id: 'story',
        color: '#eab308',
        label: 'Рассказчик',
        hint: 'Текст, путь игрока, факты и пиксель-арт',
        blocks: () => [
            makeBlock('quote', { width: 'full', tone: 'transparent', style: { align: 'center', font: 'serif' } }),
            makeBlock('text', { width: 'two_thirds', tone: 'paper' }),
            makeBlock('sticker', { width: 'third', tone: 'transparent' }),
            makeBlock('timeline', { width: 'half' }),
            makeBlock('facts', { width: 'half' }),
            makeBlock('divider', { settings: { style: 'glow' } }),
            makeBlock('achievements', { width: 'half' }),
            makeBlock('players', { width: 'half' }),
        ],
        page: { gap: 'normal', appear: 'fade', radius: 'soft' },
    },
    {
        id: 'minimal',
        color: '#06b6d4',
        label: 'Минимализм',
        hint: 'Только карточка, клан и соцсети',
        blocks: () => [
            makeBlock('showcase', { width: 'full', tone: 'transparent' }),
            makeBlock('clan', { width: 'half', tone: 'outline' }),
            makeBlock('socials', { width: 'half', tone: 'outline' }),
        ],
        page: { gap: 'loose', appear: 'fade', radius: 'sharp', header: 'compact' },
    },
]

/** id ролика из ссылки YouTube (watch, youtu.be, shorts, embed). */
export function youtubeId(url) {
    const match = String(url || '').match(/(?:youtu\.be\/|v=|shorts\/|embed\/)([A-Za-z0-9_-]{11})/)
    return match ? match[1] : null
}
