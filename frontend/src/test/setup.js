/**
 * Общее окружение для тестов.
 *
 * jsdom не содержит части браузерных API, которые используют компоненты,
 * поэтому подставляем заглушки.
 */
import { vi } from 'vitest'
import { config } from '@vue/test-utils'

/* --- matchMedia: используется темами и адаптивом --- */
if (!window.matchMedia) {
  window.matchMedia = (query) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false,
  })
}

/* --- IntersectionObserver: виртуальный скролл и ленивые блоки --- */
if (!global.IntersectionObserver) {
  global.IntersectionObserver = class {
    constructor(callback) {
      this.callback = callback
    }

    observe(element) {
      // Сразу сообщаем, что элемент виден: так прогружаются списки
      this.callback([{ isIntersecting: true, target: element }])
    }

    unobserve() {}
    disconnect() {}
    takeRecords() {
      return []
    }
  }
}

/* --- ResizeObserver: графики и подгонка размеров --- */
if (!global.ResizeObserver) {
  global.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  }
}

/* --- Прочее, чего нет в jsdom --- */
if (!window.scrollTo) window.scrollTo = () => {}
if (!window.HTMLElement.prototype.scrollIntoView) {
  window.HTMLElement.prototype.scrollIntoView = () => {}
}

if (!navigator.clipboard) {
  navigator.clipboard = { writeText: () => Promise.resolve(), readText: () => Promise.resolve('') }
}

if (!URL.createObjectURL) URL.createObjectURL = () => 'blob:test'
if (!URL.revokeObjectURL) URL.revokeObjectURL = () => {}

afterEach(() => {
  localStorage.clear()
})

/*
 * Глобальных заглушек здесь намеренно нет.
 *
 * Раньше teleport был заглушён, и это ломало RouterLink: он переставал
 * рисовать содержимое, и компоненты падали с «missing template».
 * Роутер подключается в тестах как плагин, теги оставляем настоящими.
 */
config.global.mocks = {
  $route: { params: {}, query: {}, name: 'test', path: '/' },
  $router: { push: vi.fn(), replace: vi.fn(), back: vi.fn() },
}
