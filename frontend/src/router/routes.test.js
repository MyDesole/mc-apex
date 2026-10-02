/**
 * Проверка маршрутов.
 *
 * Ловит класс ошибок, который не видит сборка: ссылки на несуществующие
 * страницы. Именно так в интерфейсе появились /profile/{id} и
 * /api/user/{ник} — они вели в никуда, но `npm run build` проходил.
 */
import { describe, expect, it } from 'vitest'
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'

const SRC = join(process.cwd(), 'src')
const ROUTER_FILE = join(SRC, 'router', 'index.js')

/** Все файлы фронтенда, где могут быть ссылки. */
function sourceFiles(dir = SRC, acc = []) {
  for (const name of readdirSync(dir)) {
    if (name === 'node_modules' || name === 'test') continue

    const full = join(dir, name)

    if (statSync(full).isDirectory()) sourceFiles(full, acc)
    else if (/\.(vue|js)$/.test(name)) acc.push(full)
  }

  return acc
}

const routerSource = readFileSync(ROUTER_FILE, 'utf8')

/** Шаблоны путей из определения роутера: '/messages/:id?' и прочие. */
const knownPaths = [...routerSource.matchAll(/path:\s*['"]([^'"]+)['"]/g)]
  .map((m) => m[1])

const knownNames = new Set(
  [...routerSource.matchAll(/name:\s*['"]([^'"]+)['"]/g)].map((m) => m[1])
)

/**
 * Совпадает ли конкретный путь с шаблоном роутера.
 *
 * Учитывает параметры (:id) и необязательные сегменты (:id?):
 * /messages/:id? принимает и /messages, и /messages/42.
 */
function matches(template, path) {
  /*
   * В шаблоне '?' означает необязательный сегмент (/messages/:id?),
   * а в ссылке — начало query-строки. Поэтому чистим только ссылку.
   */
  const cleanPath = (value) => (value.split('?')[0].replace(/\/+$/, '') || '/')

  const t = template.replace(/\/+$/, '') || '/'
  const p = cleanPath(path)

  if (t === p) return true

  const segments = t.split('/').filter(Boolean).map((part) => ({
    optional: part.endsWith('?'),
    isParam: part.startsWith(':'),
    // ':id?' -> ':id', 'create' -> 'create'
    literal: part.startsWith(':') ? part : part.replace(/\?$/, ''),
  }))

  const parts = p.split('/').filter(Boolean)

  /** Перебор с учётом возможности пропустить необязательный сегмент. */
  function walk(segIndex, partIndex) {
    if (segIndex === segments.length) return partIndex === parts.length

    const segment = segments[segIndex]

    if (partIndex < parts.length && (segment.isParam || segment.literal === parts[partIndex])) {
      if (walk(segIndex + 1, partIndex + 1)) return true
    }

    if (segment.optional && walk(segIndex + 1, partIndex)) return true

    return false
  }

  return walk(0, 0)
}

/** Есть ли в роутере путь, которому соответствует эта ссылка. */
function isKnown(path) {
  return knownPaths.some((template) => matches(template, path))
}

const files = sourceFiles()

describe('маршруты', () => {
  it('основные страницы объявлены', () => {
    // Проверяем по соответствию, а не по буквальному совпадению:
    // у /messages маршрут объявлен как /messages/:id?
    const pages = [
      ['/', 'главная'],
      ['/clans', 'список кланов'],
      ['/my-clan', 'мой клан'],
      ['/shop', 'магазин'],
      ['/inventory', 'инвентарь'],
      ['/messages', 'сообщения'],
      ['/friends', 'друзья'],
      ['/forum', 'форум'],
      ['/players', 'игроки'],
      ['/profile', 'профиль'],
      ['/wallet', 'кошелёк'],
      ['/notifications', 'уведомления'],
      ['/news', 'новости'],
      ['/tournaments', 'турниры'],
      ['/admin', 'админка'],
    ]

    const missing = pages
      .filter(([path]) => !isKnown(path))
      .map(([path, label]) => `${label} (${path})`)

    expect(missing, `Не объявлены страницы: ${missing.join(', ')}`).toEqual([])
  })

  it('ссылки ведут на существующие маршруты', () => {
    const problems = []

    for (const file of files) {
      // Файлы тестов содержат примеры путей
      if (file.endsWith('.test.js')) continue

      const src = readFileSync(file, 'utf8')
      const rel = relative(SRC, file)

      const patterns = [
        /:to=["'`](\/[^"'`$]*)/g,
        /router\.push\(["'`](\/[^"'`$]*)/g,
        /router\.replace\(["'`](\/[^"'`$]*)/g,
      ]

      for (const pattern of patterns) {
        for (const m of src.matchAll(pattern)) {
          const path = m[1]

          // Внешние ссылки и файлы не проверяем
          if (path.startsWith('//')) continue
          if (path.startsWith('/api/') || path.startsWith('/storage/')) continue

          if (!isKnown(path)) {
            problems.push(`${rel}: ${path}`)
          }
        }
      }
    }

    expect(
      [...new Set(problems)],
      'Ссылки ведут на маршруты, которых нет в роутере'
    ).toEqual([])
  })

  it('переходы по имени используют объявленные имена', () => {
    const problems = []

    for (const file of files) {
      if (file.endsWith('.test.js')) continue

      const src = readFileSync(file, 'utf8')
      const rel = relative(SRC, file)

      for (const m of src.matchAll(/name:\s*['"]([a-zA-Z0-9-]+)['"]/g)) {
        // Интересуют только переходы: :to="{ name: ... }" и router.push({ name })
        const context = src.slice(Math.max(0, m.index - 80), m.index)

        if (!/:to=|router\.push\(|router\.replace\(/.test(context)) continue
        if (knownNames.has(m[1])) continue

        problems.push(`${rel}: ${m[1]}`)
      }
    }

    expect(
      [...new Set(problems)],
      'Переходы по имени ссылаются на несуществующие маршруты'
    ).toEqual([])
  })

  it('нет дублей путей', () => {
    const seen = new Set()
    const duplicates = []

    for (const path of knownPaths) {
      if (seen.has(path)) duplicates.push(path)
      seen.add(path)
    }

    expect(duplicates, `Пути объявлены дважды: ${duplicates.join(', ')}`).toEqual([])
    expect(knownPaths.length).toBeGreaterThan(20)
  })

  it('сопоставление путей понимает параметры и необязательные сегменты', () => {
    // Сами правила: если сломаются, тесты выше начнут пропускать ошибки
    expect(matches('/messages/:id?', '/messages')).toBe(true)
    expect(matches('/messages/:id?', '/messages/42')).toBe(true)
    expect(matches('/user/:id', '/user/42')).toBe(true)
    expect(matches('/user/:id', '/user')).toBe(false)
    expect(matches('/clans/:id', '/clans/create')).toBe(true)
    expect(matches('/shop', '/shop/42')).toBe(false)
    expect(matches('/', '/')).toBe(true)
  })
})
