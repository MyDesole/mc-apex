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
    seasons: { group: 'profile', label: 'Сезоны', hint: 'Награды прошлых сезонов', icon: '✲', single: true, width: 'third', title: null },
    socials: { group: 'profile', label: 'Соцсети', hint: 'Ссылки на твои соцсети', icon: '@', single: true, width: 'half', title: 'Соцсети' },
    games: { group: 'profile', label: 'История тиров / виды бриджа', hint: 'График тир-тестов или подтверждённые виды', icon: '↗', single: true, width: 'full', title: null },
    aspects: { group: 'profile', label: 'Аспекты', hint: 'Оценки навыков PvP', icon: '✧', single: true, width: 'full', title: 'Аспекты' },
    achievements: { group: 'profile', label: 'Ачивки', hint: 'Витрина достижений', icon: '✦', single: true, width: 'half', title: null },
    friends: { group: 'profile', label: 'Друзья', hint: 'Список друзей', icon: '♢', single: true, width: 'half', title: null },
    recommendations: { group: 'profile', label: 'Рекомендации', hint: 'Отзывы друзей о тебе', icon: '❝', single: true, width: 'full', title: null },

    // Шапка, разобранная на части (режим «Разобрать на блоки»)
    h_avatar: { group: 'header', label: 'Аватар', hint: 'Аватар с рамкой из инвентаря', icon: '◉', single: true, width: 'quarter', title: null },
    h_name: { group: 'header', label: 'Ник', hint: 'Ник с эффектом, значком, ролью и титулом', icon: 'Aa', single: true, width: 'three_quarters', title: null },
    h_badges: { group: 'header', label: 'Бейджи', hint: 'Купленные бейджи', icon: '✪', single: true, width: 'half', title: null },
    h_about: { group: 'header', label: 'О себе', hint: 'Статус, описание и цитата', icon: '❞', single: true, width: 'half', title: null },
    h_tier: { group: 'header', label: 'Тир и плашки', hint: 'Полоса тира, клан, дни, уровень', icon: '▲', single: true, width: 'full', title: null },

    // Свои блоки
    text:{ group: 'content', label: 'Свой текст', hint: 'Абзац о чём угодно: правила, истории, планы', icon: '¶', width: 'half', title: 'Заметка', settings: { body: '', size: 'm' } },
    quote: { group: 'content', label: 'Цитата', hint: 'Крупная цитата с автором', icon: '“', width: 'full', title: null, settings: { text: '', author: '' } },
    facts: { group: 'content', label: 'Факты', hint: 'Таблица: девайс, пинг, любимый кит…', icon: '☰', width: 'third', title: 'Факты', settings: { items: [{ label: 'Мышь', value: '' }, { label: 'Пинг', value: '' }] } },
    bars: { group: 'content', label: 'Навыки', hint: 'Полоски от 0 до 100', icon: '▤', width: 'third', title: 'Навыки', settings: { items: [{ label: 'Бридж', value: 80 }, { label: 'Клатчи', value: 60 }] } },
    timeline: { group: 'content', label: 'Мой путь', hint: 'Лента событий: когда и что случилось', icon: '⋮', width: 'half', title: 'Мой путь', settings: { items: [{ when: '', text: '' }] } },
    players: { group: 'content', label: 'Тиммейты', hint: 'Игроки APEX, с кем играешь: дуо, сквад, тренер', icon: '⚇', width: 'half', title: 'Мой сквад', settings: { items: [] } },
    poll: { group: 'content', label: 'Опрос', hint: 'Гости голосуют — результаты в реальном времени', icon: '▥', width: 'third', title: 'Опрос', settings: { question: 'Какой режим лучший?', options: ['Бридж', 'BedWars'] } },
    goals: { group: 'content', label: 'Цели', hint: 'Список целей с галочками и прогрессом', icon: '◎', width: 'third', title: 'Мои цели', settings: { items: [{ text: 'Получить тир A', done: false }, { text: 'Выиграть турнир', done: false }] } },
    counter: { group: 'content', label: 'Счётчик', hint: 'Крупная цифра: победы, часы, рекорды', icon: '№', width: 'quarter', title: null, settings: { value: '1000', label: 'побед в бридже', emoji: '🏆' } },
    countdown: { group: 'content', label: 'Обратный отсчёт', hint: 'До турнира, стрима или дня рождения', icon: '◷', width: 'third', title: null, settings: { title: '', date: '' } },
    video: { group: 'content', label: 'Видео с YouTube', hint: 'Твой лучший хайлайт прямо в профиле', icon: '▶', width: 'two_thirds', title: null, settings: { url: '' } },
    setup: { group: 'content', label: 'Сетап', hint: 'Мышь, клава, DPI, сенса — с иконками', icon: '⌨', width: 'half', title: 'Мой сетап', settings: { items: [{ kind: 'mouse', value: '' }, { kind: 'dpi', value: '800' }] } },
    links: { group: 'content', label: 'Кнопки-ссылки', hint: 'Яркие кнопки: Discord, YouTube, Twitch…', icon: '⇗', width: 'third', title: null, settings: { style: 'solid', items: [] } },
    music: { group: 'content', label: 'Сейчас слушаю', hint: 'Трек со Spotify, YouTube или Яндекс Музыки', icon: '♫', width: 'third', title: null, settings: { url: '', title: '', artist: '' } },
    faq: { group: 'content', label: 'Вопрос — ответ', hint: 'Раскрывающиеся ответы на частые вопросы', icon: '?', width: 'half', title: 'Частые вопросы', settings: { items: [{ q: 'Во что играешь?', a: '' }] } },
    schedule: { group: 'content', label: 'Расписание', hint: 'Когда играю или стримлю — по дням недели', icon: '▦', width: 'third', title: 'Расписание', settings: { items: [{ day: 'fri', time: '20:00', text: 'Бридж с кланом' }] } },
    banner: { group: 'content', label: 'Баннер', hint: 'Крупный призыв с кнопкой: «Пиши в ДС»', icon: '▬', width: 'full', title: null, settings: { title: 'Ищу тиммейта на турнир', text: '', button: 'Написать', url: '' } },
    progress: { group: 'content', label: 'Прогресс до цели', hint: 'Полоса с процентами: до тира, до 1000 побед', icon: '▰', width: 'half', title: null, settings: { title: 'До 1000 побед', current: 640, target: 1000, unit: 'побед' } },
    guestbook: { group: 'content', label: 'Гостевая книга', hint: 'Гости оставляют тебе записи', icon: '✎', single: true, width: 'half', title: 'Гостевая книга' },

    // Украшения
    sticker: { group: 'decor', label: 'Пиксель-арт', hint: 'Крупная анимированная картинка с подписью', icon: '▣', width: 'third', title: null, settings: { sprite: 'creeper', caption: '' } },
    marquee: { group: 'decor', label: 'Бегущая строка', hint: 'Текст, который бесконечно едет по экрану', icon: '⇉', width: 'full', title: null, settings: { text: 'APEX TIERS', speed: 'normal', direction: 'left', size: 'l' } },
    divider: { group: 'decor', label: 'Разделитель', hint: 'Линия между частями профиля', icon: '—', width: 'full', title: null, settings: { style: 'line' } },
    spacer: { group: 'decor', label: 'Пустое место', hint: 'Отступ, чтобы раздвинуть блоки', icon: '␣', width: 'full', title: null, settings: { size: 'm' } },
}

