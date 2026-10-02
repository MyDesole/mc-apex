/**
 * Проверка прикрепления файлов в чате.
 *
 * Путь проходил через несколько правок (вынос стилей, вынос компонентов,
 * смена формы запроса), и каждый раз ломался молча. Тест закрепляет:
 *   - выбор файла вызывает загрузку на сервер;
 *   - прикреплённый файл появляется в списке;
 *   - запрос уходит в форме, которую принимает сервер.
 */
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

vi.mock('@/services/chat/chat.js', () => ({
  chatApi: { uploadMany: vi.fn(), upload: vi.fn() },
}))

import ChatAttachmentsInput from '@/components/chat/ChatAttachmentsInput.vue'
import { chatApi } from '@/services/chat/chat.js'

/** Монтирует панель вложений с готовым ответом сервера. */
function mountPanel(response) {
  chatApi.uploadMany.mockResolvedValue(response)

  return mount(ChatAttachmentsInput, {
    props: { modelValue: [], max: 10 },
  })
}

/** Имитирует выбор файлов в поле. */
async function pick(wrapper, files) {
  const input = wrapper.find('input[type="file"]')

  Object.defineProperty(input.element, 'files', { value: files, writable: false })

  await input.trigger('change')
  await flushPromises()
}

const ATTACHMENTS = {
  attachments: [
    { id: 1, name: 'shot.png', url: '/storage/shot.png', size: '3 КБ', is_image: true },
  ],
}

describe('Прикрепление файлов в чате', () => {
  beforeEach(() => vi.clearAllMocks())

  it('поле выбора файла есть в разметке и не заблокировано', () => {
    const wrapper = mountPanel(ATTACHMENTS)
    const input = wrapper.find('input[type="file"]')

    expect(input.exists()).toBe(true)
    expect(input.attributes('disabled')).toBeUndefined()
  })

  it('выбор файла отправляет его на сервер', async () => {
    const wrapper = mountPanel(ATTACHMENTS)

    await pick(wrapper, [new File(['x'], 'shot.png', { type: 'image/png' })])

    expect(chatApi.uploadMany).toHaveBeenCalledTimes(1)
  })

  it('прикреплённый файл отдаётся наверх и виден в списке', async () => {
    const wrapper = mountPanel(ATTACHMENTS)

    await pick(wrapper, [new File(['x'], 'shot.png', { type: 'image/png' })])

    const emitted = wrapper.emitted('update:modelValue')

    expect(emitted).toBeTruthy()
    expect(emitted.at(-1)[0][0].id).toBe(1)

    /* После обновления модель приходит сверху — показываем список */
    await wrapper.setProps({ modelValue: emitted.at(-1)[0] })

    expect(wrapper.text()).toContain('shot.png')
  })

  it('несколько файлов уходят одним запросом', async () => {
    const wrapper = mountPanel({
      attachments: [
        { id: 1, name: 'a.png', url: '/a.png', size: '1 КБ', is_image: true },
        { id: 2, name: 'b.pdf', url: '/b.pdf', size: '2 КБ', is_image: false },
      ],
    })

    await pick(wrapper, [
      new File(['x'], 'a.png', { type: 'image/png' }),
      new File(['y'], 'b.pdf', { type: 'application/pdf' }),
    ])

    expect(chatApi.uploadMany).toHaveBeenCalledTimes(1)
    expect(chatApi.uploadMany.mock.calls[0][0]).toHaveLength(2)
  })

  it('слишком большой файл не отправляется и видна причина', async () => {
    const wrapper = mountPanel(ATTACHMENTS)

    const big = new File(['x'], 'huge.zip', { type: 'application/zip' })

    Object.defineProperty(big, 'size', { value: 13 * 1024 * 1024 })

    await pick(wrapper, [big])

    expect(chatApi.uploadMany).not.toHaveBeenCalled()
    expect(wrapper.text()).toMatch(/больше 12 МБ/i)
  })
})
