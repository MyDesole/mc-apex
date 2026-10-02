/**
 * Проверка настроек подключения к Reverb.
 *
 * Эти ошибки были незаметны: страница грузилась, канал подписывался, а
 * соединение при этом не устанавливалось, и события не приходили.
 *
 * Тест читает исходник echo.js и проверяет то, что ломалось:
 *   - адрес берётся из окружения, а не захардкожен;
 *   - заданы ОБА порта. pusher-js строит защищённый адрес из wsPort,
 *     поэтому при одном только wssPort получалось wss://хост:80;
 *   - транспорты не ограничены списком. Ограничение ломало проверку
 *     поддержки, и соединение не создавалось вовсе: состояние сразу
 *     становилось failed, без единого запроса к серверу.
 */
import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const source = readFileSync(resolve(here, '../echo.js'), 'utf8')

/** Убирает комментарии, чтобы не ловить примеры из пояснений. */
const code = source.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '')

describe('Подключение к Reverb', () => {
  it('адрес берётся из окружения', () => {
    expect(code).toContain('VITE_REVERB_HOST')
    expect(code).toContain('VITE_REVERB_PORT')
    expect(code).toContain('VITE_REVERB_SCHEME')
    expect(code).toContain('VITE_REVERB_APP_KEY')
  })

  it('localhost не захардкожен', () => {
    expect(code).not.toMatch(/wsHost:\s*['"]localhost['"]/)
    expect(code).not.toMatch(/wsPort:\s*80\b/)
    expect(code).not.toMatch(/wssPort:\s*443\b/)
  })

  it('заданы оба порта — ws и wss', () => {
    /*
     * Без явного wsPort библиотека подставляет 80, и защищённое
     * соединение уходит на этот порт: wss://хост:80 не работает.
     */
    expect(code, 'wsPort должен задаваться явно').toMatch(/wsPort:\s*port/)
    expect(code, 'wssPort должен задаваться явно').toMatch(/wssPort:\s*port/)
  })

  it('оба порта берут одно значение', () => {
    const ws = code.match(/wsPort:\s*([A-Za-z_$][\w$]*)/)
    const wss = code.match(/wssPort:\s*([A-Za-z_$][\w$]*)/)

    expect(ws, 'wsPort не найден').toBeTruthy()
    expect(wss, 'wssPort не найден').toBeTruthy()
    expect(ws[1], 'порты должны совпадать').toBe(wss[1])
  })

  it('транспорты не ограничены списком', () => {
    /*
     * Перечисление enabledTransports сужает список транспортов, и
     * проверка поддержки стратегии проваливается. Тогда pusher-js
     * ставит failed, не создавая соединение.
     */
    expect(code, 'enabledTransports ломает подключение').not.toContain('enabledTransports')
  })

  it('защита соединения задаётся через forceTLS', () => {
    expect(code).toMatch(/forceTLS:\s*secure/)
  })

  it('подключён канал авторизации', () => {
    expect(code).toContain('/broadcasting/auth')
  })
})
