/**
 * Проверка, что все компоненты, используемые в шаблонах, импортированы.
 *
 * Сборка и монтирование эту ошибку не ловят: Vue ругается
 * предупреждением «Failed to resolve component» и просто рисует
 * пустое место. Так уже случилось при выносе пьедестала —
 * RatingPodium использовал RatingMountainBackground без импорта,
 * а сборка проходила.
 */
import { describe, expect, it } from 'vitest'
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'

const SRC = join(process.cwd(), 'src')

/** Глобально доступные компоненты Vue и Vue Router. */
const GLOBALS = new Set([
  'RouterLink', 'RouterView', 'Transition', 'TransitionGroup',
  'Teleport', 'KeepAlive', 'Suspense', 'Component',
])

function vueFiles(dir = SRC, acc = []) {
  for (const name of readdirSync(dir)) {
    if (name === 'node_modules' || name === 'test') continue

    const full = join(dir, name)

    if (statSync(full).isDirectory()) vueFiles(full, acc)
    else if (name.endsWith('.vue')) acc.push(full)
  }

  return acc
}

/** Все имена, которые объявлены в блоке script. */
function declaredNames(script) {
  const names = new Set()

  // import X from ... / import { X } from ... / import X, { Y } from ...
  for (const m of script.matchAll(/import\s+([^'"]+?)\s+from\s+['"]/g)) {
    const clause = m[1]

    // Значение по умолчанию
    const def = clause.match(/^([A-Za-z_$][\w$]*)/)
    if (def) names.add(def[1])

    // Именованные
    for (const n of clause.matchAll(/\{([^}]*)\}/g)) {
      for (const part of n[1].split(',')) {
        const name = part.trim().split(/\s+as\s+/).pop()?.trim()
        if (name) names.add(name)
      }
    }
  }

  // Локальные объявления: const X = ..., function X(...)
  for (const m of script.matchAll(/(?:const|let|var)\s+([A-Za-z_$][\w$]*)\s*=/g)) {
    names.add(m[1])
  }

  for (const m of script.matchAll(/function\s+([A-Za-z_$][\w$]*)\s*\(/g)) {
    names.add(m[1])
  }

  return names
}

describe('компоненты в шаблонах импортированы', () => {
  const files = vueFiles()

  it('файлы найдены', () => {
    expect(files.length).toBeGreaterThan(50)
  })

  for (const file of files) {
    const rel = relative(SRC, file)

    it(rel, () => {
      const src = readFileSync(file, 'utf8')

      const scriptEnd = src.indexOf('<template>')
      const script = scriptEnd > 0 ? src.slice(0, scriptEnd) : src
      const template = scriptEnd > 0 ? src.slice(scriptEnd) : ''

      if (!template) return

      const declared = declaredNames(script)

      // Теги с заглавной буквы или через дефис: <Foo>, <foo-bar>
      const tags = new Set()

      for (const m of template.matchAll(/<([A-Z][A-Za-z0-9]*)[\s/>]/g)) tags.add(m[1])
      for (const m of template.matchAll(/<([a-z][a-z0-9]*-[a-z0-9-]+)[\s/>]/g)) {
        // Кастомные элементы через дефис: FooBar -> foo-bar
        tags.add(m[1])
      }

      // Компонент может ссылаться на самого себя: дерево ответов
      // ForumReplyNode рисует вложенные ForumReplyNode
      const selfName = rel.split('/').pop().replace('.vue', '')

      const missing = []

      for (const tag of tags) {
        if (GLOBALS.has(tag)) continue
        if (tag === selfName) continue

        // PascalCase из kebab-case
        const pascal = tag
          .split('-')
          .map((p) => p.charAt(0).toUpperCase() + p.slice(1))
          .join('')

        if (declared.has(tag) || declared.has(pascal)) continue

        missing.push(tag)
      }

      expect(
        missing,
        `Не импортированы компоненты: ${missing.join(', ')}`
      ).toEqual([])
    })
  }
})
