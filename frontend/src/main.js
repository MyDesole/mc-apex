import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from '@/App.vue'
import router from '@/router/index.js'
import '@fontsource/golos-text/400.css'
import '@fontsource/golos-text/500.css'
import '@fontsource/golos-text/600.css'
import '@fontsource/golos-text/700.css'
import '@fontsource/golos-text/800.css'
import '@fontsource/unbounded/500.css'
import '@fontsource/unbounded/600.css'
import '@fontsource/unbounded/700.css'
import '@fontsource/unbounded/800.css'
import './assets/base.css'
import './assets/main.css'
import './assets/transitions.css'
import './assets/backdrops.css'
import './assets/nameEffects.css'
import './assets/chatBubbles.css'
import './assets/petTricks.css'
import './assets/content-page.css'
import './echo'

import { useAuthStore } from '@/stores/core/auth.js'
import { initSentry, setSentryUser } from '@/sentry.js'
import { unlockSoundOnFirstGesture } from '@/utils/sound.js'
import { watch } from 'vue'
import { isApiError, notifyError } from '@/utils/toast.js'

const app = createApp(App)

initSentry(app, router)

/*
 * Страховка от «молчаливых» сбоев. Если страница не обработала ошибку
 * сама, игрок всё равно узнаёт, что случилось, а не смотрит на пустой
 * блок или кнопку, которая «не нажимается».
 * Ставится после Sentry: его обработчик сохраняем и вызываем первым.
 */
const reportError = app.config.errorHandler

app.config.errorHandler = (error, instance, info) => {
    if (reportError) reportError(error, instance, info)
    else console.error(error)

    notifyError(error)
}

window.addEventListener('unhandledrejection', (event) => {
    // Только ответы API: у них готовый понятный текст. Чужие промисы
    // (расширения браузера, плеер) игроку показывать незачем.
    if (isApiError(event.reason)) notifyError(event.reason)
})

const pinia = createPinia()

app.use(pinia)

const auth = useAuthStore(pinia)

// Кто вошёл, выясняется один раз; проверка доступа ждёт ответа
const authReady = auth.fetchMe()

/*
 * Проверка доступа ставится ДО app.use(router): роутер начинает первый
 * переход сразу при подключении. Раньше она добавлялась после ожидания
 * fetchMe и первый переход пропускала: по прямой ссылке гость открывал
 * /admin и /profile (пустые страницы с ошибками), а вошедший — /register.
 */
router.beforeEach(async (to) => {
    await authReady

    if (to.meta.auth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } }
    }

    // куратор выше всех: проходит везде, где пускают администратора
    const role = auth.user?.role
    const roleOk = !to.meta.role || to.meta.role.includes(role) || (role === 'curator' && to.meta.role.includes('admin'))

    if (!roleOk) {
        return { name: 'home' }
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'home' }
    }
})

app.use(router)

await authReady

watch(() => auth.user?.id, () => setSentryUser(auth.user), { immediate: true })

app.mount('#app')

// Браузер разрешает звук только после действия игрока
unlockSoundOnFirstGesture()