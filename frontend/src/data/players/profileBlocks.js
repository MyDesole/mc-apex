/**
 * Конструктор профиля: какие бывают блоки и как они выглядят по умолчанию.
 * Типы, ширины и оформления совпадают с ProfileBlocksService на сервере.
 */

/**
 * builtin — готовый блок профиля (статистика, друзья…): в профиле может
 * быть только один такой. Остальные можно добавлять сколько угодно.
 */
export const BLOCK_TYPES = {
    stats: { label: 'Статистика', hint: 'Тир, ачивки, друзья, рекомендации', icon: '◆', builtin: true, width: 'full' },
    socials: { label: 'Соцсети', hint: 'Ссылки на твои соцсети', icon: '@', builtin: true, width: 'half' },
    games: { label: 'История тиров / виды бриджа', hint: 'График тир-тестов или подтверждённые виды', icon: '↗', builtin: true, width: 'full' },
    aspects: { label: 'Аспекты', hint: 'Оценки навыков PvP', icon: '✧', builtin: true, width: 'full' },
    achievements: { label: 'Ачивки', hint: 'Витрина достижений', icon: '✦', builtin: true, width: 'half' },
    friends: { label: 'Друзья', hint: 'Список друзей', icon: '♢', builtin: true, width: 'half' },
    recommendations: { label: 'Рекомендации', hint: 'Отзывы друзей о тебе', icon: '❝', builtin: true, width: 'full' },

    text: { label: 'Текст', hint: 'Заголовок и абзац о чём угодно', icon: '¶', width: 'half', settings: { title: 'Обо мне', body: '' } },
    quote: { label: 'Цитата', hint: 'Крупная цитата с автором', icon: '“', width: 'full', settings: { text: '', author: '' } },
    links: { label: 'Кнопки-ссылки', hint: 'До 8 кнопок: Discord, YouTube, твич…', icon: '⇗', width: 'third', settings: { items: [] } },
    now: { label: 'Сейчас', hint: 'Статус: во что играю, чем занят', icon: '●', width: 'third', settings: { emoji: '🎮', text: '' } },
    countdown: { label: 'Обратный отсчёт', hint: 'До турнира, стрима или дня рождения', icon: '⏱', width: 'third', settings: { title: '', date: '' } },
    video: { label: 'Видео с YouTube', hint: 'Твой лучший хайлайт прямо в профиле', icon: '▶', width: 'two_thirds', settings: { url: '' } },
    guestbook: { label: 'Гостевая книга', hint: 'Гости оставляют тебе записи', icon: '✎', width: 'half', settings: { title: 'Гостевая книга' } },
    sticker: { label: 'Пиксель-арт', hint: 'Крупная анимированная картинка с подписью', icon: '▣', width: 'third', settings: { sprite: 'creeper', caption: '' } },
    clan: { label: 'Мой клан', hint: 'Карточка клана со ссылкой', icon: '⛨', width: 'third', settings: {} },
    divider: { label: 'Разделитель', hint: 'Линия между частями профиля', icon: '—', width: 'full', settings: { style: 'line' } },
}

export const WIDTHS = [
    { id: 'third', label: '⅓', span: 4 },
    { id: 'half', label: '½', span: 6 },
    { id: 'two_thirds', label: '⅔', span: 8 },
    { id: 'full', label: '1', span: 12 },
]

export const TONES = [
    { id: 'default', label: 'Обычный' },
    { id: 'accent', label: 'Акцент' },
    { id: 'glass', label: 'Стекло' },
    { id: 'outline', label: 'Контур' },
    { id: 'transparent', label: 'Без фона' },
]

export const STICKER_SPRITES = [
    'creeper', 'bee', 'axolotl', 'tnt', 'diamond', 'heart', 'skull', 'warden', 'totem', 'cat',
    'paw', 'crown', 'flame', 'dog', 'fox', 'slime', 'ghost', 'dragon', 'robot', 'penguin',
]

export function widthSpan(width) {
    return WIDTHS.find((w) => w.id === width)?.span ?? 12
}

let counter = 0

export function newBlockId() {
    counter += 1
    return (Date.now().toString(36) + counter.toString(36)).slice(-12)
}

export function makeBlock(type) {
    const def = BLOCK_TYPES[type]

    return {
        id: newBlockId(),
        type,
        width: def?.width ?? 'full',
        tone: 'default',
        settings: JSON.parse(JSON.stringify(def?.settings ?? {})),
    }
}

/**
 * Раскладка «как сейчас»: с неё начинается конструктор, если игрок
 * ещё ничего не настраивал.
 */
export function defaultBlocks() {
    return [
        makeBlock('stats'),
        makeBlock('socials'),
        { ...makeBlock('achievements'), width: 'half' },
        makeBlock('games'),
        makeBlock('aspects'),
        { ...makeBlock('friends'), width: 'half' },
        makeBlock('recommendations'),
    ]
}

/** id ролика из ссылки YouTube (watch, youtu.be, shorts, embed). */
export function youtubeId(url) {
    const match = String(url || '').match(/(?:youtu\.be\/|v=|shorts\/|embed\/)([A-Za-z0-9_-]{11})/)
    return match ? match[1] : null
}
