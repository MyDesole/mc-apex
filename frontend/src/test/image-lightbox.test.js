/**
 * Просмотр картинок: листание, закрытие по Esc и фону, увеличение.
 */
import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ImageLightbox from '@/components/core/ImageLightbox.vue'

const images = [
  { url: '/a.png', name: 'a.png' },
  { url: '/b.png', name: 'b.png' },
  { url: '/c.png', name: 'c.png' },
]

let wrapper

function open(props = {}) {
  wrapper = mount(ImageLightbox, { props: { images, ...props }, attachTo: document.body })

  return wrapper
}

const box = () => document.body.querySelector('.lightbox')
const shown = () => box().querySelector('.lightbox__img').getAttribute('src')

afterEach(() => wrapper?.unmount())

describe('ImageLightbox', () => {
  it('открывается на нужной картинке и листается по кругу', async () => {
    open({ start: 2 })

    expect(shown()).toBe('/c.png')
    expect(box().querySelector('.lightbox__count').textContent).toContain('3 / 3')

    box().querySelector('.lightbox__nav--next').click()
    await nextTick()
    expect(shown()).toBe('/a.png')

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft' }))
    await nextTick()
    expect(shown()).toBe('/c.png')
  })

  it('закрывается по Esc и по клику на фон', async () => {
    open()

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    box().querySelector('.lightbox__stage').click()

    expect(wrapper.emitted('close')).toHaveLength(2)
  })

  it('одна картинка — без стрелок; страница не прокручивается под просмотром', () => {
    open({ images: [images[0]] })

    expect(box().querySelector('.lightbox__nav')).toBeNull()
    expect(document.body.style.overflow).toBe('hidden')

    wrapper.unmount()
    wrapper = null

    expect(document.body.style.overflow).toBe('')
  })
})
