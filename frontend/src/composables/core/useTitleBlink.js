import { ref, watch } from 'vue'

const APP_NAME = 'APEX TIERS'
const BLINK_TEXT = 'Новое сообщение'
const BLINK_INTERVAL = 1500

const isBlinking = ref(false)
const baseTitle = ref(APP_NAME)

let intervalId = null
let showAlt = false

function applyBase() {
    document.title = baseTitle.value
}

function startBlink() {
    if (intervalId) return

    showAlt = false
    applyBase()

    intervalId = setInterval(() => {
        showAlt = !showAlt
        document.title = showAlt ? BLINK_TEXT : baseTitle.value
    }, BLINK_INTERVAL)
}

function stopBlink() {
    if (intervalId) {
        clearInterval(intervalId)
        intervalId = null
    }
    showAlt = false
    applyBase()
}

watch(isBlinking, (v) => {
    if (v) startBlink()
    else stopBlink()
})

function setBaseTitle(title) {
    baseTitle.value = title || APP_NAME
    if (!isBlinking.value) applyBase()
}

export function useTitleBlink() {
    return {
        isBlinking,
        setBaseTitle,
        startBlink: () => { isBlinking.value = true },
        stopBlink: () => { isBlinking.value = false },
    }
}