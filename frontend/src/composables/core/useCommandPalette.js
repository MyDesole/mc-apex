import { ref } from 'vue'

/*
 * Состояние на уровне модуля: палитру открывают и шапка (кнопка),
 * и горячие клавиши, и другие страницы — все видят одно и то же.
 */
const isOpen = ref(false)

export function useCommandPalette() {
    function open() {
        isOpen.value = true
    }

    function close() {
        isOpen.value = false
    }

    function toggle() {
        isOpen.value = !isOpen.value
    }

    return { isOpen, open, close, toggle }
}

/** Mac показывает ⌘, остальные — Ctrl. */
export const paletteShortcut = typeof navigator !== 'undefined'
    && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)
    ? '⌘K'
    : 'Ctrl K'
