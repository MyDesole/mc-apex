/**
 * Звуки уведомлений и сообщений.
 *
 * Синтезируем через Web Audio — без файлов: короткий «блок-блинк»
 * для уведомлений (два тона вверх, как опыт в Minecraft) и мягкий
 * «поп» для сообщений. Игрок выключает звук на странице уведомлений,
 * выбор хранится в браузере.
 */

const STORAGE_KEY = 'apex:sound'

let context = null
let lastPlayed = 0

export function isSoundEnabled() {
    try {
        return localStorage.getItem(STORAGE_KEY) !== 'off'
    } catch {
        return true
    }
}

export function setSoundEnabled(enabled) {
    try {
        localStorage.setItem(STORAGE_KEY, enabled ? 'on' : 'off')
    } catch {
        // приватный режим — просто не запомним
    }
}

function audio() {
    if (typeof window === 'undefined') return null

    const Ctx = window.AudioContext || window.webkitAudioContext
    if (!Ctx) return null

    context ??= new Ctx()

    // Браузер будит звук только после действия игрока — пробуем возобновить
    if (context.state === 'suspended') context.resume().catch(() => {})

    return context
}

/** Один тон с мягкой атакой и затуханием. */
function tone(ctx, { freq, start, duration, type = 'sine', volume = 0.12 }) {
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()

    osc.type = type
    osc.frequency.setValueAtTime(freq, start)

    gain.gain.setValueAtTime(0.0001, start)
    gain.gain.exponentialRampToValueAtTime(volume, start + 0.012)
    gain.gain.exponentialRampToValueAtTime(0.0001, start + duration)

    osc.connect(gain).connect(ctx.destination)
    osc.start(start)
    osc.stop(start + duration + 0.02)
}

/**
 * kind: 'notification' — новое уведомление, 'message' — новое сообщение.
 * Пачка событий подряд звучит один раз.
 */
export function playSound(kind = 'notification') {
    if (!isSoundEnabled()) return

    const now = Date.now()
    if (now - lastPlayed < 700) return
    lastPlayed = now

    const ctx = audio()
    if (!ctx) return

    const t = ctx.currentTime + 0.01

    if (kind === 'message') {
        tone(ctx, { freq: 660, start: t, duration: 0.09, type: 'triangle', volume: 0.1 })
        tone(ctx, { freq: 990, start: t + 0.05, duration: 0.12, type: 'sine', volume: 0.07 })
        return
    }

    tone(ctx, { freq: 1318.5, start: t, duration: 0.14, type: 'square', volume: 0.035 })
    tone(ctx, { freq: 1760, start: t + 0.09, duration: 0.22, type: 'square', volume: 0.035 })
    tone(ctx, { freq: 1760, start: t + 0.09, duration: 0.3, type: 'sine', volume: 0.08 })
}

/** Щелчок рулетки кейса: карточка прошла под указателем. */
export function playCaseTick() {
    if (!isSoundEnabled()) return

    const ctx = audio()
    if (!ctx) return

    tone(ctx, { freq: 1900, start: ctx.currentTime + 0.001, duration: 0.03, type: 'square', volume: 0.018 })
}

/** Выигрыш из кейса: чем реже исход, тем богаче аккорд. */
export function playCaseWin(outcome = 'common') {
    if (!isSoundEnabled()) return

    const ctx = audio()
    if (!ctx) return

    const t = ctx.currentTime + 0.01

    if (outcome === 'nothing') {
        tone(ctx, { freq: 330, start: t, duration: 0.18, type: 'triangle', volume: 0.08 })
        tone(ctx, { freq: 247, start: t + 0.14, duration: 0.3, type: 'triangle', volume: 0.07 })
        return
    }

    const chords = {
        coins: [880, 1175],
        common: [523, 659, 784],
        rare: [587, 740, 880, 1175],
        epic: [523, 659, 784, 1047, 1319],
        legendary: [523, 659, 784, 1047, 1319, 1568, 2093],
    }

    ;(chords[outcome] || chords.common).forEach((freq, i) => {
        tone(ctx, { freq, start: t + i * 0.07, duration: 0.5, type: i % 2 ? 'sine' : 'triangle', volume: 0.07 })
    })
}

/** Первый клик по странице «будит» звук, чтобы первое уведомление не потерялось. */
export function unlockSoundOnFirstGesture() {
    if (typeof window === 'undefined') return

    const unlock = () => {
        audio()
        window.removeEventListener('pointerdown', unlock)
        window.removeEventListener('keydown', unlock)
    }

    window.addEventListener('pointerdown', unlock, { once: true })
    window.addEventListener('keydown', unlock, { once: true })
}