export const GROUPS = [
    { id: 'header', label: 'Части шапки' },
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
    border: { label: 'Рамка', options: [['none', 'Обычная'], ['shine', 'Блик'], ['pulse', 'Пульс'], ['rainbow', 'Радуга'], ['glow', 'Свечение']] },
    text: { label: 'Цвет текста', options: [['auto', 'Авто'], ['light', 'Светлый'], ['dark', 'Тёмный'], ['accent', 'Цветом блока']] },
    pad: { label: 'Отступы внутри', options: [['normal', 'Обычные'], ['none', 'Нет'], ['s', 'S'], ['l', 'L'], ['xl', 'XL']] },
    size: { label: 'Размер текста', options: [['m', 'M'], ['s', 'S'], ['l', 'L'], ['xl', 'XL']] },
    anim: { label: 'Живая анимация', options: [['none', 'Нет'], ['float', 'Парит'], ['sway', 'Качается'], ['pulse', 'Пульс'], ['bounce', 'Прыгает']] },
    reveal: { label: 'Появление при прокрутке', options: [['none', 'Сразу'], ['up', 'Снизу'], ['left', 'Слева'], ['right', 'Справа'], ['zoom', 'Зум'], ['flip', 'Переворот']] },
}

/** Настройки всей страницы. */
export const PAGE_OPTIONS = {
    flow: { label: 'Укладка', options: [['masonry', 'Плотно, без дыр'], ['rows', 'Ровными рядами']] },
    gap: { label: 'Отступы', options: [['normal', 'Обычные'], ['tight', 'Плотно'], ['loose', 'Просторно']] },
    header: { label: 'Шапка', options: [['full', 'Полная'], ['compact', 'Компактная']] },
    appear: { label: 'Появление', options: [['rise', 'Снизу'], ['none', 'Сразу'], ['fade', 'Проявление'], ['zoom', 'Приближение'], ['cascade', 'Каскад']] },
    radius: { label: 'Углы блоков', options: [['round', 'Скруглённые'], ['sharp', 'Острые'], ['soft', 'Мягкие']] },
    backdrop: { label: 'Фон под блоками', options: [['none', 'Нет'], ['dim', 'Затемнить'], ['blur', 'Размыть']] },
    bg: { label: 'Фон страницы', options: [['none', 'Как в теме'], ['gradient', 'Градиент'], ['mesh', 'Меш'], ['aurora', 'Сияние'], ['stars', 'Звёзды'], ['grid', 'Сетка'], ['spotlight', 'Прожектор'], ['noise', 'Шум']] },
    font: { label: 'Шрифт профиля', options: [['default', 'Обычный'], ['display', 'Заголовочный'], ['mono', 'Моно'], ['serif', 'С засечками'], ['rounded', 'Округлый']] },
    shadow: { label: 'Тени блоков', options: [['soft', 'Мягкие'], ['none', 'Без теней'], ['deep', 'Глубокие'], ['neon', 'Неоновые']] },
    width: { label: 'Ширина', options: [['normal', 'Обычная'], ['wide', 'Широкая'], ['full', 'Во весь экран']] },
    titles: { label: 'Заголовки блоков', options: [['default', 'Обычные'], ['caps', 'ПРОПИСНЫЕ'], ['line', 'С линией'], ['pill', 'Плашкой'], ['hidden', 'Скрыть все']] },
    density: { label: 'Плотность', options: [['normal', 'Обычная'], ['compact', 'Плотно'], ['airy', 'Воздушно']] },
    hdr_mode: { label: 'Шапка', options: [['whole', 'Целиком'], ['blocks', 'Разобрать на блоки']] },
    hdr_layout: { label: 'Раскладка', options: [['classic', 'Классика'], ['center', 'По центру'], ['mirror', 'Зеркально'], ['banner', 'Баннер'], ['minimal', 'Минимал']] },
    hdr_height: { label: 'Высота', options: [['normal', 'Обычная'], ['short', 'Низкая'], ['tall', 'Высокая']] },
    hdr_bg: { label: 'Фон шапки', options: [['theme', 'Как в теме'], ['accent', 'Акцент'], ['gradient', 'Градиент'], ['glass', 'Стекло'], ['none', 'Без фона']] },
    hdr_avatar: { label: 'Форма аватара', options: [['circle', 'Круг'], ['rounded', 'Скруглённый'], ['square', 'Квадрат'], ['hex', 'Шестиугольник']] },
    hdr_avatar_size: { label: 'Размер аватара', options: [['m', 'Обычный'], ['s', 'Маленький'], ['l', 'Крупный']] },
}

