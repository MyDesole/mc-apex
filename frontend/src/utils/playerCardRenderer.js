/**
 * Карточка игрока: рисуется на canvas целиком, без снимков DOM.
 *
 * Так PNG одинаковый в любом браузере и не зависит от вёрстки страницы.
 * Размер 600×840 (×2 для чёткости) — удобно для Discord и Telegram.
 */
import { tierColors } from '@/data/players/profileCustomization.js'
import { ASPECT_MAX, MODE_LABELS } from '@/data/players/aspects.js'
import { renderSkinFront } from '@/utils/minecraftSkin.js'
import { cardTheme } from '@/data/shop/cosmetics.js'

export const CARD_WIDTH = 600
export const CARD_HEIGHT = 840

const SCALE = 2

/** Цвета текста и элементов; фон, постамент и рамку задаёт тема карточки. */
const C = {
    track: '#262230',
    text: '#ecebf1',
    dim: '#a29eb0',
    muted: '#6f6a7c',
    accent: '#7c3aed',
    accentLight: '#a78bfa',
    accentEdge: '#4c1d95',
}

const DISPLAY = 'Unbounded, "Golos Text", sans-serif'
const BODY = '"Golos Text", system-ui, sans-serif'

/** Шрифты должны загрузиться до отрисовки, иначе canvas возьмёт запасной. */
export async function ensureCardFonts() {
    if (!document.fonts?.load) return

    await Promise.all([
        document.fonts.load(`800 40px Unbounded`),
        document.fonts.load(`700 20px Unbounded`),
        document.fonts.load(`600 18px "Golos Text"`),
        document.fonts.load(`400 14px "Golos Text"`),
    ]).catch(() => {})
}

/**
 * @param {{
 *   user: object,
 *   rank?: { position: number|null, total: number },
 *   aspects?: { mode: string, values: Array<{label: string, value: number}> } | null,
 *   skin?: { image: HTMLImageElement, model: string } | null,
 *   avatar?: HTMLImageElement | null,
 * }} data
 * @returns {HTMLCanvasElement}
 */
export function renderPlayerCard({ user, rank = null, aspects = null, skin = null, avatar = null }) {
    const canvas = document.createElement('canvas')

    canvas.width = CARD_WIDTH * SCALE
    canvas.height = CARD_HEIGHT * SCALE

    const ctx = canvas.getContext('2d')
    ctx.scale(SCALE, SCALE)

    const tier = user?.tier || 'E'
    const tierColor = tierColors[tier] || C.accentLight
    const score = Math.round(Number(user?.tier_score ?? 0))

    // Тема из магазина: фон, постамент и цвет полос; иначе — цвет тира
    const theme = cardTheme(user?.card_theme)
    const accent = theme.accent || tierColor

    drawBackground(ctx, theme, accent)
    drawBrand(ctx)
    drawRank(ctx, rank)
    drawTierColumn(ctx, tier, tierColor, score)
    drawFigure(ctx, user, skin, avatar)
    drawIdentity(ctx, user, theme)
    drawAspects(ctx, aspects, accent)
    drawFooter(ctx)

    return canvas
}

/* ------------------------------ Части карточки ------------------------------ */

function drawBackground(ctx, theme, accent) {
    roundRect(ctx, 0, 0, CARD_WIDTH, CARD_HEIGHT, 20)
    ctx.fillStyle = theme.bg
    ctx.fill()

    ctx.save()
    roundRect(ctx, 0, 0, CARD_WIDTH, CARD_HEIGHT, 20)
    ctx.clip()

    // Полоса сверху: цвет темы или тира
    ctx.fillStyle = accent
    ctx.fillRect(0, 0, CARD_WIDTH, 8)

    // Постамент под фигурой: шахматка, как у блока без текстуры
    const px = 300
    const py = 40
    const pw = 268
    const ph = 452
    const cell = 26

    ctx.save()
    roundRect(ctx, px, py, pw, ph, 12)
    ctx.clip()

    for (let y = py; y < py + ph; y += cell) {
        for (let x = px; x < px + pw; x += cell) {
            const even = ((x - px) / cell + (y - py) / cell) % 2 === 0

            ctx.globalAlpha = even ? 1 : (theme.panelAltAlpha ?? 1)
            ctx.fillStyle = even ? theme.panel : theme.panelAlt
            ctx.fillRect(x, y, cell, cell)
        }
    }

    ctx.globalAlpha = 1

    // Мягкое затемнение к низу постамента — в цвет фона карточки
    const fade = ctx.createLinearGradient(0, py, 0, py + ph)
    fade.addColorStop(0, withAlpha(theme.bg, 0))
    fade.addColorStop(1, withAlpha(theme.bg, 0.75))
    ctx.fillStyle = fade
    ctx.fillRect(px, py, pw, ph)

    ctx.restore()
    ctx.restore()

    // Рамка
    roundRect(ctx, 1, 1, CARD_WIDTH - 2, CARD_HEIGHT - 2, 19)
    ctx.strokeStyle = theme.border
    ctx.lineWidth = 2
    ctx.stroke()
}

