/**
 * Сборка всей документации APEX: Markdown → DOCX → PDF.
 *
 *   node docs/tools/build.mjs
 *
 * Почему Node, а не PowerShell: скрипт уже гоняет node (сборка книги) и
 * node-CLI LibreOffice, а PowerShell 5.1 ломается на UTF-8 без BOM и
 * упирается в политику выполнения. Один рантайм вместо трёх.
 *
 * Инструменты ищутся так:
 *   1. переменные окружения APEX_NODE / APEX_PYTHON / APEX_LO_CLI;
 *   2. известные пути рантайма DSH;
 *   3. PATH.
 */
import { spawnSync } from 'node:child_process'
import { existsSync, rmSync, statSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const TOOLS = dirname(fileURLToPath(import.meta.url))
const DOCS = join(TOOLS, '..')
const NODE = process.execPath

/*
 * Куда класть PDF. По умолчанию рядом с документами, но можно увести в
 * сторону: удобно, когда готовый PDF открыт в просмотрщике и Windows не
 * даёт его перезаписать — тогда сборка идёт мимо, а файл подменяется
 * вручную.
 */
const PDF_DIR = process.env.APEX_PDF_DIR || DOCS

const DSH_PYTHON = 'C:\\Users\\n-kol\\.dsh\\dsh-runtimes\\dsh-primary-runtime\\dependencies\\python\\python.exe'
const DSH_LO_CLI = 'C:\\Users\\n-kol\\AppData\\Local\\Programs\\DeepSeek Harness\\resources\\app.asar.unpacked\\dsh\\node_modules\\@deepseek-ai\\libreoffice-kit\\lib\\cli.js'

function resolve(envName, candidates, fallback) {
  if (process.env[envName]) return process.env[envName]

  for (const candidate of candidates) {
    if (existsSync(candidate)) return candidate
  }

  return fallback
}

const PYTHON = resolve('APEX_PYTHON', [DSH_PYTHON], 'python')
const LO_CLI = resolve('APEX_LO_CLI', [DSH_LO_CLI], null)

function run(label, command, args) {
  const result = spawnSync(command, args, {
    stdio: 'inherit',
    env: { ...process.env, PYTHONIOENCODING: 'utf-8' },
  })

  if (result.error) {
    throw new Error(`${label}: ${result.error.message}`)
  }

  if (result.status !== 0) {
    throw new Error(`${label}: код выхода ${result.status}`)
  }
}

/** Документы: md-источник, имя, подзаголовок обложки, подпись колонтитула, режим. */
const DOCUMENTS = [
  {
    name: 'APEX-Книга',
    src: join(DOCS, 'APEX-Книга.md'),
    sub: 'полная документация проекта',
    footer: 'APEX · документация',
    mode: null,
  },
  {
    name: 'APEX-Руководство',
    src: join(DOCS, 'guide', 'Руководство-пользователя.md'),
    sub: 'руководство пользователя',
    footer: 'APEX · руководство',
    // Главы короткие: без разрыва страницы перед каждой
    mode: 'flow',
  },
  {
    name: 'APEX-Памятки',
    src: join(DOCS, 'guide', 'Памятки.md'),
    sub: 'памятки по сайту',
    footer: 'APEX · памятки',
    mode: null,
  },
]

function size(path) {
  try {
    return Math.round(statSync(path).size / 1024)
  } catch {
    return 0
  }
}

function main() {
  console.log('\n==> Книга: склейка глав')
  run('build_book', NODE, [join(TOOLS, 'build_book.mjs')])

  for (const doc of DOCUMENTS) {
    if (!existsSync(doc.src)) {
      console.log(`\n==> ПРОПУСК ${doc.name}: нет исходника ${doc.src}`)

      continue
    }

    const docx = join(DOCS, `${doc.name}.docx`)
    const pdf = join(PDF_DIR, `${doc.name}.pdf`)

    console.log(`\n==> ${doc.name}: DOCX`)

    const args = [join(TOOLS, 'build_docx.py'), doc.src, docx, doc.sub, doc.footer]

    if (doc.mode) args.push(doc.mode)

    run(doc.name, PYTHON, args)

    if (!LO_CLI) {
      console.log(`    PDF пропущен: не найден CLI LibreOffice. Задайте APEX_LO_CLI.`)

      continue
    }

    console.log(`==> ${doc.name}: PDF`)

    /*
     * CLI LibreOffice не перезаписывает готовый файл (падает с EEXIST),
     * поэтому старый PDF удаляем. Если он открыт в просмотрщике —
     * удаление не пройдёт, и об этом лучше сказать прямо.
     */
    try {
      rmSync(pdf, { force: true })
    } catch {
      throw new Error(
        `${doc.name}: не удалось перезаписать PDF — файл открыт в просмотрщике. Закройте его и повторите сборку.`,
      )
    }

    run(doc.name, NODE, [LO_CLI, 'convert', '--input', docx, '--output', pdf])
  }

  console.log('\n==> Готово')

  for (const doc of DOCUMENTS) {
    const pdf = join(PDF_DIR, `${doc.name}.pdf`)
    const docx = join(DOCS, `${doc.name}.docx`)

    console.log(`    ${doc.name}: PDF ${size(pdf)} КБ, DOCX ${size(docx)} КБ`)
  }

  if (PDF_DIR !== DOCS) {
    console.log(`\n    PDF собраны в ${PDF_DIR} — их нужно перенести в ${DOCS}`)
  }
}

main()
