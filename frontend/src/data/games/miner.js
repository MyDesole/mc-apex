/**
 * «Шахтёр»: подписи блоков, ярусов, улучшений и событий забега.
 * Правила и цены знает только сервер — здесь только то, как их показать.
 */

export const BLOCKS = {
  dirt: 'Земля',
  stone: 'Камень',
  coal: 'Уголь',
  copper: 'Медь',
  iron: 'Железо',
  redstone: 'Редстоун',
  lapis: 'Лазурит',
  gold: 'Золото',
  diamond: 'Алмаз',
  emerald: 'Изумруд',
  deepslate: 'Глубинный сланец',
  amethyst: 'Аметист',
  netherrack: 'Незерак',
  quartz: 'Кварц',
  glowstone: 'Светокамень',
  nether_gold: 'Незер-золото',
  debris: 'Древние обломки',
  end_stone: 'Эндерняк',
  chorus: 'Хорус',
  purpur: 'Пурпур',
  shulker: 'Панцирь шалкера',
  tnt: 'TNT',
  gravel: 'Гравий',
  lava: 'Лава',
  chest: 'Сундук',
  mimic: 'Мимик',
  villager: 'Житель',
  relic: 'Подозрительный блок',
  bedrock: 'Бедрок',
  obsidian: 'Обсидиан',
}

/** Что встречается на каждом ярусе — для легенды. */
export const TIER_BLOCKS = {
  1: ['dirt', 'stone', 'coal', 'copper', 'iron', 'redstone', 'lapis', 'gold', 'diamond', 'emerald'],
  2: ['deepslate', 'iron', 'redstone', 'lapis', 'gold', 'amethyst', 'diamond', 'emerald'],
  3: ['netherrack', 'quartz', 'glowstone', 'nether_gold', 'gold', 'debris'],
  4: ['end_stone', 'chorus', 'purpur', 'shulker'],
}

/** Особые блоки: что делают. */
export const SPECIALS = {
  tnt: 'Взрывает соседей бесплатно',
  gravel: 'Осыпается (−1 удар) или открывает пещеру',
  lava: 'Обжигает: −2 удара',
  chest: 'Клад… или мимик, который кусает добычу',
  villager: 'Торгует: удары, динамит, карта',
  relic: 'Артефакт в коллекцию',
  bedrock: 'Не копается',
}

/** Пиксельные картинки поверх блока. */
export const BLOCK_SPRITES = {
  chest: 'mine_chest',
  mimic: 'mine_mimic',
  villager: 'mine_villager',
  relic: 'sparkle',
}

export const UPGRADE_SPRITES = {
  pickaxe: 'mine_pickaxe',
  fortune: 'diamond',
  lantern: 'mine_lantern',
  cart: 'mine_cart',
  dynamite: 'mine_dynamite',
}

export const ARTIFACT_SPRITES = {
  coin: 'art_coin',
  fossil: 'art_fossil',
  compass: 'art_compass',
  map: 'art_map',
  heart: 'art_heart',
  totem: 'totem',
  star: 'art_star',
  egg: 'art_egg',
}

export const TRADE_SPRITES = {
  strikes: 'mine_pickaxe',
  dynamite: 'mine_dynamite',
  map: 'art_map',
}

/** Подсказки фонаря о скрытом блоке. */
export const HINTS = {
  danger: 'Опасно: лава или гравий',
  wall: 'Бедрок',
  special: 'Что-то интересное',
}

/** Склонение: 1 удар, 2 удара, 5 ударов. */
export function plural(n, one, few, many) {
  const mod10 = n % 10
  const mod100 = n % 100
  if (mod10 === 1 && mod100 !== 11) return one
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return few
  return many
}

const strikes = (n) => `${n} ${plural(n, 'удар', 'удара', 'ударов')}`

/**
 * Событие забега → строка для журнала и всплывашки.
 * tone: good — радость, bad — потеря, info — нейтрально.
 */
export function eventText(e) {
  switch (e.type) {
    case 'combo': return { text: `Комбо ×${e.combo} · добыча ×${e.mult.toFixed(2)}`, tone: 'good' }
    case 'combo_lost': return { text: `Комбо ×${e.combo} прервано`, tone: 'info' }
    case 'blast': return { text: 'Бабах! Взрыв открыл соседей', tone: 'good' }
    case 'lava': return { text: `Лава! Минус ${strikes(e.lost)}`, tone: 'bad' }
    case 'collapse': return { text: `Гравий осыпался: минус ${strikes(e.lost)}`, tone: 'bad' }
    case 'cave': return { text: 'Гравий ушёл вниз — открылась пещера!', tone: 'good' }
    case 'wall': return { text: 'Бедрок — киркой не взять', tone: 'info' }
    case 'wall_break': return { text: 'Незеритовая кирка расколола бедрок', tone: 'good' }
    case 'mimic': return { text: `Мимик! Откусил ${e.lost} монет добычи`, tone: 'bad' }
    case 'loot': return { text: `Сундук: +${e.coins} монет`, tone: 'good' }
    case 'loot_dynamite': return { text: 'В сундуке динамит: +1 заряд', tone: 'good' }
    case 'relic': return e.new
      ? { text: `Новый артефакт: ${e.name}!`, tone: 'good' }
      : { text: `${e.name} уже есть — продан за ${e.coins}`, tone: 'info' }
    case 'trade': return { text: `Сделка с жителем за ${e.cost} монет`, tone: 'info' }
    case 'descend': return { text: `Спуск на ярус ${e.tier}: ${e.name}`, tone: 'good' }
    default: return null
  }
}

/** Секунды → «4 мин 05 с» / «45 с». */
export function formatSeconds(total) {
  const s = Math.max(0, Math.ceil(total))
  if (s < 60) return `${s} с`
  const m = Math.floor(s / 60)
  return `${m} мин ${String(s % 60).padStart(2, '0')} с`
}
