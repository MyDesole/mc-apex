/**
 * Файлы из буфера обмена (Ctrl+V) и перетаскивания.
 *
 * Общие для мессенджера и форума: поле ввода ловит вставку, область
 * формы — сброс файлов. Обычный текст при вставке не трогаем.
 */
import { ref } from 'vue'

/** Имя для картинки из буфера: у скриншота его нет или оно «image.png». */
function nameFor(file, index) {
    const stamp = new Date().toISOString().replace(/[-:T]/g, '').slice(0, 14)
    const ext = (file.type.split('/')[1] || 'png').replace('jpeg', 'jpg').replace(/[^a-z0-9]/g, '')

    return `screenshot-${stamp}${index ? `-${index + 1}` : ''}.${ext}`
}

/**
 * Файлы из события вставки. Пусто — значит вставляют текст, и событие
 * нужно оставить браузеру.
 *
 * @param {ClipboardEvent} event
 * @returns {File[]}
 */
export function filesFromClipboard(event) {
    const items = Array.from(event?.clipboardData?.items || [])

    const files = items
        .filter((item) => item.kind === 'file')
        .map((item) => item.getAsFile())
        .filter(Boolean)

    return files.map((file, index) => {
        const generic = !file.name || /^image\.(png|jpe?g|gif|webp)$/i.test(file.name)

        return generic ? new File([file], nameFor(file, index), { type: file.type }) : file
    })
}

/** Тащат ли именно файлы (а не выделенный текст или ссылку). */
function hasFiles(event) {
    return Array.from(event?.dataTransfer?.types || []).includes('Files')
}

/**
 * Область сброса файлов: подсветка, пока над ней тащат файл, и колбэк
 * с файлами при сбросе.
 *
 * Счётчик нужен, потому что dragenter и dragleave приходят и от вложенных
 * элементов: без него подсветка мигала бы при движении над формой.
 *
 * @param {(files: File[]) => void} onFiles
 * @param {{ enabled?: () => boolean }} options
 */
export function useFileDrop(onFiles, options = {}) {
    const dragging = ref(false)

    let depth = 0

    const enabled = () => (options.enabled ? options.enabled() : true)

    function onDragEnter(event) {
        if (!hasFiles(event) || !enabled()) return

        event.preventDefault()
        depth++
        dragging.value = true
    }

    function onDragOver(event) {
        if (!hasFiles(event) || !enabled()) return

        // Без preventDefault браузер не разрешит сброс и откроет файл сам
        event.preventDefault()
        event.dataTransfer.dropEffect = 'copy'
    }

    function onDragLeave(event) {
        if (!hasFiles(event)) return

        depth = Math.max(0, depth - 1)

        if (!depth) dragging.value = false
    }

    function onDrop(event) {
        if (!hasFiles(event)) return

        event.preventDefault()
        depth = 0
        dragging.value = false

        if (!enabled()) return

        const files = Array.from(event.dataTransfer.files || [])

        if (files.length) onFiles(files)
    }

    return {
        dragging,
        dropHandlers: {
            dragenter: onDragEnter,
            dragover: onDragOver,
            dragleave: onDragLeave,
            drop: onDrop,
        },
    }
}
