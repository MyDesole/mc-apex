import { describe, expect, it } from 'vitest'
import { isBlankBlock } from '@/utils/blankBlock.js'

function block(html) {
  const el = document.createElement('div')
  el.className = 'pb-block'
  el.innerHTML = `<div class="pb-block__body">${html}</div>`

  return el
}

describe('пустые блоки профиля', () => {
  it('блок без тела и с одними комментариями пустой', () => {
    expect(isBlankBlock(block('<!----><!---->'))).toBe(true)
  })

  it('заголовок и пустая обёртка под ним — тоже пустой блок', () => {
    expect(isBlankBlock(block('<h3 class="pb-block__title">Аспекты</h3><div><!----></div>'))).toBe(true)
  })

  it('текст под заголовком делает блок непустым', () => {
    expect(isBlankBlock(block('<h3 class="pb-block__title">История</h3><p>Истории тиров пока нет.</p>'))).toBe(false)
  })

  it('картинка, кнопка или поле ввода — не пусто', () => {
    expect(isBlankBlock(block('<div><img src="x.png"></div>'))).toBe(false)
    expect(isBlankBlock(block('<div><button></button></div>'))).toBe(false)
    expect(isBlankBlock(block('<div><textarea></textarea></div>'))).toBe(false)
  })

  it('пробелы вместо текста — пустой блок', () => {
    expect(isBlankBlock(block('<div>   \n  </div>'))).toBe(true)
  })
})