/** Числа и цвета страницы — не выбор из списка, а ползунки и пипетки. */
export const PAGE_EXTRAS = { bg_from: '#2e1065', bg_to: '#0b0912', glass: 100, blur: 0 }

/**
 * Готовые палитры студии: фон, два цвета и характер блоков одним кликом.
 * Акцента здесь нет — цвет акцента продаётся в магазине.
 */
export const THEME_PRESETS = [
    { id: 'midnight', label: 'Полночь', bg: 'mesh', bg_from: '#312e81', bg_to: '#05060f', shadow: 'soft', glass: 100, blur: 0 },
    { id: 'sunset', label: 'Закат', bg: 'gradient', bg_from: '#9a3412', bg_to: '#1c0a1e', shadow: 'deep', glass: 85, blur: 8 },
    { id: 'neon', label: 'Неон', bg: 'grid', bg_from: '#db2777', bg_to: '#07030d', shadow: 'neon', glass: 70, blur: 10 },
    { id: 'ocean', label: 'Океан', bg: 'aurora', bg_from: '#0e7490', bg_to: '#020617', shadow: 'soft', glass: 65, blur: 16 },
    { id: 'forest', label: 'Лес', bg: 'spotlight', bg_from: '#166534', bg_to: '#04110a', shadow: 'deep', glass: 90, blur: 4 },
    { id: 'nether', label: 'Незер', bg: 'noise', bg_from: '#7f1d1d', bg_to: '#120404', shadow: 'deep', glass: 90, blur: 0 },
    { id: 'end', label: 'Энд', bg: 'stars', bg_from: '#4c1d95', bg_to: '#030008', shadow: 'neon', glass: 60, blur: 14 },
    { id: 'gold', label: 'Золото', bg: 'spotlight', bg_from: '#a16207', bg_to: '#0c0a04', shadow: 'soft', glass: 95, blur: 0 },
    { id: 'ice', label: 'Лёд', bg: 'gradient', bg_from: '#1e3a8a', bg_to: '#0b1120', shadow: 'soft', glass: 55, blur: 20 },
    { id: 'mono', label: 'Монохром', bg: 'grid', bg_from: '#27272a', bg_to: '#09090b', shadow: 'none', glass: 100, blur: 0 },
]

