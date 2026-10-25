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

/** Рамка с фигурой игрока справа (в координатах карточки). */
const FRAME = { x: 310, y: 40, w: 248, h: 460, r: 24 }

/** Размер 3D-кадра, который готовит renderSkin3d — под рамку с учётом SCALE. */
export const FIGURE_FRAME = { width: FRAME.w * SCALE, height: FRAME.h * SCALE }

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
export function renderPlayerCard({ user, rank = null, aspects = null, skin = null, figure = null, avatar = null }) {
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
    drawFigure(ctx, user, { skin, figure, avatar }, theme, accent)
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

/**
 * Игрок в полный рост в высокой скруглённой рамке с цветной обводкой.
 *
 * Основной вариант — 3D-кадр (renderSkin3d): вид три четверти и второй
 * слой скина. Без WebGL — плоская фигура спереди, без скина — аватар
 * или первая буква ника.
 */
function drawFigure(ctx, user, { skin, figure, avatar }, theme, accent) {
    const { x, y, w, h, r } = FRAME

    ctx.save()
    roundRect(ctx, x, y, w, h, r)
    ctx.clip()

    // Фон рамки: чуть светлее карточки, к низу темнее — фигура «стоит»
    const bg = ctx.createLinearGradient(0, y, 0, y + h)
    bg.addColorStop(0, theme.panel)
    bg.addColorStop(1, theme.bg)
    ctx.fillStyle = bg
    ctx.fillRect(x, y, w, h)

    if (figure) {
        ctx.imageSmoothingEnabled = true
        ctx.drawImage(figure, x, y, w, h)
    } else if (skin?.image) {
        const flat = renderSkinFront(skin.image, { model: skin.model, scale: 16 })
        const height = h - 70
        const width = (flat.width / flat.height) * height

        ctx.imageSmoothingEnabled = false
        ctx.drawImage(flat, x + (w - width) / 2, y + 35, width, height)
    } else {
        const size = 160
        const ax = x + (w - size) / 2
        const ay = y + (h - size) / 2

        roundRect(ctx, ax, ay, size, size, 14)
        ctx.clip()

        if (avatar) {
            ctx.imageSmoothingEnabled = true
            ctx.drawImage(avatar, ax, ay, size, size)
        } else {
            ctx.fillStyle = C.accent
            ctx.fillRect(ax, ay, size, size)
            ctx.fillStyle = '#fff'
            ctx.font = `800 80px ${DISPLAY}`
            ctx.textAlign = 'center'
            ctx.textBaseline = 'middle'
            ctx.fillText((user?.username || '?').charAt(0).toUpperCase(), ax + size / 2, ay + size / 2)
            ctx.textAlign = 'left'
        }
    }

    ctx.restore()

    roundRect(ctx, x, y, w, h, r)
    ctx.strokeStyle = accent
    ctx.lineWidth = 5
    ctx.stroke()
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
