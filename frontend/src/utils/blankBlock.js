/**
 * Пустой ли блок профиля.
 *
 * Блок «Сезоны» без сезонов или «Аспекты» без данных рисуют только рамку
 * (а иногда и заголовок): гостю это выглядит как пустая коробка. Пустым
 * считаем блок, в теле которого нет текста (кроме заголовка блока) и нет
 * картинок, полей ввода и кнопок. Пока блок грузит данные, в нём есть текст
 * или спиннер, так что скрывается он только по-настоящему пустым.
 */
const MEDIA = 'img, svg, canvas, video, audio, iframe, input, textarea, select, button, [style*="background"]'

export function isBlankBlock(block) {
    const body = block.querySelector(':scope > .pb-block__body') ?? block
    const title = body.querySelector(':scope > .pb-block__title')

    let text = ''

    body.childNodes.forEach((node) => {
        if (node !== title) text += node.textContent || ''
    })

    if (text.trim()) return false

    return !body.querySelector(MEDIA)
}