/** Кем тиммейт приходится игроку — подпись и цвет карточки. */
export const TEAMMATE_ROLES = [
    { id: 'duo', label: 'Дуо', icon: '⚔', color: '#8b5cf6' },
    { id: 'squad', label: 'Сквад', icon: '⛨', color: '#3b82f6' },
    { id: 'coach', label: 'Тренер', icon: '★', color: '#eab308' },
    { id: 'student', label: 'Ученик', icon: '✎', color: '#22c55e' },
    { id: 'rival', label: 'Соперник', icon: '⚡', color: '#ef4444' },
    { id: 'bestie', label: 'Лучший друг', icon: '♥', color: '#ec4899' },
]

export function teammateRole(id) {
    return TEAMMATE_ROLES.find((r) => r.id === id) ?? TEAMMATE_ROLES[0]
}

export const STICKER_SPRITES = [
    'creeper', 'bee', 'axolotl', 'tnt', 'diamond', 'heart', 'skull', 'warden', 'totem', 'cat',
    'paw', 'crown', 'flame', 'dog', 'fox', 'slime', 'ghost', 'dragon', 'robot', 'penguin',
]

export function widthSpan(width) {
    return WIDTHS.find((w) => w.id === width)?.span ?? 12
}

export function defaultStyle() {
    const style = { color: 'auto', title: '', icon: '', hide_title: false, hide_mobile: false, hex: '', tilt: 0, opacity: 100, grad_from: '', grad_to: '', grad_angle: 135, bw: 0 }
    for (const [key, def] of Object.entries(STYLE_OPTIONS)) style[key] = def.options[0][0]
    return style
}

/** Части шапки: их можно переставлять, прятать и оформлять по отдельности. */
export const HEADER_ITEMS = {
    name: 'Ник',
    role: 'Роль',
    stats: 'Плашки: клан, дни',
    progress: 'Полоса тира',
}

export const HEADER_ITEM_STYLE = {
    size: { label: 'Размер', options: [['s', 'S'], ['m', 'M'], ['l', 'L'], ['xl', 'XL']] },
    font: { label: 'Шрифт', options: [['default', 'Обычный'], ['display', 'Заголовочный'], ['mono', 'Моно'], ['serif', 'С засечками'], ['rounded', 'Округлый']] },
    weight: { label: 'Жирность', options: [['default', 'Как есть'], ['light', 'Тонкий'], ['bold', 'Жирный'], ['black', 'Сверхжирный']] },
    spacing: { label: 'Разрядка', options: [['tight', 'Плотно'], ['normal', 'Обычно'], ['wide', 'Широко']] },
    case: { label: 'Регистр', options: [['none', 'Как есть'], ['upper', 'ВЕРХНИЙ'], ['lower', 'нижний']] },
}

export function defaultHeaderItems() {
    return Object.keys(HEADER_ITEMS).map((id) => ({ id, hidden: false, size: 'm', font: 'default', weight: 'default', spacing: 'normal', case: 'none' }))
}

export function defaultPage() {
    const page = { ...PAGE_EXTRAS, hdr_items: defaultHeaderItems() }
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
