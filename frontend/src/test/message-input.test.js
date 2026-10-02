/**
 * Проверка поля ввода сообщения.
 *
 * Поле должно подстраивать высоту под текст: без этого длинное сообщение
 * не видно, а писать его неудобно. Проверяется, что обработчик ввода
 * подключён и что высота выставляется по содержимому.
 */
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('@/services/chat/chat.js', () => ({
  chatApi: {
    conversations: vi.fn(),
    show: vi.fn(),
    unreadCount: vi.fn().mockResolvedValue({ count: 0 }),
    send: vi.fn(),
    markConversationRead: vi.fn(),
    search: vi.fn(),
    uploadMany: vi.fn(),
    upload: vi.fn(),
    presence: vi.fn().mockResolvedValue({ online: [] }),
    presenceOnline: vi.fn().mockResolvedValue({ online: [] }),
    presenceOffline: vi.fn().mockResolvedValue({ ok: true }),
  },
}))

import Messages from '@/views/chat/Messages.vue'
import { chatApi } from '@/services/chat/chat.js'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/messages/:id?', name: 'messages', component: { template: '<div />' } }],
})

/** Данные, при которых страница показывает поле ввода. */
function stubConversation() {
  const conversation = {
    id: 1,
    type: 'direct',
    title: 'Друг',
    users: [
      { id: 1, username: 'me' },
      { id: 2, username: 'friend' },
    ],
  }

  chatApi.conversations.mockResolvedValue({ conversations: [conversation] })
  chatApi.show.mockResolvedValue({ conversation, messages: [] })
}

/** Монтирует страницу сообщений с активным диалогом. */
async function mountMessages() {
  setActivePinia(createPinia())
  stubConversation()

  await router.push('/messages/1')
  await router.isReady()

  const wrapper = mount(Messages, {
    global: { plugins: [createPinia(), router], stubs: { Teleport: true } },
  })

  await flushPromises()
  await flushPromises()

  return wrapper
}

describe('Поле ввода сообщения', () => {
  beforeEach(() => vi.clearAllMocks())

  it('подстраивает высоту под содержимое', async () => {
    const wrapper = await mountMessages()
    const field = wrapper.find('textarea.chat__input')

    expect(field.exists(), 'поле ввода отрисовано').toBe(true)

    const el = field.element

    /* jsdom не считает раскладку: задаём измерение вручную */
    Object.defineProperty(el, 'scrollHeight', { value: 120, configurable: true })

    await field.trigger('input')
    await flushPromises()

    expect(el.style.height, 'высота выставлена по содержимому').toBe('120px')
  })

  it('сбрасывает высоту перед измерением', async () => {
    const wrapper = await mountMessages()
    const field = wrapper.find('textarea.chat__input')
    const el = field.element

    /*
     * Без сброса в auto значение scrollHeight не уменьшается при удалении
     * текста, и поле не сжимается обратно.
     */
    const heights = []

    Object.defineProperty(el.style, 'height', {
      configurable: true,
      get: () => heights.at(-1) ?? '',
      set: (value) => heights.push(value),
    })

    Object.defineProperty(el, 'scrollHeight', { value: 80, configurable: true })

    await field.trigger('input')
    await flushPromises()

    expect(heights, 'сначала сброс, затем измеренная высота').toEqual(['auto', '80px'])
  })
})
