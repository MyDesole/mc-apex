/**
 * Кадр 3D-модели скина для карточки игрока.
 *
 * Фигура в полный рост, чуть повёрнута (вид три четверти) и со вторым
 * слоем — курткой, рукавами, шапкой. Плоский рендер спереди слоёв
 * не показывал и выглядел как наклейка.
 *
 * skinview3d (three.js) тяжёлый — грузится отдельным чанком только тут.
 * Без WebGL возвращаем null, и карточка рисует плоскую фигуру.
 *
 * @param {{ url: string, model: string }} skin  результат loadSkin()
 * @param {{ width: number, height: number }} size  размер кадра в пикселях
 * @returns {Promise<HTMLCanvasElement|null>}
 */
export async function renderSkin3d(skin, { width, height }) {
    if (!skin?.url) return null

    let viewer = null

    try {
        const lib = await import('skinview3d')

        viewer = new lib.SkinViewer({
            canvas: document.createElement('canvas'),
            width,
            height,
            // Кадр нужен ровно width×height: по умолчанию холст берёт
            // devicePixelRatio, и на телефоне (2–3x) в карточку попадал
            // только левый верхний угол фигуры
            pixelRatio: 1,
            // Без этого кадр после render() будет пустым при копировании
            preserveDrawingBuffer: true,
        })

        await viewer.loadSkin(skin.url, { model: skin.model === 'slim' ? 'slim' : 'default' })

        viewer.background = null
        viewer.autoRotate = false
        viewer.animation = null
        viewer.controls.enabled = false

        viewer.fov = 30
        viewer.zoom = 0.78
        viewer.globalLight.intensity = 2.4
        viewer.cameraLight.intensity = 0.9

        // Три четверти: повернуть корпус и чуть развести руки, как в позе
        viewer.playerWrapper.rotation.y = -0.5
        viewer.playerObject.skin.leftArm.rotation.z = 0.12
        viewer.playerObject.skin.rightArm.rotation.z = -0.12

        viewer.render()

        // Копируем в обычный 2D-холст: WebGL-контекст сейчас уничтожим
        const frame = document.createElement('canvas')
        frame.width = width
        frame.height = height
        // Масштабируем весь холст в кадр — на случай, если плотность всё же учлась
        frame.getContext('2d').drawImage(viewer.canvas, 0, 0, width, height)

        return frame
    } catch {
        return null
    } finally {
        viewer?.dispose()
    }
}
