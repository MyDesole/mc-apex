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
    // Ввод текста сообщает собеседнику «печатает»
    typing: vi.fn().mockResolvedValue({ ok: true }),
    markConversationRead: vi.fn(),
    search: vi.fn(),
    uploadMany: vi.fn(),
    upload: vi.fn(),
    presence: vi.fn().mockResolvedValue({ online: [] }),
    presenceOnline: vi.fn().mockResolvedValue({ online: [] }),
    presenceOffline: vi.fn().mockResolvedValue({ ok: true }),
  },
}))

vi.mock('@/echo.js', async () => ({ echoGeneration: (await import('vue')).ref(0), onEchoReset: () => () => {} }))

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
  chatApi.show.mockImplementation(async (id) => ({ conversation: { ...conversation, id }, messages: [] }))
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

  it('does not insert a pending send into another conversation', async () => {
    const wrapper = await mountMessages()
    let resolve
    chatApi.send.mockReturnValue(new Promise(r => { resolve = r }))
    await wrapper.find('textarea.chat__input').setValue('For chat one')
    await wrapper.find('form.chat__form').trigger('submit')
    await router.push('/messages/2')
    await flushPromises()
    resolve({ message: { id: 90, conversation_id: 1, body: 'For chat one', user: { id: 1 } } })
    await flushPromises()
    expect(wrapper.find('.chat__messages').text()).not.toContain('For chat one')
    expect(chatApi.send).toHaveBeenCalledWith(1, 'For chat one', null, [])
    wrapper.unmount()
  })

  it('restores a failed send to its original draft without replacing another draft', async () => {
    const wrapper = await mountMessages()
    let reject
    chatApi.send.mockReturnValue(new Promise((_, r) => { reject = r }))
    await wrapper.find('textarea.chat__input').setValue('Original text')
    await wrapper.find('form.chat__form').trigger('submit')
    await router.push('/messages/2')
    await flushPromises()
    await wrapper.find('textarea.chat__input').setValue('Second chat draft')
    reject(new Error('Network unavailable'))
    await flushPromises()
    expect(wrapper.find('textarea.chat__input').element.value).toBe('Second chat draft')
    await router.push('/messages/1')
    await flushPromises()
    expect(wrapper.find('textarea.chat__input').element.value).toBe('Original text')
    wrapper.unmount()
  })

  it('ignores a late opening response after returning to the conversation list', async () => {
    const wrapper = await mountMessages()
    let resolve
    chatApi.show.mockReturnValueOnce(new Promise(r => { resolve = r }))
    await router.push('/messages/2')
    await flushPromises()
    await router.push('/messages')
    await flushPromises()
    resolve({ conversation: { id: 2, type: 'direct', users: [] }, messages: [] })
    await flushPromises()
    expect(wrapper.find('textarea.chat__input').exists()).toBe(false)
    wrapper.unmount()
  })

  it('blocks repeated submits while the previous message is pending', async () => {
    const wrapper = await mountMessages()
    let resolve
    chatApi.send.mockReturnValue(new Promise(r => { resolve = r }))
    await wrapper.find('textarea.chat__input').setValue('First')
    await wrapper.find('form.chat__form').trigger('submit')
    await wrapper.find('textarea.chat__input').setValue('Next draft')
    await wrapper.find('form.chat__form').trigger('submit')
    expect(chatApi.send).toHaveBeenCalledTimes(1)
    resolve({ message: { id: 95, conversation_id: 1, body: 'First', user: { id: 1 } } })
    await flushPromises()
    expect(wrapper.find('textarea.chat__input').element.value).toBe('Next draft')
    wrapper.unmount()
  })

  it('does not send Enter while composing text with an IME', async () => {
    const wrapper = await mountMessages()
    const field = wrapper.find('textarea.chat__input')
    await field.setValue('Composing')
    await field.trigger('keydown', { key: 'Enter', isComposing: true })
    expect(chatApi.send).not.toHaveBeenCalled()
    wrapper.unmount()
  })

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
