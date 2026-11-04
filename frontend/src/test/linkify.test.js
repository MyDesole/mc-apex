import { describe, expect, it } from 'vitest'
import { internalPath, linkify } from '@/utils/linkify.js'

const links = (text) => linkify(text).filter((s) => s.type === 'link').map((s) => s.href)

describe('ссылки в тексте форума', () => {
  it('находит http(s) и www, склеивает текст обратно без потерь', () => {
    const text = 'Смотри https://mc-apex.ru/forum/12 и www.example.com/a?b=1 тут'

    expect(links(text)).toEqual(['https://mc-apex.ru/forum/12', 'https://www.example.com/a?b=1'])
    expect(linkify(text).map((s) => s.text).join('')).toBe(text)
  })

  it('не забирает знаки препинания и скобки предложения', () => {
    expect(links('Вот ссылка: https://a.ru/x.')).toEqual(['https://a.ru/x'])
    expect(links('(см. https://a.ru/x)')).toEqual(['https://a.ru/x'])
    expect(links('Правда? https://a.ru/x?')).toEqual(['https://a.ru/x'])
    // скобка внутри адреса остаётся
    expect(links('https://ru.wikipedia.org/wiki/Test_(page)')).toEqual(['https://ru.wikipedia.org/wiki/Test_(page)'])
  })

  it('не делает ссылками опасные схемы и простой текст', () => {
    expect(links('javascript:alert(1)')).toEqual([])
    expect(links('data:text/html,<b>x</b>')).toEqual([])
    expect(links('просто текст без адреса')).toEqual([])
    expect(linkify('')).toEqual([])
    expect(linkify(null)).toEqual([])
  })

  it('сохраняет переносы строк внутри текста', () => {
    const segments = linkify('первая\nhttps://a.ru\nвторая')

    expect(segments.map((s) => s.text).join('')).toBe('первая\nhttps://a.ru\nвторая')
  })

  it('узнаёт ссылки на свой сайт', () => {
    const hosts = ['mc-apex.ru']

    expect(internalPath('https://mc-apex.ru/forum/3?x=1#r5', hosts)).toBe('/forum/3?x=1#r5')
    expect(internalPath('https://evil.example/forum/3', hosts)).toBeNull()
    expect(internalPath('не адрес', hosts)).toBeNull()
  })
})