function drawBrand(ctx) {
    drawCube(ctx, 32, 36, 30)

    ctx.fillStyle = '#fff'
    ctx.font = `800 18px ${DISPLAY}`
    ctx.textBaseline = 'middle'
    ctx.fillText('APEX', 72, 52)

    const apexWidth = ctx.measureText('APEX').width

    ctx.fillStyle = C.muted
    ctx.font = `700 11px ${BODY}`
    ctx.fillText('T I E R S', 72 + apexWidth + 8, 53)
}

function drawRank(ctx, rank) {
    if (!rank?.position) return

    ctx.textBaseline = 'alphabetic'
    ctx.fillStyle = '#fff'
    ctx.font = `700 34px ${DISPLAY}`
    ctx.fillText(`#${rank.position}`, 32, 412)

    ctx.fillStyle = C.muted
    ctx.font = `400 15px ${BODY}`
    ctx.fillText(`из ${rank.total} в рейтинге`, 32, 438)
}

function drawTierColumn(ctx, tier, tierColor, score) {
    ctx.textBaseline = 'alphabetic'
    ctx.fillStyle = C.muted
    ctx.font = `600 14px ${BODY}`
    ctx.fillText('Тир', 32, 120)

    // Тир-блок: грани светлее сверху и темнее снизу, как у руды
    const x = 32
    const y = 134
    const size = 116

    roundRect(ctx, x, y, size, size, 10)
    ctx.fillStyle = tierColor
    ctx.fill()

    ctx.save()
    roundRect(ctx, x, y, size, size, 10)
    ctx.clip()
    ctx.fillStyle = 'rgba(255, 255, 255, 0.35)'
    ctx.fillRect(x, y, size, 5)
    ctx.fillStyle = 'rgba(0, 0, 0, 0.22)'
    ctx.fillRect(x, y + size - 12, size, 12)
    ctx.restore()

    ctx.fillStyle = '#0d0c11'
    ctx.font = `800 64px ${DISPLAY}`
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillText(tier, x + size / 2, y + size / 2 - 4)
    ctx.textAlign = 'left'

    ctx.textBaseline = 'alphabetic'
    ctx.fillStyle = '#fff'
    ctx.font = `800 56px ${DISPLAY}`
    ctx.fillText(`${score}%`, 32, 324)

    ctx.fillStyle = C.muted
    ctx.font = `400 15px ${BODY}`
    ctx.fillText('очков тира', 32, 350)
}

function drawFigure(ctx, user, skin, avatar) {
    const centerX = 434
    const top = 70
    const height = 400

    ctx.imageSmoothingEnabled = false

    if (skin?.image) {
        const figure = renderSkinFront(skin.image, { model: skin.model, scale: 16 })
        const width = (figure.width / figure.height) * height

        // Тень под ногами
        ctx.fillStyle = 'rgba(0, 0, 0, 0.35)'
        ctx.beginPath()
        ctx.ellipse(centerX, top + height + 4, width * 0.42, 9, 0, 0, Math.PI * 2)
        ctx.fill()

        ctx.drawImage(figure, centerX - width / 2, top, width, height)

        return
    }

    // Скина нет — аватар или первая буква ника
    const size = 180
    const x = centerX - size / 2
    const y = top + 90

    ctx.save()
    roundRect(ctx, x, y, size, size, 14)
    ctx.clip()

    if (avatar) {
        ctx.imageSmoothingEnabled = true
        ctx.drawImage(avatar, x, y, size, size)
    } else {
        ctx.fillStyle = C.accent
        ctx.fillRect(x, y, size, size)
        ctx.fillStyle = '#fff'
        ctx.font = `800 84px ${DISPLAY}`
        ctx.textAlign = 'center'
        ctx.textBaseline = 'middle'
        ctx.fillText((user?.username || '?').charAt(0).toUpperCase(), centerX, y + size / 2)
        ctx.textAlign = 'left'
    }

    ctx.restore()
}

