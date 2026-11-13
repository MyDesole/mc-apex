import { afterEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/services/core/api.js'
import { NETWORK_ERROR, formError, humanError } from '@/services/core/errors.js'
import { notifyError, toastState } from '@/utils/toast.js'

describe('humanError: текст ошибки для игрока', () => {
  it('оставляет русское сообщение сервера', () => {
    expect(humanError(403, { message: 'Вы не в клане.' })).toBe('Вы не в клане.')
  })

  it('заменяет английский текст фреймворка', () => {
    expect(humanError(401, { message: 'Unauthenticated.' })).toBe('Нужно войти в аккаунт.')
    expect(humanError(419, { message: 'CSRF token mismatch.' })).toMatch(/Сессия устарела/)
    expect(humanError(429, { message: 'Too Many Attempts.' })).toMatch(/Слишком много запросов/)
  })

  it('не пересказывает ошибки сервера, даже русские', () => {
    expect(humanError(500, { message: 'SQLSTATE[42000]: ошибка' })).toMatch(/сломалось на нашей стороне/)
  })

  it('для 422 берёт первую ошибку поля, а не «и ещё 2 ошибки»', () => {
    const data = { message: 'Заполните поле «ник». (и ещё ошибок: 2)', errors: { username: ['Заполните поле «ник».'] } }

    expect(humanError(422, data)).toBe('Заполните поле «ник».')
  })

  it('пустой ответ (nginx, HTML) получает текст по коду', () => {
    expect(humanError(413, {})).toMatch(/Файл слишком большой/)
    expect(humanError(418, {})).toMatch(/Не получилось/)
  })
})

describe('formError: без дубля ошибки под полем', () => {
  const error = { message: 'Этот email уже зарегистрирован.', errors: { email: ['Этот email уже зарегистрирован.'] } }

  it('прячет общий текст, если он уже показан под полем', () => {
    expect(formError(error, ['email'], 'запасной')).toBe('')
  })

  it('показывает общий текст, если поля в форме нет', () => {
    expect(formError(error, ['code'], 'запасной')).toBe('Этот email уже зарегистрирован.')
    expect(formError({}, ['code'], 'запасной')).toBe('запасной')
  })
})

describe('api: сбой сети', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('вместо «Failed to fetch» бросает понятную ошибку', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))

    await expect(api.get('/clans')).rejects.toMatchObject({ status: 0, message: NETWORK_ERROR })
  })
})

describe('notifyError: всплывающее сообщение', () => {
  afterEach(() => toastState.items.splice(0))

  it('показывает текст ошибки API один раз', () => {
    const error = { status: 403, message: 'Вы не в клане.' }

    notifyError(error)
    notifyError(error)

    expect(toastState.items.map((item) => item.message)).toEqual(['Вы не в клане.'])
  })

  it('для сбоя в коде страницы показывает запасной текст, а не исключение', () => {
    notifyError(new TypeError("Cannot read properties of undefined (reading 'count')"), 'Не удалось загрузить.')

    expect(toastState.items[0].message).toBe('Не удалось загрузить.')
  })

  it('молчит про 401 и обновление сайта', () => {
    notifyError({ status: 401, message: 'Нужно войти в аккаунт.' })
    notifyError({ status: 503, message: 'Сайт обновляется.' })

    expect(toastState.items).toEqual([])
  })
})
