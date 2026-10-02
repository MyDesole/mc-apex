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

/*
 * Прокрутка контейнера. jsdom её не реализует, а списки сообщений
 * вызывают scrollTo при появлении новых записей — без заглушки тесты
 * завершались с необработанной ошибкой.
 */
if (!window.HTMLElement.prototype.scrollTo) {
  window.HTMLElement.prototype.scrollTo = () => {}
}
if (!window.HTMLElement.prototype.scrollIntoView) {
  window.HTMLElement.prototype.scrollIntoView = () => {}
}

if (!navigator.clipboard) {
  navigator.clipboard = { writeText: () => Promise.resolve(), readText: () => Promise.resolve('') }
}

if (!URL.createObjectURL) URL.createObjectURL = () => 'blob:test'
if (!URL.revokeObjectURL) URL.revokeObjectURL = () => {}

/*
 * Сеть.
 *
 * Компоненты при монтировании сразу запрашивают данные. В Node
 * относительный адрес вида /api/clans не разбирается (ERR_INVALID_URL),
 * и такие запросы копились как необработанные ошибки — прогон падал с
 * «Errors: 20», хотя отдельные тесты были зелёными.
 *
 * Отвечаем пустым объектом: тестам важна разметка, а не данные.
 * Проверки, которым нужен ответ, подменяют fetch сами.
 */
/*
 * Никаких обращений к сервисам при монтировании быть не должно, но
 * страховка полезна: неизвестный метод чата не сломает тест.
 */
if (!window.Echo) {
  window.Echo = {
    private: () => ({ listen: () => {} }),
    join: () => ({ here: () => {}, joining: () => {}, leaving: () => {} }),
    leave: () => {},
  }
}

globalThis.fetch = vi.fn(() =>
  Promise.resolve({
    ok: true,
    status: 200,
    statusText: 'OK',
    headers: new Map(),
    json: () => Promise.resolve({}),
    text: () => Promise.resolve(''),
    blob: () => Promise.resolve(new Blob()),
    clone() {
      return this
    },
  })
)

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
