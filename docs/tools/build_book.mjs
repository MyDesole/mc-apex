/**
 * Сборка Markdown-версии книги APEX из глав в docs/book/.
 *
 *   node docs/tools/build_book.mjs
 *
 * Что делает:
 *   1. читает главы в порядке, заданном ниже;
 *   2. выставляет заголовки частей (# Часть …) и порядок приложений;
 *   3. склеивает в один файл docs/APEX-Книга.md;
 *   4. строит оглавление по частям и разделам.
 *
 * Главы-справочники (файлы, чьё имя начинается с 1x-) идут без нумерации:
 * это справочник, а не последовательное чтение. Их подразделы в оглавлении
 * сдвигаются вправо.
 *
 * Запускать после правок в docs/book/. Дальше — build.ps1, он сделает DOCX и PDF.
 */
import { readFileSync, writeFileSync, readdirSync, existsSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const TOOLS = dirname(fileURLToPath(import.meta.url))
const DOCS = join(TOOLS, '..')
const SRC = join(DOCS, 'book')
const OUT = join(DOCS, 'APEX-Книга.md')

/** Главы до справочника: файл и заголовок части. */
const ORDER = [
  { file: '00-введение.md', part: 'Часть I. Проект целиком' },
  { file: '01-архитектура-бэкенда.md', part: 'Часть II. Бэкенд' },
  { file: '02-фронтенд.md', part: 'Часть III. Фронтенд' },
]

/** Главы-справочники: всё, что начинается с 1x-, в алфавитном порядке. */
function domainChapters() {
  return readdirSync(SRC)
    .filter((f) => /^1\d-.*\.md$/.test(f))
    .sort()
    .map((file) => ({ file, part: null }))
}

/** Хвост книги: после справочника. */
const TAIL = [
  { file: '04-разработка.md', part: 'Часть V. Разработка' },
  { file: '90-приложение-схема.md', part: 'Приложения' },
  { file: '80-остальные-домены.md', part: null },
  { file: '91-приложение-шпаргалка.md', part: null },
  { file: '92-приложение-числа.md', part: null },
]

const REFERENCE_INTRO = `# Часть IV. Справочник механик

Здесь разобраны все предметные области сайта по отдельности. В отличие от
предыдущих частей, эти главы **не нумерованы** — это справочник, а не
последовательное чтение. Нумерация остальных глав при этом непрерывна:
1–14 идут до справочника, 15–28 после.

Отдельно стоит глава про питомцев: там разобрана боевая система арены,
экспедиции, перки и питомцедекс — с точными числами из кода.
`

/** Ставит нужный заголовок части: заменяет существующий или дописывает. */
function setPart(text, part) {
  if (!part) return text

  const header = `# ${part}`

  if (/^# (Часть .+|Приложения)$/m.test(text)) {
    return text.replace(/^# (Часть .+|Приложения)$/m, header)
  }

  return `${header}\n\n${text.replace(/^\s*---\s*\n/, '')}`
}

/** Убирает ведущие разделители, чтобы они не копились при склейке. */
function trimLead(text) {
  return text.replace(/^\s*(---\s*\n)+/, '').trimStart()
}

function main() {
  const chapters = [...ORDER, ...domainChapters(), ...TAIL]
  const missing = chapters.filter((c) => !existsSync(join(SRC, c.file)))

  if (missing.length) {
    console.error('  НЕТ ФАЙЛОВ:', missing.map((m) => m.file).join(', '))
  }

  const parts = []
  let referenceDone = false

  for (const chapter of chapters) {
    const path = join(SRC, chapter.file)
    if (!existsSync(path)) continue

    let text = readFileSync(path, 'utf8').replace(/\r\n/g, '\n')
    const isDomain = /^1\d-/.test(chapter.file)

    text = trimLead(text)

    if (isDomain) {
      if (!referenceDone) {
        parts.push(REFERENCE_INTRO)
        referenceDone = true
      }
    } else {
      text = setPart(text, chapter.part)
    }

    parts.push(`${text.trim()}\n`)
  }

  const body = parts.join('\n---\n\n')

  /* Оглавление: части и разделы, подразделы справочника — с отступом */
  const tocLines = []
  let inUnnumberedChapter = false

  for (const line of body.split('\n')) {
    if (/^# (Часть .+|Приложения)$/.test(line)) {
      tocLines.push('', `**${line.replace(/^# /, '')}**`, '')
      inUnnumberedChapter = false

      continue
    }

    if (/^## /.test(line)) {
      const title = line.replace(/^## /, '')
      const anchor = title
        .toLowerCase()
        .replace(/[^\p{L}\p{N}\s-]/gu, '')
        .trim()
        .replace(/\s+/g, '-')

      const numbered = /^\d+\.\s/.test(title)

      if (inUnnumberedChapter && numbered) {
        tocLines.push(`  - [${title}](#${anchor})`)

        continue
      }

      tocLines.push(`- [${title}](#${anchor})`)
      inUnnumberedChapter = !numbered
    }
  }

  const header = `# APEX — полная документация проекта

**Веб-платформа: сайт mc-apex.ru**

---

## Оглавление

${tocLines.join('\n').trim()}

---
`

  writeFileSync(OUT, `${header}\n${body}`)

  const lines = `${header}\n${body}`.split('\n').length
  const kb = Math.round(Buffer.byteLength(`${header}\n${body}`, 'utf8') / 1024)

  console.log(`  глав: ${parts.length}`)
  console.log(`  разделов: ${(body.match(/^## .+$/gm) || []).length}`)
  console.log(`  строк: ${lines}, объём: ${kb} КБ`)
  console.log(`  файл: docs/APEX-Книга.md`)
}

main()
