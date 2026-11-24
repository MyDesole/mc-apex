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

describe('Image bounds', () => {
  function dimensions(width, height, naturalWidth, naturalHeight) {
    const stage = box().querySelector('.lightbox__stage')
    const image = box().querySelector('.lightbox__img')
    Object.defineProperties(stage, {
      clientWidth: { configurable: true, value: width },
      clientHeight: { configurable: true, value: height },
    })
    Object.defineProperties(image, {
      naturalWidth: { configurable: true, value: naturalWidth },
      naturalHeight: { configurable: true, value: naturalHeight },
    })
    stage.style.padding = '0px'
    image.dispatchEvent(new Event('load'))
    return image
  }

  it('fits a tall photo entirely within the available height', async () => {
    open()
    const image = dimensions(900, 600, 1800, 3000)
    await nextTick()
    expect(image.style.width).toBe('360px')
    expect(image.style.height).toBe('600px')
  })

  it('fits a wide photo by width without stretching its proportions', async () => {
    open()
    const image = dimensions(900, 600, 4000, 1000)
    await nextTick()
    expect(image.style.width).toBe('900px')
    expect(image.style.height).toBe('225px')
  })

  it('recalculates image size and resets zoom after a viewport resize', async () => {
    open()
    const image = dimensions(900, 600, 1800, 3000)
    await nextTick()
    image.click()
    await nextTick()
    expect(image.style.transform).toContain('scale(2.2)')
    Object.defineProperty(box().querySelector('.lightbox__stage'), 'clientHeight', { configurable: true, value: 400 })
    window.dispatchEvent(new Event('resize'))
    await nextTick()
    expect(image.style.width).toBe('240px')
    expect(image.style.height).toBe('400px')
    expect(image.style.transform).toContain('scale(1)')
  })
})