function drawIdentity(ctx, user, theme) {
    const maxWidth = CARD_WIDTH - 64
    const tag = user?.clan_tag ? `[${user.clan_tag}] ` : ''
    const name = user?.username || 'Игрок'

    // Длинные ники уменьшаем, а не обрезаем
    let size = 40
    ctx.font = `800 ${size}px ${DISPLAY}`

    while (size > 20 && ctx.measureText(tag + name).width > maxWidth) {
        size -= 2
        ctx.font = `800 ${size}px ${DISPLAY}`
    }

    ctx.textBaseline = 'alphabetic'

    let x = 32
    const y = 552

    if (tag) {
        ctx.fillStyle = user.clan_color || C.accentLight
        ctx.fillText(tag, x, y)
        x += ctx.measureText(tag).width
    }

    ctx.fillStyle = '#fff'
    ctx.fillText(name, x, y)

    const nick = user?.minecraft_username

    ctx.fillStyle = C.dim
    ctx.font = `400 15px ${BODY}`
    ctx.fillText(nick ? `Minecraft: ${nick}` : 'Minecraft-аккаунт не привязан', 32, 582)

    ctx.fillStyle = theme.border
    ctx.fillRect(32, 604, CARD_WIDTH - 64, 1)
}

function drawAspects(ctx, aspects, tierColor) {
    if (!aspects?.values?.length) {
        ctx.fillStyle = C.muted
        ctx.font = `400 16px ${BODY}`
        ctx.textBaseline = 'middle'
        ctx.fillText('Тир-тест ещё не пройден — аспекты появятся после него.', 32, 700)

        return
    }

    const startY = 632
    const rowHeight = 32

    ctx.textBaseline = 'middle'
    ctx.fillStyle = C.muted
    ctx.font = `600 12px ${BODY}`
    ctx.textAlign = 'right'
    ctx.fillText(MODE_LABELS[aspects.mode] || aspects.mode, CARD_WIDTH - 32, startY - 14)
    ctx.textAlign = 'left'

    aspects.values.forEach((aspect, index) => {
        const y = startY + 6 + index * rowHeight

        ctx.fillStyle = C.text
        ctx.font = `600 16px ${BODY}`
        ctx.fillText(aspect.label, 32, y)

        const barX = 210
        const barW = 300
        const ratio = Math.max(0, Math.min(1, aspect.value / ASPECT_MAX))

        roundRect(ctx, barX, y - 5, barW, 10, 3)
        ctx.fillStyle = C.track
        ctx.fill()

        if (ratio > 0) {
            roundRect(ctx, barX, y - 5, Math.max(6, barW * ratio), 10, 3)
            ctx.fillStyle = tierColor
            ctx.fill()
        }

        ctx.fillStyle = '#fff'
        ctx.font = `700 16px ${DISPLAY}`
        ctx.textAlign = 'right'
        ctx.fillText(String(Math.round(aspect.value * 10) / 10), CARD_WIDTH - 32, y)
        ctx.textAlign = 'left'
    })
}

function drawFooter(ctx) {
    ctx.textBaseline = 'alphabetic'
    ctx.fillStyle = C.muted
    ctx.font = `400 13px ${BODY}`
    ctx.fillText(window.location.host || 'APEX TIERS', 32, CARD_HEIGHT - 22)

    ctx.textAlign = 'right'
    ctx.fillText(new Date().toLocaleDateString('ru-RU'), CARD_WIDTH - 32, CARD_HEIGHT - 22)
    ctx.textAlign = 'left'
}

/* ------------------------------ Помощники ------------------------------ */

/** #rrggbb → rgba с прозрачностью. */
function withAlpha(hex, alpha) {
    const value = /^#([0-9a-f]{6})$/i.exec(hex)?.[1] ?? '16141c'
    const [r, g, b] = [0, 2, 4].map((i) => parseInt(value.slice(i, i + 2), 16))

    return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

/** Знак APEX — тот же блок, что в шапке сайта. */
function drawCube(ctx, x, y, size) {
    const s = size / 24
    const poly = (points, color) => {
        ctx.beginPath()
        points.forEach(([px, py], index) => {
            const method = index ? 'lineTo' : 'moveTo'
            ctx[method](x + px * s, y + py * s)
        })
        ctx.closePath()
        ctx.fillStyle = color
        ctx.fill()
    }

    poly([[12, 1.5], [22, 7], [12, 12.5], [2, 7]], C.accentLight)
    poly([[2, 7], [12, 12.5], [12, 23], [2, 17.5]], C.accent)
    poly([[22, 7], [22, 17.5], [12, 23], [12, 12.5]], C.accentEdge)
}

function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath()

    if (ctx.roundRect) {
        ctx.roundRect(x, y, w, h, r)
        return
    }

    ctx.moveTo(x + r, y)
    ctx.arcTo(x + w, y, x + w, y + h, r)
    ctx.arcTo(x + w, y + h, x, y + h, r)
    ctx.arcTo(x, y + h, x, y, r)
    ctx.arcTo(x, y, x + w, y, r)
    ctx.closePath()
}
