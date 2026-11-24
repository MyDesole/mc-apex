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
    search: vi.fn().mockResolvedValue({ users: [], clans: [] }),
    searchMessages: vi.fn().mockResolvedValue({ messages: [] }),
    updateMessage: vi.fn(),
    uploadMany: vi.fn(),
    upload: vi.fn(),
    presence: vi.fn().mockResolvedValue({ online: [] }),
    presenceOnline: vi.fn().mockResolvedValue({ online: [] }),
    presenceOffline: vi.fn().mockResolvedValue({ ok: true }),
  },
}))

vi.mock('@/echo.js', async () => ({ echoGeneration: (await import('vue')).ref(0), onEchoReset: () => () => {} }))

import { useAuthStore } from '@/stores/core/auth.js'
import Messages from '@/views/chat/Messages.vue'
import { chatApi } from '@/services/chat/chat.js'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/messages/:id?', name: 'messages', component: { template: '<div />' } }],
})

/** Данные, при которых страница показывает поле ввода. */
function stubConversation(history = []) {
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
  chatApi.show.mockImplementation(async (id) => ({ conversation: { ...conversation, id }, messages: id === 1 ? history : [] }))
}

/** Монтирует страницу сообщений с активным диалогом. */
async function mountMessages(history = []) {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore(pinia).user = { id: 1, username: 'me' }
  stubConversation(history)

  await router.push('/messages/1')
  await router.isReady()

  const wrapper = mount(Messages, {
    global: { plugins: [pinia, router], stubs: { Teleport: true } },
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

  it('edits in the composer, saves the change and restores the unsent draft', async () => {
    const original = { id: 41, conversation_id: 1, body: 'Old text', user: { id: 1, username: 'me' }, created_at: new Date().toISOString() }
    const wrapper = await mountMessages([original])
    const input = wrapper.find('textarea.chat__input')
    await input.setValue('Unsent draft')
    await wrapper.find('button[title="Изменить"]').trigger('click')
    expect(input.element.value).toBe('Old text')
    expect(wrapper.find('.msg__edit').exists()).toBe(false)
    expect(wrapper.find('.chat__edit-bar').exists()).toBe(true)
    await input.setValue('Updated text')
    chatApi.updateMessage.mockResolvedValue({ message: { ...original, body: 'Updated text' } })
    await wrapper.find('form.chat__form').trigger('submit')
    await flushPromises()
    expect(chatApi.updateMessage).toHaveBeenCalledWith(41, 'Updated text')
    expect(chatApi.send).not.toHaveBeenCalled()
    expect(input.element.value).toBe('Unsent draft')
    expect(wrapper.find('.chat__messages').text()).toContain('Updated text')
    wrapper.unmount()
  })

  it('keeps editing text on a save failure and restores the draft on cancel', async () => {
    const wrapper = await mountMessages([{ id: 42, conversation_id: 1, body: 'Old text', user: { id: 1, username: 'me' } }])
    await wrapper.find('textarea.chat__input').setValue('Draft to keep')
    await wrapper.find('button[title="Изменить"]').trigger('click')
    await wrapper.find('textarea.chat__input').setValue('Failed edit')
    chatApi.updateMessage.mockRejectedValueOnce(new Error('Network error'))
    await wrapper.find('form.chat__form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('textarea.chat__input').element.value).toBe('Failed edit')
    await wrapper.find('[aria-label="Отменить редактирование"]').trigger('click')
    expect(wrapper.find('textarea.chat__input').element.value).toBe('Draft to keep')
    wrapper.unmount()
  })

  it('searches inside the open conversation using its header button', async () => {
    const wrapper = await mountMessages()
    await wrapper.find('[aria-label="Поиск по сообщениям"]').trigger('click')
    const input = wrapper.find('input.search-input')
    expect(input.attributes('placeholder')).toBe('Поиск в этом диалоге...')
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    try {
      await input.setValue('bridge')
      await vi.advanceTimersByTimeAsync(400)
      await flushPromises()
    } finally { vi.useRealTimers() }
    expect(chatApi.searchMessages).toHaveBeenCalledWith(1, 'bridge')
    wrapper.unmount()
  })

  it('does not overwrite another conversation draft when an edit finishes late', async () => {
    const original = { id: 43, conversation_id: 1, body: 'Original', user: { id: 1, username: 'me' } }
    const wrapper = await mountMessages([original])
    await wrapper.find('button[title="Изменить"]').trigger('click')
    let resolve
    chatApi.updateMessage.mockReturnValueOnce(new Promise(r => { resolve = r }))
    await wrapper.find('textarea.chat__input').setValue('Edited')
    await wrapper.find('form.chat__form').trigger('submit')
    await router.push('/messages/2')
    await flushPromises()
    await wrapper.find('textarea.chat__input').setValue('Other conversation draft')
    resolve({ message: { ...original, body: 'Edited' } })
    await flushPromises()
    expect(wrapper.find('textarea.chat__input').element.value).toBe('Other conversation draft')
    expect(wrapper.find('.chat__messages').text()).not.toContain('Edited')
    wrapper.unmount()
  })

  it.each([false, true])('jumps to search results outside the visible window (remote: %s)', async remote => {
    const target = { id: 10, conversation_id: 1, body: 'Old needle', snippet: 'Old needle', user: { id: 2, username: 'friend' } }
    const history = Array.from({length:120}, (_,i)=>({id:i+1,conversation_id:1,body:`Message ${i}`,user:{id:2,username:'friend'}}))
    if (!remote) history[9] = target
    const wrapper = await mountMessages(remote ? history.slice(80) : history)
    chatApi.searchMessages.mockResolvedValueOnce({messages:[target]})
    await wrapper.find('[aria-label="Поиск по сообщениям"]').trigger('click')
    vi.useFakeTimers({toFake:['setTimeout','clearTimeout']})
    try {
      await wrapper.find('input.search-input').setValue('needle')
      await vi.advanceTimersByTimeAsync(400)
      await flushPromises()
    } finally { vi.useRealTimers() }
    if (remote) chatApi.show.mockResolvedValueOnce({messages:[target],oldest_id:10,has_more:false,has_more_after:true})
    await wrapper.find('.search-item--message').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-message-id="10"]').exists()).toBe(true)
    if (remote) expect(chatApi.show).toHaveBeenCalledWith(1, expect.objectContaining({aroundId:10,markRead:false}))
    wrapper.unmount()
  })

  it('renders an attached video as a player instead of a download-only file', async () => {
    const wrapper = await mountMessages([{id:50,conversation_id:1,body:'',user:{id:2,username:'friend'},attachments:[{id:8,name:'clip.mp4',url:'/clip.mp4',mime:'video/mp4',is_video:true}]}])
    const video=wrapper.find('video.msg__video')
    expect(video.exists()).toBe(true)
    expect(video.attributes('controls')).toBeDefined()
    expect(video.attributes('playsinline')).toBeDefined()
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
