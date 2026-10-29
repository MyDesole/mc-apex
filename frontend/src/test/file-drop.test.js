/**
 * Вставка файлов из буфера и перетаскивание.
 */
import { describe, expect, it, vi } from 'vitest'
import { filesFromClipboard, useFileDrop } from '@/composables/core/useFileDrop.js'

function clipboardEvent(items) {
  return { clipboardData: { items } }
}

function fileItem(file) {
  return { kind: 'file', getAsFile: () => file }
}

describe('filesFromClipboard', () => {
  it('текст — не файлы: событие остаётся браузеру', () => {
    expect(filesFromClipboard(clipboardEvent([{ kind: 'string', getAsFile: () => null }]))).toEqual([])
    expect(filesFromClipboard({})).toEqual([])
  })

  it('безымянный скриншот получает читаемое имя, свой файл — сохраняет', () => {
    const shot = new File(['x'], 'image.png', { type: 'image/png' })
    const doc = new File(['x'], 'отчёт.pdf', { type: 'application/pdf' })

    const files = filesFromClipboard(clipboardEvent([fileItem(shot), fileItem(doc)]))

    expect(files).toHaveLength(2)
    expect(files[0].name).toMatch(/^screenshot-\d{14}\.png$/)
    expect(files[0].type).toBe('image/png')
    expect(files[1].name).toBe('отчёт.pdf')
  })
})

describe('useFileDrop', () => {
  function dragEvent(files = [], types = ['Files']) {
    return { preventDefault: vi.fn(), dataTransfer: { types, files, dropEffect: 'none' } }
  }

  it('подсветка держится над вложенными элементами и гаснет после сброса', () => {
    const onFiles = vi.fn()
    const { dragging, dropHandlers } = useFileDrop(onFiles)

    dropHandlers.dragenter(dragEvent())
    dropHandlers.dragenter(dragEvent()) // вошли во вложенный элемент
    dropHandlers.dragleave(dragEvent())

    expect(dragging.value).toBe(true)

    const file = new File(['x'], 'a.png', { type: 'image/png' })

    dropHandlers.drop(dragEvent([file]))

    expect(dragging.value).toBe(false)
    expect(onFiles).toHaveBeenCalledWith([file])
  })

  it('перетаскивание текста и выключенная зона файлы не принимают', () => {
    const onFiles = vi.fn()
    const { dragging, dropHandlers } = useFileDrop(onFiles, { enabled: () => false })

    dropHandlers.dragenter(dragEvent([], ['text/plain']))
    dropHandlers.dragenter(dragEvent())
    dropHandlers.drop(dragEvent([new File(['x'], 'a.png')]))

    expect(dragging.value).toBe(false)
    expect(onFiles).not.toHaveBeenCalled()
  })
})
