/**
 * Скины Minecraft: загрузка через сервер и плоская отрисовка.
 *
 * Сервер (GET /api/players/{id}/skin) сам ходит к Mojang и кэширует PNG,
 * модель рук приходит заголовком X-Skin-Model. Здесь — общий кэш на
 * вкладку, чтобы 3D-модель, карточка и сравнение не качали скин заново,
 * и отрисовка фигуры спереди для карточки и запасного вида без WebGL.
 */

/** @type {Map<string, Promise<{url: string, model: 'classic'|'slim', image: HTMLImageElement}|null>>} */
const cache = new Map()

/**
 * Скин игрока или null, если ник не привязан или скин недоступен.
 * @param {string|number} key id или ник на сайте
 */
export function loadSkin(key) {
    if (key === undefined || key === null || key === '') return Promise.resolve(null)

    const id = String(key)

    if (!cache.has(id)) {
        cache.set(id, fetchSkin(id).catch(() => null))
    }

    return cache.get(id)
}

async function fetchSkin(id) {
    const response = await fetch(`/api/players/${encodeURIComponent(id)}/skin`, {
        credentials: 'include',
    })

    if (!response.ok) return null

    const model = response.headers.get('X-Skin-Model') === 'slim' ? 'slim' : 'classic'
    const url = URL.createObjectURL(await response.blob())
    const image = await loadImage(url)

    return { url, model, image }
}

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const image = new Image()
        image.onload = () => resolve(image)
        image.onerror = reject
        image.src = src
    })
}

/* ------------------------------ Плоская отрисовка ------------------------------ */

/**
 * Части тела спереди: где лежат на текстуре и куда ставятся на фигуре.
 * Координаты в пикселях текстуры 64×64. У старых скинов 64×32 левые
 * рука и нога — зеркало правых.
 */
function frontParts(model, legacy) {
    const arm = model === 'slim' ? 3 : 4

    // Правая рука игрока — слева от зрителя, вплотную к туловищу
    const rightArmX = 4 - arm
    const leftArmX = 12

    return [
        // [srcX, srcY, w, h, dstX, dstY, mirror]
        [4, 20, 4, 12, 4, 20, false], // правая нога
        legacy ? [4, 20, 4, 12, 8, 20, true] : [20, 52, 4, 12, 8, 20, false], // левая нога
        [20, 20, 8, 12, 4, 8, false], // туловище
        [44, 20, arm, 12, rightArmX, 8, false], // правая рука
        legacy ? [44, 20, arm, 12, leftArmX, 8, true] : [36, 52, arm, 12, leftArmX, 8, false], // левая рука
        [8, 8, 8, 8, 4, 0, false], // голова

        // Второй слой: куртка, рукава, штанины (у 64×32 их нет)
        ...(legacy ? [] : [
            [4, 36, 4, 12, 4, 20, false],
            [4, 52, 4, 12, 8, 20, false],
            [20, 36, 8, 12, 4, 8, false],
            [44, 36, arm, 12, rightArmX, 8, false],
            [52, 52, arm, 12, leftArmX, 8, false],
        ]),
    ]
}

/** Лицо со второго слоя (шляпа), 8×8 спереди. */
const HAT_FRONT = [40, 8, 8, 8]

/**
 * Рисовать ли шляпу.
 *
 * У старых скинов 64×32 область шляпы часто залита сплошным цветом
 * (как у Notch). Minecraft в таком случае считает её прозрачной —
 * повторяем это правило, иначе лицо закрывается чёрным квадратом.
 */
function hasHatLayer(image) {
    if (image.naturalHeight !== 32) return true

    const probe = document.createElement('canvas')
    probe.width = 32
    probe.height = 16

    const ctx = probe.getContext('2d', { willReadFrequently: true })
    ctx.drawImage(image, 32, 0, 32, 16, 0, 0, 32, 16)

    const { data } = ctx.getImageData(0, 0, 32, 16)

    for (let i = 3; i < data.length; i += 4) {
        if (data[i] < 128) return true
    }

    return false
}

/**
 * Фигура спереди на холсте: 16×32 «пикселя скина», умноженные на scale.
 * @param {HTMLImageElement} image
 * @param {{ model?: 'classic'|'slim', scale?: number }} options
 */
export function renderSkinFront(image, { model = 'classic', scale = 8 } = {}) {
    const legacy = image.naturalHeight === 32
    const canvas = document.createElement('canvas')

    canvas.width = 16 * scale
    canvas.height = 32 * scale

    const ctx = canvas.getContext('2d')
    ctx.imageSmoothingEnabled = false

    for (const [sx, sy, w, h, dx, dy, mirror] of frontParts(model, legacy)) {
        ctx.save()

        if (mirror) {
            ctx.translate((dx + w) * scale, dy * scale)
            ctx.scale(-1, 1)
            ctx.drawImage(image, sx, sy, w, h, 0, 0, w * scale, h * scale)
        } else {
            ctx.drawImage(image, sx, sy, w, h, dx * scale, dy * scale, w * scale, h * scale)
        }

        ctx.restore()
    }

    if (hasHatLayer(image)) {
        const [sx, sy, w, h] = HAT_FRONT
        ctx.drawImage(image, sx, sy, w, h, 4 * scale, 0, w * scale, h * scale)
    }

    return canvas
}

/**
 * Лицо с маской второго слоя — для аватарок.
 * @param {HTMLImageElement} image
 */
export function renderSkinHead(image, size = 64) {
    const canvas = document.createElement('canvas')

    canvas.width = size
    canvas.height = size

    const ctx = canvas.getContext('2d')
    ctx.imageSmoothingEnabled = false

    ctx.drawImage(image, 8, 8, 8, 8, 0, 0, size, size)

    if (hasHatLayer(image)) {
        ctx.drawImage(image, 40, 8, 8, 8, 0, 0, size, size)
    }

    return canvas
}
