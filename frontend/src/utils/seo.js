/**
 * Мета-теги страницы для поисковиков и превью: описание, canonical,
 * og:title / og:description / og:url. Обновляются при каждом переходе.
 */

const SITE = 'https://mc-apex.ru'

const DEFAULT_DESCRIPTION = 'APEX TIERS — рейтинг игроков Minecraft PvP и бриджа: тир-тесты от S до E, кланы и клановые войны, турниры с призами, сезоны и профили с косметикой.'

/** Описания открытых разделов (по имени маршрута). */
export const ROUTE_DESCRIPTIONS = {
  home: DEFAULT_DESCRIPTION,
  players: 'Рейтинг игроков Minecraft PvP по тирам: кто в S, A и B, очки тира и места в топе.',
  clans: 'Кланы Minecraft PvP на APEX TIERS: рейтинг кланов, клановые войны, состав и уровни.',
  news: 'Новости APEX TIERS: обновления сайта, события, сезоны и турниры.',
  tournaments: 'Турниры по Minecraft PvP и бриджу с призами: расписание, регистрация, участники и победители.',
  respects: 'Самые уважаемые игроки недели — у кого больше всего респектов. Тройка лидеров получает награды.',
  shop: 'Магазин косметики APEX TIERS: рамки, эффекты профиля и ника, питомцы, макеты профиля.',
  cases: 'Сундуки с косметикой: ежедневный бесплатный, сундук искателя, королевский и сундук питомцев.',
  auction: 'Аукцион уникальных питомцев: ставки с таймером, редкие окрасы, фоны и аксессуары.',
  changelog: 'Что нового на APEX TIERS: история обновлений сайта.',
  stats: 'Статистика APEX TIERS: игроки, тир-тесты и распределение по тирам.',
  about: 'О проекте APEX TIERS: как работают тир-тесты, кто тестеры и как попасть в рейтинг.',
}

function meta(attr, key, content) {
  let el = document.head.querySelector(`meta[${attr}="${key}"]`)
  if (!el) {
    el = document.createElement('meta')
    el.setAttribute(attr, key)
    document.head.appendChild(el)
  }
  el.setAttribute('content', content)
}

function canonical(href) {
  let el = document.head.querySelector('link[rel="canonical"]')
  if (!el) {
    el = document.createElement('link')
    el.setAttribute('rel', 'canonical')
    document.head.appendChild(el)
  }
  el.setAttribute('href', href)
}

export function applySeo(to, title) {
  const description = to.meta?.description || ROUTE_DESCRIPTIONS[to.name] || DEFAULT_DESCRIPTION
  const url = SITE + (to.path === '/' ? '/' : to.path.replace(/\/$/, ''))

  meta('name', 'description', description)
  meta('property', 'og:title', title)
  meta('property', 'og:description', description)
  meta('property', 'og:url', url)
  canonical(url)
}
